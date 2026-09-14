<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\DronDuplicado;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\QueryException;

/**
 * Alta de un dron (HU-27, tarea 36): identificador, modelo (texto libre) y
 * capacidad de carga en litros (30/50/60, CHECK de base de datos). Capacidad
 * en kilos (HU-81, tarea 96) sin catálogo cerrado, ver `CrearDronRequest`.
 */
final class CrearDron
{
    /**
     * @throws DronDuplicado si el identificador ya pertenece a otro dron
     *                       activo (índice parcial `ope_drones_identificador_unico`).
     */
    public function ejecutar(string $identificador, ?string $modelo, ?string $capacidadL, ?string $capacidadKg): Dron
    {
        $dron = new Dron([
            'identificador' => $identificador,
            'modelo' => $modelo,
            'capacidad_l' => $capacidadL,
            'capacidad_kg' => $capacidadKg,
        ]);

        try {
            $dron->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $dron->refresh();
    }

    /**
     * Traduce la violación del índice único parcial a una excepción de
     * dominio legible — nunca deja propagarse el 500 crudo del motor de base
     * de datos. El formato del mensaje difiere por driver: Postgres nombra
     * el índice (`ope_drones_identificador_unico`); SQLite (motor de los
     * tests) nombra tabla.columna (`ope_drones.identificador`) — mismo
     * criterio que `CrearCliente::relanzarComoDuplicado`.
     *
     * @throws DronDuplicado si la violación corresponde al identificador.
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $identificador): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_drones_identificador_unico') || str_contains($mensaje, 'ope_drones.identificador')) {
            throw DronDuplicado::porIdentificador($identificador);
        }

        throw $excepcion;
    }
}
