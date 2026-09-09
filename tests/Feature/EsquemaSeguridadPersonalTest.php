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

/*
 * Tarea 66 — ADR 0004, ampliación 9/9/2026: el correo vive en la CUENTA
 * (`sec_user.email`), no en `per_personas` (no tiene) ni se lee solo de
 * `com_cliente_contactos` (varios contactos por cliente). Nullable: no toda
 * cuenta declara correo. La unicidad entre cuentas vivas (índice parcial,
 * igual patrón que `username`) se prueba por comportamiento en
 * AsignarRolesUsuarioTest/GestionUsuariosPanelTest, no por introspección de
 * esquema — SQLite no expone el `WHERE` de un índice parcial vía `Schema`.
 */
it('agrega email nullable a sec_user', function () {
    expect(Schema::hasColumn('sec_user', 'email'))->toBeTrue()
        ->and(Schema::getColumnType('sec_user', 'email'))->toBe('varchar');
});

/*
 * Tarea 63 — instante absoluto (`created_at`, ya UTC) + zona IANA del actor
 * (`zona_horaria`), nullable en ambas tablas: lo ya escrito antes de esta
 * migración no se reescribe, y una mutación sin actor con preferencia
 * resuelta (seeders, comandos) tampoco la inventa.
 */
it('agrega zona_horaria (IANA, nullable) a sec_user_preferencia y a plt_bitacoras', function () {
    expect(Schema::hasColumn('sec_user_preferencia', 'zona_horaria'))->toBeTrue()
        ->and(Schema::hasColumn('plt_bitacoras', 'zona_horaria'))->toBeTrue();
});
