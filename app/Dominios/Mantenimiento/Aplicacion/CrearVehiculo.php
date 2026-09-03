<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Database\QueryException;

/**
 * Alta de un vehículo de la flota (HU-40, tarea 50): identificador, base
 * asignada (opcional) y estado.
 */
final class CrearVehiculo
{
    /**
     * @throws VehiculoDuplicado si el identificador ya pertenece a otro
     *                           vehículo activo (índice parcial
     *                           `man_vehiculos_identificador_unico`).
     */
    public function ejecutar(string $identificador, ?int $baseId, EstadoVehiculo $estado): Vehiculo
    {
        $vehiculo = new Vehiculo([
            'identificador' => $identificador,
            'base_id' => $baseId,
            'estado' => $estado->value,
        ]);

        try {
            $vehiculo->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $vehiculo->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — mismo criterio que `CrearDron::relanzarComoDuplicado`.
     * El formato del mensaje difiere por driver: Postgres nombra el índice
     * (`man_vehiculos_identificador_unico`); SQLite (motor de los tests)
     * nombra tabla.columna (`man_vehiculos.identificador`).
     *
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
