<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso `solicitudes.cliente.aprobar` (ADR 0046, punto 11; plan de cuentas §7): aprobar o rechazar a un
 * cliente pendiente desde el panel. Es un permiso asignable del panel: lo tiene el administrador y el
 * administrador puede marcarlo en la matriz del operador. `SeguridadSeeder` lo siembra en una instalación
 * nueva; esta migración lo crea en una base que ya tiene el catálogo y se lo da al rol `administrador`, que
 * tiene todo el panel, sin esperar a que se vuelva a sembrar.
 */
return new class extends Migration
{
    private const CODIGO = 'solicitudes.cliente.aprobar';

    public function up(): void
    {
        if (! Schema::hasTable('sec_permission') || ! DB::table('sec_permission')->exists()) {
            return;
        }

        $ahora = now();

        $permiso = DB::table('sec_permission')->where('code', self::CODIGO)->first();
        $permisoId = $permiso !== null
            ? (int) $permiso->id
            : (int) DB::table('sec_permission')->insertGetId([
                'code' => self::CODIGO,
                'description' => 'Aprobar o rechazar a un cliente pendiente, con motivo en el rechazo',
                'ambito' => 'panel',
                'state' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

        if ($permiso !== null && $permiso->deleted_at !== null) {
            DB::table('sec_permission')->where('id', $permisoId)->update(['deleted_at' => null, 'updated_at' => $ahora]);
        }

        $administrador = DB::table('sec_role')->where('name', 'administrador')->whereNull('deleted_at')->value('id');

        if ($administrador === null) {
            return;
        }

        $otorgamiento = DB::table('sec_role_permission')->where('id_role', $administrador)->where('id_permission', $permisoId)->first();

        if ($otorgamiento === null) {
            DB::table('sec_role_permission')->insert(['id_role' => $administrador, 'id_permission' => $permisoId, 'created_at' => $ahora, 'updated_at' => $ahora]);
        } elseif ($otorgamiento->deleted_at !== null) {
            DB::table('sec_role_permission')->where('id', $otorgamiento->id)->update(['deleted_at' => null, 'updated_at' => $ahora]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sec_permission')) {
            return;
        }

        $permisoId = DB::table('sec_permission')->where('code', self::CODIGO)->value('id');

        if ($permisoId === null) {
            return;
        }

        $ahora = now();

        DB::table('sec_role_permission')->where('id_permission', $permisoId)->whereNull('deleted_at')->update(['deleted_at' => $ahora, 'updated_at' => $ahora]);
        DB::table('sec_permission')->where('id', $permisoId)->update(['deleted_at' => $ahora, 'updated_at' => $ahora]);
    }
};
