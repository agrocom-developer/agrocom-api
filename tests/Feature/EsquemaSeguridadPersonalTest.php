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
    'sec_user_preferencia',
    'sec_token_dispositivo',
    'sec_datos_fiscales',
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

/*
 * HU-03 — `sec_token_dispositivo`. La tabla NO es la `personal_access_tokens`
 * de Sanctum: lleva prefijo de módulo (ADR 0011), auditoría y soft delete
 * (ADR 0007), y una FK real al usuario en vez de las columnas polimórficas
 * `tokenable_type`/`tokenable_id`.
 */
it('crea sec_token_dispositivo con FK real al usuario y al rol, sin columnas polimórficas', function () {
    expect(Schema::hasColumns('sec_token_dispositivo', [
        'user_id',
        'role_id',
        'uuid_dispositivo',
        'nombre_dispositivo',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('sec_token_dispositivo', 'tokenable_type'))->toBeFalse()
        ->and(Schema::hasColumn('sec_token_dispositivo', 'tokenable_id'))->toBeFalse();
});

it('no crea la tabla personal_access_tokens de Sanctum', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse();
});
