<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Retrofit de HU-01 (tarea 30) — created_by/updated_by de toda tabla de
 * dominio ahora tienen FK real a sec_user.id (nullOnDelete). Se verifica en
 * tres tablas representativas de módulos distintos, standalone (sin
 * dependencias de FK propias), que insertar con un id de sec_user
 * inexistente es rechazado por la base — mismo patrón que
 * EsquemaOperacionesTest.
 */

uses(RefreshDatabase::class);

it('rechaza un cliente con created_by apuntando a un sec_user inexistente', function () {
    DB::table('com_clientes')->insert([
        'razon_social' => 'Cliente de prueba',
        'created_by' => 999999,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rechaza un dron con created_by apuntando a un sec_user inexistente', function () {
    DB::table('ope_drones')->insert([
        'identificador' => 'DRN-TEST',
        'created_by' => 999999,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rechaza un rol con updated_by apuntando a un sec_user inexistente', function () {
    DB::table('sec_role')->insert([
        'name' => 'rol_test',
        'description' => 'Rol de prueba',
        'updated_by' => 999999,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
