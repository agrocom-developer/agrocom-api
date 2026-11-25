<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira los permisos de las actas de conformidad y del reporte técnico de
 * lote (`operaciones.acta.generar`, `operaciones.acta.firmar` y
 * `operaciones.reporte.ver`), cuyas rutas y código ya no existen.
 *
 * Baja lógica de los permisos y de sus asignaciones a roles (mismo criterio
 * que `PERMISOS_RETIRADOS` de `SeguridadSeeder`): no se borra ninguna fila ni
 * ninguna tabla. `down()` las restaura. Sin permisos sembrados en la base
 * (instalación nueva) no hace nada.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
        'operaciones.reporte.ver',
    ];

    public function up(): void
    {
        $p = DB::getTablePrefix();
        $codigos = $this->codigos();

        DB::statement("UPDATE {$p}sec_role_permission SET deleted_at = now(), updated_at = now() WHERE deleted_at IS NULL AND id_permission IN (SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}))");
        DB::statement("UPDATE {$p}sec_permission SET deleted_at = now(), updated_at = now() WHERE deleted_at IS NULL AND code IN ({$codigos})");
    }

    public function down(): void
    {
        $p = DB::getTablePrefix();
        $codigos = $this->codigos();

        DB::statement("UPDATE {$p}sec_permission SET deleted_at = NULL, updated_at = now() WHERE code IN ({$codigos})");
        DB::statement("UPDATE {$p}sec_role_permission SET deleted_at = NULL, updated_at = now() WHERE deleted_at IS NOT NULL AND id_permission IN (SELECT id FROM {$p}sec_permission WHERE code IN ({$codigos}))");
    }

    private function codigos(): string
    {
        return implode(', ', array_map(fn (string $codigo): string => DB::getPdo()->quote($codigo), self::PERMISOS));
    }
};
