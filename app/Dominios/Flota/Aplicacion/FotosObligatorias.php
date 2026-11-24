<?php

namespace App\Dominios\Flota\Aplicacion;

use Illuminate\Support\Facades\DB;

/**
 * Qué fotos le faltan a un dron para poder aprobarse (ADR 0042): cada tipo de
 * `config('flota.fotos_obligatorias')` necesita al menos una foto `aprobada`.
 * Una foto en revisión o rechazada no cuenta: lo que el cliente ve al comparar
 * ofertas son solo las aprobadas.
 */
final class FotosObligatorias
{
    /** @return list<string> */
    public function tipos(): array
    {
        return array_values(array_map('strval', (array) config('flota.fotos_obligatorias', [])));
    }

    /** @return list<string> los tipos obligatorios sin una foto aprobada. */
    public function faltantes(int $dronId): array
    {
        $aprobadas = DB::table('flo_dron_fotos')
            ->where('dron_id', $dronId)
            ->where('estado', 'aprobada')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('tipo')
            ->map(fn ($tipo): string => (string) $tipo)
            ->all();

        return array_values(array_diff($this->tipos(), $aprobadas));
    }
}
