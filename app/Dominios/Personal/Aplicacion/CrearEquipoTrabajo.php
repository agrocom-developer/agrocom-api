<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\QueryException;

/**
 * Alta de un equipo de trabajo (tarea 72, HU-49, ADR 0015 punto 3): código,
 * nombre opcional, base, estado y vigencia propia. Sin integrantes ni
 * recursos todavía — se asignan aparte, cada uno con su propia vigencia (ver
 * `AsignarIntegranteEquipo`/`AsignarRecursoEquipo`).
 */
final class CrearEquipoTrabajo
{
    /**
     * @throws EquipoTrabajoDuplicado si el código ya pertenece a otro equipo
     *                                activo (índice parcial
     *                                `per_equipos_trabajo_codigo_unico`).
     */
    public function ejecutar(
        string $codigo,
        ?string $nombre,
        int $baseId,
        EstadoEquipoTrabajo $estado,
        string $desde,
        ?string $hasta,
    ): EquipoTrabajo {
        $equipo = new EquipoTrabajo([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'base_id' => $baseId,
            'estado' => $estado->value,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        try {
            $equipo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $codigo);
        }

        return $equipo->refresh();
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
