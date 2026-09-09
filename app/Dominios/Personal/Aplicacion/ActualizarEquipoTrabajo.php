<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\QueryException;

/**
 * Edición de los datos descriptivos de un equipo de trabajo (tarea 72,
 * HU-49). No toca integrantes ni recursos — esos se editan con sus propios
 * casos de uso, cada asignación es una fila con su propia vigencia (ADR 0015
 * punto 3: un gasto de marzo se atribuye a la formación de marzo, editar acá
 * no puede reescribir esa historia).
 */
final class ActualizarEquipoTrabajo
{
    /**
     * @throws EquipoTrabajoDuplicado si el código ya pertenece a otro equipo
     *                                activo (índice parcial
     *                                `per_equipos_trabajo_codigo_unico`).
     */
    public function ejecutar(
        EquipoTrabajo $equipo,
        string $codigo,
        ?string $nombre,
        int $baseId,
        EstadoEquipoTrabajo $estado,
        string $desde,
        ?string $hasta,
    ): EquipoTrabajo {
        $equipo->fill([
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
