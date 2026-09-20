<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Aplicacion\MaquinaEstados\MaquinaEstadosEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\QueryException;

/**
 * Alta de un equipo de trabajo (tarea 72, HU-49, ADR 0015 punto 3): código,
 * nombre opcional, base y vigencia propia. Sin integrantes ni recursos
 * todavía — se asignan aparte, cada uno con su propia vigencia (ver
 * `AsignarIntegranteEquipo`/`AsignarRecursoEquipo`; para el alta de una sola
 * vez con piloto/ayudante/dron ya incluidos, ver `ArmarCuadrilla`).
 *
 * Ya NO recibe `estado` (corrección 19/9/2026, invariante 7 de CLAUDE.md):
 * el estado inicial (`activo`) lo fija
 * {@see MaquinaEstadosEquipoTrabajo::crear()}, nunca esta clase
 * directamente — mismo criterio que `Campania\Aplicacion\CrearCampania`.
 */
final class CrearEquipoTrabajo
{
    public function __construct(private readonly MaquinaEstadosEquipoTrabajo $maquinaEstados) {}

    /**
     * @throws EquipoTrabajoDuplicado si el código ya pertenece a otro equipo
     *                                activo (índice parcial
     *                                `per_equipos_trabajo_codigo_unico`).
     */
    public function ejecutar(
        string $codigo,
        ?string $nombre,
        int $baseId,
        string $desde,
        ?string $hasta,
    ): EquipoTrabajo {
        try {
            return $this->maquinaEstados->crear([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'base_id' => $baseId,
                'desde' => $desde,
                'hasta' => $hasta,
            ]);
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $codigo);
        }
    }

    /**
     * Mismo criterio que `CrearGenerador::relanzarComoDuplicado`: el mensaje
     * difiere por driver (Postgres nombra el índice; SQLite, tabla.columna).
     *
     * @throws EquipoTrabajoDuplicado si la violación corresponde al código.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $codigo): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'per_equipos_trabajo_codigo_unico') || str_contains($mensaje, 'per_equipos_trabajo.codigo')) {
            throw EquipoTrabajoDuplicado::porCodigo($codigo);
        }

        throw $excepcion;
    }
}
