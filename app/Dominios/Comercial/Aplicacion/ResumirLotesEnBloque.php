<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\ResumenLotesEnBloque;
use App\Dominios\Comercial\Dominio\PrefijoCodigoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Support\Collection;

/**
 * Lectura para la pantalla "Editar lotes en bloque": el prefijo con el que
 * se numeran los lotes de la propiedad, sus códigos en orden de alta y, por
 * cada atributo que se edita en bloque, su valor común — o la marca de que
 * varía.
 */
final class ResumirLotesEnBloque
{
    private const ATRIBUTOS = ['hectareas', 'desnivel', 'limpieza', 'restricciones'];

    public function ejecutar(Propiedad $propiedad): ResumenLotesEnBloque
    {
        /** @var Collection<int, Lote> $lotes */
        $lotes = $propiedad->lotes()->orderBy('id')->get();

        $comunes = [];
        $variables = [];

        foreach (self::ATRIBUTOS as $atributo) {
            $valores = $lotes
                ->map(fn (Lote $lote) => $this->normalizar($lote->getAttribute($atributo)))
                ->unique()
                ->values();

            if ($valores->count() > 1) {
                $variables[] = $atributo;
            }

            $comunes[$atributo] = $valores->count() === 1 ? $valores->first() : null;
        }

        $codigos = $lotes->pluck('codigo')->all();

        return new ResumenLotesEnBloque(
            PrefijoCodigoLote::inferir($codigos),
            $codigos,
            $comunes['hectareas'],
            $comunes['desnivel'],
            $comunes['limpieza'],
            $comunes['restricciones'],
            $variables,
        );
    }

    private function normalizar(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
