<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\DronDuplicado;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\QueryException;

/**
 * Edición de un dron (HU-27, tarea 36). Mismo criterio que `CrearDron` para
 * la traducción de la violación del índice único parcial.
 */
final class ActualizarDron
{
    /**
     * @throws DronDuplicado si el identificador ya pertenece a otro dron
     *                       activo (índice parcial `ope_drones_identificador_unico`).
     */
    public function ejecutar(Dron $dron, string $identificador, ?string $modelo, ?string $capacidadL, ?string $capacidadKg): Dron
    {
        $dron->identificador = $identificador;
        $dron->modelo = $modelo;
        $dron->capacidad_l = $capacidadL;
        $dron->capacidad_kg = $capacidadKg;

        try {
            $dron->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $identificador);
        }

        return $dron->refresh();
    }

    /**
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
