<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Editar una tarifa cambia lo que se propone de acá en más; no toca ninguna
 * orden de trabajo ya armada ni ningún devengo (copian los montos al nacer).
 */
final class ActualizarTarifa
{
    use DestacaTarifaPredeterminada;

    public function ejecutar(Tarifa $tarifa, DatosTarifa $datos): Tarifa
    {
        return DB::transaction(function () use ($tarifa, $datos): Tarifa {
            if ($datos->predeterminada) {
                $this->quitarPredeterminadaActual($tarifa->id);
            }

            $tarifa->fill($datos->atributos());

            try {
                $tarifa->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicada($excepcion, $datos->nombre);
            }

            return $tarifa->refresh();
        });
    }
}
