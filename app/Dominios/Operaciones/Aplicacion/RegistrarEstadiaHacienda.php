<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\EstadiaAbiertaExistente;
use App\Dominios\Operaciones\Dominio\Excepciones\SalidaAnteriorAEntrada;
use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Alta de una estadía en hacienda DESDE EL PANEL (reforma 19/9/2026: hasta
 * hoy solo nacía en la app de campo por `POST /api/sync` — la app de campo
 * todavía no está distribuida, así que la oficina también registra). Mismo
 * criterio de `uuid_cliente` generado en servidor que `CrearOrdenTrabajo`
 * para los trabajos que nacen en el panel: `(string) Str::uuid()`, no hay
 * dispositivo que lo traiga.
 *
 * Admite cargar una estadía ya terminada (`$salida` no nulo, con su propio
 * `cierre_uuid_cliente`) — el jefe de campo puede estar poniendo al día
 * varios días de historial de una sola vez, no solo abriendo una en curso.
 *
 * Dos guardas, evaluadas ANTES de tocar la base:
 *
 *   1. La cuadrilla está vigente a la fecha de ENTRADA
 *      ({@see LecturaEquipoTrabajo::vigentesAFecha()}, mismo criterio que
 *      `CrearOrdenTrabajo`, aplicado a la fecha del hecho en vez de a hoy).
 *   2. Si viene `$salida`, es posterior a `$entrada`.
 *
 * La tercera —una sola estadía EN CURSO por cuadrilla— no se verifica antes:
 * la garantiza el índice único parcial `ope_estadias_hacienda_equipo_abierta_unico`
 * (invariante 1 de CLAUDE.md extendida a esta regla de unicidad, mismo
 * criterio que el resto del motor de sync), y la violación se traduce acá a
 * {@see EstadiaAbiertaExistente} — nunca se pre-consulta con un `SELECT`, que
 * tendría condición de carrera. Como ese índice es parcial (`WHERE salida IS
 * NULL`), cargar una estadía YA CERRADA nunca choca con él, aunque la
 * cuadrilla tenga otra abierta en paralelo.
 */
final class RegistrarEstadiaHacienda
{
    public function __construct(private readonly LecturaEquipoTrabajo $equipos) {}

    /**
     * @throws EquipoTrabajoNoVigente si la cuadrilla no está vigente a la fecha de entrada.
     * @throws SalidaAnteriorAEntrada si `$salida` no es posterior a `$entrada`.
     * @throws EstadiaAbiertaExistente si la cuadrilla ya tiene una estadía en curso.
     */
    public function ejecutar(
        int $equipoTrabajoId,
        int $propiedadId,
        string $entrada,
        TipoAlojamiento $tipoAlojamiento,
        ?int $vehiculoId,
        ?string $observacion,
        ?string $salida,
    ): EstadiaHacienda {
        $entradaUtc = self::normalizarUtc($entrada);

        $idsVigentes = array_map(
            static fn (DatosEquipoTrabajo $equipo): int => $equipo->id,
            $this->equipos->vigentesAFecha($entradaUtc->toDateString()),
        );

        if (! in_array($equipoTrabajoId, $idsVigentes, true)) {
            throw EquipoTrabajoNoVigente::porId($equipoTrabajoId);
        }

        $salidaUtc = $salida !== null ? self::normalizarUtc($salida) : null;

        if ($salidaUtc !== null && ! $salidaUtc->greaterThan($entradaUtc)) {
            throw new SalidaAnteriorAEntrada;
        }

        $estadia = new EstadiaHacienda([
            'uuid_cliente' => (string) Str::uuid(),
            'equipo_trabajo_id' => $equipoTrabajoId,
            'propiedad_id' => $propiedadId,
            'entrada' => $entradaUtc,
            'salida' => $salidaUtc,
            'vehiculo_id' => $vehiculoId,
            'tipo_alojamiento' => $tipoAlojamiento,
            'observacion' => $observacion,
            'cierre_uuid_cliente' => $salidaUtc !== null ? (string) Str::uuid() : null,
        ]);

        try {
            $estadia->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoAbiertaExistente($excepcion, $equipoTrabajoId);
        }

        return $estadia->refresh();
    }

    /**
     * @throws EstadiaAbiertaExistente si la violación corresponde al índice de estadía abierta.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoAbiertaExistente(QueryException $excepcion, int $equipoTrabajoId): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_estadias_hacienda_equipo_abierta_unico') || str_contains($mensaje, 'ope_estadias_hacienda.equipo_trabajo_id')) {
            throw EstadiaAbiertaExistente::porEquipoTrabajoId($equipoTrabajoId);
        }

        throw $excepcion;
    }

    /**
     * Mismo mecanismo (y mismo motivo) que
     * `EscrituraSincronizacionEloquent::normalizarUtc()`: `ope_estadias_hacienda.entrada`/`salida`
     * son `dateTime` sin tz — sin este `->utc()`, `format()` escribiría la
     * hora del huso original literal. El panel manda el datetime del
     * formulario tal como lo interpreta el servidor (mismo criterio que
     * `Aplicacion/RegistrarPausa` con `atoms/datetime`), no offset propio del
     * navegador.
     */
    private static function normalizarUtc(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor)->utc();
    }
}
