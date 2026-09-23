<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEditable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajoEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Brick\Math\BigDecimal;

/**
 * Edición de un trabajo ya sincronizado (HU-93, tarea 108): el encargado
 * corrige lote/equipo/hectáreas/turno de un trabajo cargado mal, sin tener
 * que pasar por otra pantalla. Nunca crea ni borra el trabajo (eso sigue
 * siendo de `Aplicacion/CrearOrdenTrabajo`/el motor de sync) — solo reasigna
 * estos campos, los mismos que `CrearOrdenTrabajo` resuelve al abrirlo.
 *
 * `turno`/`turno_hora_inicio`/`turno_hora_fin` (reforma 18/9/2026) son
 * opcionales acá a diferencia de `CrearOrdenTrabajo` (donde los tres son
 * obligatorios juntos): un trabajo nacido por sync puro nunca los tuvo, y
 * esta edición no debería empezar a exigirlos recién acá.
 *
 * Guarda 1 (invariante 2 de CLAUDE.md): un trabajo con estado de tablero
 * `validado` (TODAS sus sesiones vigentes ya pasaron por HU-14) nunca se
 * edita — criterio por defecto de
 * `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md` §4.4.
 *
 * Guardas 2 a 4: mismas tres guardas de `AsignarEquiposOrden` (equipo
 * vigente hoy, lote perteneciente a la orden del trabajo, hectáreas del
 * lote no superadas), reevaluadas contra el estado FINAL del lote —
 * `$trabajo` se excluye de su propia suma para no contarse dos veces.
 */
final class ActualizarTrabajo
{
    public function __construct(private readonly LecturaEquipoTrabajo $equipos) {}

    /**
     * @param  array{lote_id: int, equipo_trabajo_id: int|null, hectareas_declaradas: string, turno?: string|null, turno_hora_inicio?: string|null, turno_hora_fin?: string|null}  $atributos
     *
     * @throws TrabajoValidadoNoEditable si `$trabajo` está `validado`.
     * @throws EquipoTrabajoNoVigente si el equipo elegido no está vigente hoy.
     * @throws LoteNoPerteneceAOrden si el lote no pertenece a la orden del trabajo.
     * @throws HectareasAsignadasSuperanLote si las hectáreas superan lo solicitado para ese lote.
     */
    public function ejecutar(Trabajo $trabajo, array $atributos): Trabajo
    {
        if ($trabajo->estadoTablero() === EstadoTableroTrabajo::Validado) {
            throw TrabajoValidadoNoEditable::porId((int) $trabajo->id);
        }

        if ($atributos['equipo_trabajo_id'] !== null) {
            $idsVigentes = array_map(
                static fn (DatosEquipoTrabajo $equipo): int => $equipo->id,
                $this->equipos->vigentesAFecha(now()->toDateString()),
            );

            if (! in_array($atributos['equipo_trabajo_id'], $idsVigentes, true)) {
                throw EquipoTrabajoNoVigente::porId($atributos['equipo_trabajo_id']);
            }
        }

        /** @var OrdenLote|null $ordenLote */
        $ordenLote = OrdenLote::query()
            ->where('orden_id', $trabajo->orden_id)
            ->where('lote_id', $atributos['lote_id'])
            ->first();

        if ($ordenLote === null) {
            throw LoteNoPerteneceAOrden::porLote($atributos['lote_id'], $trabajo->orden_id);
        }

        $yaAsignado = BigDecimal::of((string) Trabajo::query()
            ->where('orden_id', $trabajo->orden_id)
            ->where('lote_id', $atributos['lote_id'])
            ->where('id', '!=', $trabajo->id)
            ->sum('hectareas_declaradas'));

        $totalFinal = $yaAsignado->plus($atributos['hectareas_declaradas']);
        $hectareasSolicitadas = BigDecimal::of((string) $ordenLote->hectareas_solicitadas);

        if ($totalFinal->isGreaterThan($hectareasSolicitadas)) {
            throw HectareasAsignadasSuperanLote::porOrdenYLote(
                $trabajo->orden_id,
                $atributos['lote_id'],
                (string) $totalFinal,
                (string) $hectareasSolicitadas,
            );
        }

        $equipoAnterior = $trabajo->equipo_trabajo_id;

        $trabajo->fill($atributos);
        $trabajo->save();

        $this->heredarCondicionDePago($trabajo, $equipoAnterior);

        return $trabajo->refresh();
    }

    /**
     * Si el trabajo pasó a otro equipo dentro de la misma Orden de Trabajo y
     * ese equipo no tiene condición de pago propia ahí, hereda la del equipo
     * que lo tenía (ADR 0023): lo negociado era por ese trabajo. Sin condición
     * previa no se inventa nada —el devengo cae a la tarifa predeterminada—.
     */
    private function heredarCondicionDePago(Trabajo $trabajo, ?int $equipoAnterior): void
    {
        $equipoNuevo = $trabajo->equipo_trabajo_id;

        if ($trabajo->orden_trabajo_id === null || $equipoNuevo === null || $equipoNuevo === $equipoAnterior) {
            return;
        }

        $yaTiene = OrdenTrabajoEquipo::query()
            ->where('orden_trabajo_id', $trabajo->orden_trabajo_id)
            ->where('equipo_trabajo_id', $equipoNuevo)
            ->exists();

        if ($yaTiene || $equipoAnterior === null) {
            return;
        }

        $condicionAnterior = OrdenTrabajoEquipo::query()
            ->where('orden_trabajo_id', $trabajo->orden_trabajo_id)
            ->where('equipo_trabajo_id', $equipoAnterior)
            ->first();

        if ($condicionAnterior === null) {
            return;
        }

        OrdenTrabajoEquipo::create([
            ...$condicionAnterior->only(['orden_trabajo_id', 'tarifa_id', 'modalidad_pago', 'monto_piloto', 'monto_auxiliar', 'negociado', 'motivo_negociacion']),
            'equipo_trabajo_id' => $equipoNuevo,
        ]);
    }
}
