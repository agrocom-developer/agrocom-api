<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Los lotes elegidos para el contrato mezclan cultivos o etapas en la campaña
 * (decisión del dueño, 22/9/2026, tras el hallazgo del 21/9: un contrato
 * agrupa lotes del mismo cultivo y la misma etapa porque cada etapa pide un
 * trabajo distinto). Los lotes sin siembra registrada no cuentan: en sólidos
 * el lote solo tiene terreno limpio y eso nunca es un error.
 */
final class LotesDeDistintoCultivo extends RuntimeException
{
    /** @param  list<array{cultivo: string, etapa: string|null, codigos: list<string>}>  $grupos */
    public static function paraGrupos(array $grupos): self
    {
        $descripcion = array_map(
            fn (array $grupo): string => Texto::de('comercial.errores.lotes_de_distinto_cultivo_grupo', [
                'cultivo' => $grupo['cultivo'],
                'etapa' => $grupo['etapa'] ?? Texto::de('comercial.contratos.lotes_modal_sin_etapa'),
                'codigos' => implode(', ', $grupo['codigos']),
            ]),
            $grupos,
        );

        return new self(Texto::de('comercial.errores.lotes_de_distinto_cultivo', ['grupos' => implode('; ', $descripcion)]));
    }
}
