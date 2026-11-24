<?php

namespace App\Dominios\Flota\Aplicacion\MaquinaEstados;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que escribe el `estado` de una foto de dron (invariante 7 de CLAUDE.md):
 * nace `en_revision` y un administrador la pasa a `aprobada` o `rechazada` (con motivo);
 * el proveedor no cambia el estado. Una foto ya revisada se puede volver a revisar.
 */
final class MaquinaEstadosFotoDeDron
{
    public function crear(int $dronId, string $tipo, string $archivo, int $usuarioId): int
    {
        return (int) DB::table('flo_dron_fotos')->insertGetId([
            'dron_id' => $dronId,
            'tipo' => $tipo,
            'archivo' => $archivo,
            'estado' => 'en_revision',
            'created_by' => $usuarioId,
            'updated_by' => $usuarioId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /** @return int filas afectadas (0 si la foto no existe). */
    public function revisar(int $fotoId, bool $aprobar, ?string $motivo, int $revisorUsuarioId): int
    {
        return DB::table('flo_dron_fotos')->where('id', $fotoId)->whereNull('deleted_at')->update([
            'estado' => $aprobar ? 'aprobada' : 'rechazada',
            'motivo' => $aprobar ? null : trim((string) $motivo),
            'revisado_por' => $revisorUsuarioId,
            'revisado_en' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
