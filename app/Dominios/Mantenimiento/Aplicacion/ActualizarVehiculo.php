<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Database\QueryException;

/**
 * Edición de un vehículo de la flota (HU-40, tarea 50). Mismo criterio que
 * `CrearVehiculo` para la traducción de la violación del índice único
 * parcial.
 */
final class ActualizarVehiculo
{
    /**
     * @throws VehiculoDuplicado si el identificador ya pertenece a otro
     *                           vehículo activo (índice parcial
     *                           `man_vehiculos_identificador_unico`).
     */
    public function ejecutar(Vehiculo $vehiculo, string $identificador, ?int $baseId, EstadoVehiculo $estado): Vehiculo
    {
        $vehiculo->identificador = $identificador;
        $vehiculo->base_id = $baseId;
        $vehiculo->estado = $estado->value;

        try {
            $vehiculo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $vehiculo->refresh();
    }

    /**
     * @throws VehiculoDuplicado si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $identificador): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'man_vehiculos_identificador_unico') || str_contains($mensaje, 'man_vehiculos.identificador')) {
            throw VehiculoDuplicado::porIdentificador($identificador);
        }

        throw $excepcion;
    }
}
