<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Dominio\Excepciones\TarifaDuplicada;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use Illuminate\Database\QueryException;

/**
 * Lo común a crear y actualizar una tarifa: a lo sumo una predeterminada
 * viva (el índice único parcial `fin_tarifas_predeterminada_unica` lo
 * garantiza; acá se destaca la nueva quitando la anterior antes de guardar)
 * y el nombre único traducido a excepción de dominio.
 */
trait DestacaTarifaPredeterminada
{
    private function quitarPredeterminadaActual(?int $exceptoId = null): void
    {
        Tarifa::query()
            ->where('predeterminada', true)
            ->when($exceptoId !== null, fn ($consulta) => $consulta->whereKeyNot($exceptoId))
            ->get()
            ->each(fn (Tarifa $anterior) => $anterior->forceFill(['predeterminada' => false])->save());
    }

    private function relanzarComoDuplicada(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'fin_tarifas_nombre_unico')) {
            throw TarifaDuplicada::porNombre($nombre);
        }

        throw $excepcion;
    }
}
