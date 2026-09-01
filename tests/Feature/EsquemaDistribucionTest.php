<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/*
 * HU-20 — invariante 6/8 de CLAUDE.md: soft delete y columnas de auditoría
 * desde la primera migración del módulo Distribucion (mismo criterio que
 * EsquemaSeguridadPersonalTest para HU-01).
 */

uses(RefreshDatabase::class);

it('crea dis_versiones_apk con soft delete y columnas de auditoría', function () {
    expect(Schema::hasTable('dis_versiones_apk'))->toBeTrue()
        ->and(Schema::hasColumns('dis_versiones_apk', [
            'version',
            'version_code',
            'ruta_apk',
            'estado',
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});
