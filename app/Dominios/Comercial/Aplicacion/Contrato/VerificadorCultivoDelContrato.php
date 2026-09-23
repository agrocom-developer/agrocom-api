<?php

namespace App\Dominios\Comercial\Aplicacion\Contrato;

use App\Dominios\Comercial\Dominio\Excepciones\LotesDeDistintoCultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;

/**
 * Guarda de cultivo y etapa del contrato (22/9/2026): entre los lotes que
 * tienen siembra registrada en la campaña, todos deben compartir cultivo y
 * etapa. Los lotes sin siembra no bloquean (sólidos: terreno limpio). Misma
 * tabla del módulo (`com_lote_campania`), así que se lee directo, como
 * `VerificadorLotesDelContrato`.
 */
final class VerificadorCultivoDelContrato
{
    /**
     * @param  list<int>  $loteIds
     *
     * @throws LotesDeDistintoCultivo si hay más de un par (cultivo, etapa) entre los lotes sembrados.
     */
    public static function verificar(array $loteIds, int $campaniaId): void
    {
        $grupos = self::gruposDeSiembra($loteIds, $campaniaId);

        if (count($grupos) > 1) {
            throw LotesDeDistintoCultivo::paraGrupos($grupos);
        }
    }

    /**
     * @param  list<int>  $loteIds
     * @return list<array{cultivo: string, etapa: string|null, codigos: list<string>}>
     */
    public static function gruposDeSiembra(array $loteIds, int $campaniaId): array
    {
        if ($loteIds === []) {
            return [];
        }

        $grupos = [];

        LoteCampania::query()
            ->whereIn('lote_id', $loteIds)
            ->where('campania_id', $campaniaId)
            ->with(['lote:id,codigo', 'cultivo:id,nombre_comun'])
            ->orderBy('lote_id')
            ->get()
            ->each(function (LoteCampania $siembra) use (&$grupos): void {
                $etapa = $siembra->etapa_cultivo?->value;
                $clave = $siembra->cultivo_id.'|'.($etapa ?? '');

                $grupos[$clave] ??= [
                    'cultivo' => $siembra->cultivo->nombre_comun,
                    'etapa' => $etapa !== null ? __("comercial.siembra.etapa_opcion.{$etapa}") : null,
                    'codigos' => [],
                ];
                $grupos[$clave]['codigos'][] = $siembra->lote->codigo;
            });

        return array_values($grupos);
    }
}
