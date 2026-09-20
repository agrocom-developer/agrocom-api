<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Dominio\Excepciones\RecursoEquipoInvalido;
use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\ResultadoSolapamientoVigencias;
use App\Dominios\Personal\Dominio\RolEquipo;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una cuadrilla completa en un solo paso (pedido del dueño,
 * 19/9/2026): un piloto, un ayudante (con posible segundo) y su dron —
 * obligatorios— y, opcionalmente, vehículo, generador y baterías. Reutiliza
 * los casos de uso ya existentes ({@see CrearEquipoTrabajo},
 * {@see AsignarIntegranteEquipo}, {@see AsignarRecursoEquipo}) — ninguna
 * regla nueva, solo orquestación dentro de UNA transacción (ADR 0003): si
 * algo falla (un dron inválido, por ejemplo), no queda nada a medias, ni
 * siquiera la fila de la cuadrilla.
 *
 * Un aviso de solapamiento (una persona o un recurso vigente en OTRA
 * cuadrilla, ADR 0015 punto 3) NO frena el alta — se guarda igual, con aviso
 * — así que se acumulan los de las siete asignaciones posibles y se
 * devuelven juntos en {@see ResultadoArmarCuadrilla}.
 */
final class ArmarCuadrilla
{
    public function __construct(
        private readonly CrearEquipoTrabajo $crearEquipoTrabajo,
        private readonly AsignarIntegranteEquipo $asignarIntegranteEquipo,
        private readonly AsignarRecursoEquipo $asignarRecursoEquipo,
    ) {}

    /**
     * @param  list<int>  $bateriaIds  sin repetidos (lo valida `CrearEquipoTrabajoRequest`).
     *
     * @throws EquipoTrabajoDuplicado si el código ya pertenece a otro equipo activo.
     * @throws RecursoEquipoInvalido si el dron, el vehículo, el generador o alguna batería no existe o no está disponible.
     * @throws VigenciaEquipoSolapada si la misma persona o el mismo recurso se repite dentro de esta alta (no debería
     *                                pasar: `CrearEquipoTrabajoRequest` ya exige personas distintas y baterías sin repetir).
     */
    public function ejecutar(
        string $codigo,
        ?string $nombre,
        int $baseId,
        string $desde,
        ?string $hasta,
        int $pilotoId,
        int $ayudanteId,
        ?int $ayudante2Id,
        int $dronId,
        ?int $vehiculoId,
        ?int $generadorId,
        array $bateriaIds,
    ): ResultadoArmarCuadrilla {
        return DB::transaction(function () use (
            $codigo, $nombre, $baseId, $desde, $hasta,
            $pilotoId, $ayudanteId, $ayudante2Id,
            $dronId, $vehiculoId, $generadorId, $bateriaIds,
        ): ResultadoArmarCuadrilla {
            $equipo = $this->crearEquipoTrabajo->ejecutar($codigo, $nombre, $baseId, $desde, $hasta);

            $equiposEnAviso = [];
            $acumular = function (ResultadoSolapamientoVigencias $resultado) use (&$equiposEnAviso): void {
                $equiposEnAviso = [...$equiposEnAviso, ...$resultado->equiposEnAviso];
            };

            $acumular($this->asignarIntegranteEquipo->ejecutar($equipo, $pilotoId, RolEquipo::Piloto, $desde, null));
            $acumular($this->asignarIntegranteEquipo->ejecutar($equipo, $ayudanteId, RolEquipo::Auxiliar, $desde, null));

            if ($ayudante2Id !== null) {
                $acumular($this->asignarIntegranteEquipo->ejecutar($equipo, $ayudante2Id, RolEquipo::Auxiliar, $desde, null));
            }

            $acumular($this->asignarRecursoEquipo->ejecutar($equipo, RecursoTipoEquipo::Dron, $dronId, $desde, null));

            if ($vehiculoId !== null) {
                $acumular($this->asignarRecursoEquipo->ejecutar($equipo, RecursoTipoEquipo::Vehiculo, $vehiculoId, $desde, null));
            }

            if ($generadorId !== null) {
                $acumular($this->asignarRecursoEquipo->ejecutar($equipo, RecursoTipoEquipo::Generador, $generadorId, $desde, null));
            }

            foreach ($bateriaIds as $bateriaId) {
                $acumular($this->asignarRecursoEquipo->ejecutar($equipo, RecursoTipoEquipo::Bateria, (int) $bateriaId, $desde, null));
            }

            return new ResultadoArmarCuadrilla($equipo->refresh(), array_values(array_unique($equiposEnAviso)));
        });
    }
}
