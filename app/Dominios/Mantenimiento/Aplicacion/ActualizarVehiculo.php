<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Database\QueryException;

/**
 * Edición de un vehículo de la flota (HU-40, tarea 50). Mismo criterio que
 * `CrearVehiculo` para la traducción de la violación del índice único
 * parcial.
 *
 * `kilometrajeInicial` (HU-84, tarea 99) SÍ se recibe acá, a diferencia de
 * `ciclosInicial` en `ActualizarBateria`: el criterio de esta HU no pide
 * que quede fijo tras el alta.
 *
 * `tipo` (HU-90, tarea 105) también es editable acá, mismo criterio que
 * `combustible`.
 */
final class ActualizarVehiculo
{
    /**
     * @throws VehiculoDuplicado si el identificador ya pertenece a otro
     *                           vehículo activo (índice parcial
     *                           `man_vehiculos_identificador_unico`).
     */
    public function ejecutar(
        Vehiculo $vehiculo,
        string $identificador,
        ?int $baseId,
        EstadoVehiculo $estado,
        ?string $marca,
        ?string $modelo,
        ?int $anio,
        ?TipoCombustibleVehiculo $combustible,
        bool $es4x4,
        ?string $kilometrajeInicial,
        ?string $kilometrajeActual,
        ?TipoVehiculo $tipo = null,
    ): Vehiculo {
        $vehiculo->identificador = $identificador;
        $vehiculo->tipo = $tipo?->value;
        $vehiculo->base_id = $baseId;
        $vehiculo->estado = $estado->value;
        $vehiculo->marca = $marca;
        $vehiculo->modelo = $modelo;
        $vehiculo->anio = $anio;
        $vehiculo->combustible = $combustible?->value;
        $vehiculo->es_4x4 = $es4x4;
        $vehiculo->kilometraje_inicial = $kilometrajeInicial;
        $vehiculo->kilometraje_actual = $kilometrajeActual;

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
