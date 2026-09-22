<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CrearTarifa
{
    use DestacaTarifaPredeterminada;

    public function ejecutar(DatosTarifa $datos): Tarifa
    {
        return DB::transaction(function () use ($datos): Tarifa {
            if ($datos->predeterminada) {
                $this->quitarPredeterminadaActual();
            }

            $tarifa = new Tarifa($datos->atributos());

            try {
                $tarifa->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicada($excepcion, $datos->nombre);
            }

            return $tarifa->refresh();
        });
    }
}
