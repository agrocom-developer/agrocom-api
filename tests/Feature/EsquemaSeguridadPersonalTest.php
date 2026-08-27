<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/*
 * HU-01 — invariante 5/8 de CLAUDE.md: soft delete + columnas de auditoría
 * desde la primera migración, en todas las tablas nuevas de Personal y
 * Seguridad (mismo criterio que EsquemaNucleoComercialTest para TE-03).
 */

uses(RefreshDatabase::class);

dataset('tablas de Personal y Seguridad', [
    'per_bases',
    'per_personas',
    'sec_role',
    'sec_permission',
    'sec_user',
    'sec_user_role',
    'sec_role_permission',
]);

it('crea la tabla con soft delete y columnas de auditoría', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeTrue()
        ->and(Schema::hasColumns($tabla, [
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
})->with('tablas de Personal y Seguridad');
