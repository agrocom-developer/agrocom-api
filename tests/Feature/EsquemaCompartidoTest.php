<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/*
 * Tarea 78 (HU-55) — invariante 8 de CLAUDE.md: soft delete + columnas de
 * auditoría en `plt_configuraciones`. Hallazgo del verificador independiente:
 * es la primera tabla de datos reales que vive en `Compartido/` (antes solo
 * alojaba `plt_bitacoras`, que a propósito NO las lleva — ver el docblock de
 * su migración), y `Compartido/` queda excluido de la regla arquitectónica
 * genérica de `tests/Unit/ArquitecturaModulosTest.php`. Mismo criterio que
 * `EsquemaSeguridadPersonalTest.php` para HU-01.
 */

uses(RefreshDatabase::class);

it('crea plt_configuraciones con soft delete y columnas de auditoría', function () {
    expect(Schema::hasTable('plt_configuraciones'))->toBeTrue()
        ->and(Schema::hasColumns('plt_configuraciones', [
            'clave',
            'valor',
            'grupo',
            'descripcion',
            'es_secreto',
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});
