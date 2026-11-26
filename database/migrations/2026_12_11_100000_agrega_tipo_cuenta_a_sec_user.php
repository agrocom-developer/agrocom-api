<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cuenta (ADR 0046, punto 1; plan de cuentas, fase A2): `sec_user.tipo_cuenta`
 * vale `panel` (personal de Agrocom), `cliente` o `proveedor`. Reemplaza a `type`
 * (`interno` | `cliente`) y a `contrato_id`, que quedan sin uso y se eliminan en la
 * reconsolidación final del esquema.
 *
 * Las cuentas existentes se clasifican por lo que ya son, en este orden:
 *
 * 1. `panel`: tiene un rol vivo de ámbito `panel` (administrador u operador) o no
 *    tiene ningún rol de app. Una cuenta con rol de panel y de app a la vez queda
 *    `panel`: los roles de app de una cuenta de panel no se usan.
 * 2. `proveedor`: tiene el rol `proveedor` o `piloto`.
 * 3. `cliente`: tiene el rol `cliente`, o es una cuenta de portal heredada
 *    (`type = cliente`, sin rol).
 *
 * El valor por defecto es `panel`: una cuenta creada sin indicar tipo no recibe
 * token de dispositivo (falla cerrada hacia las apps) y su acceso al panel sigue
 * dependiendo de los permisos de su rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('tipo_cuenta', 10)->default('panel')->after('type');
            $table->index(['tipo_cuenta'], 'sec_user_tipo_cuenta_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}sec_user ADD CONSTRAINT {$p}sec_user_tipo_cuenta_chk CHECK (tipo_cuenta IN ('panel', 'cliente', 'proveedor'))");
        }

        $this->clasificarCuentasExistentes();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $p = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$p}sec_user DROP CONSTRAINT IF EXISTS {$p}sec_user_tipo_cuenta_chk");
        }

        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropIndex('sec_user_tipo_cuenta_index');
            $table->dropColumn('tipo_cuenta');
        });
    }

    private function clasificarCuentasExistentes(): void
    {
        if (! Schema::hasTable('sec_role') || ! Schema::hasTable('sec_user_role') || ! DB::table('sec_user')->exists()) {
            return;
        }

        $rolesPorCuenta = DB::table('sec_user_role as ur')
            ->join('sec_role as r', 'r.id', '=', 'ur.id_role')
            ->whereNull('ur.deleted_at')
            ->whereNull('r.deleted_at')
            ->get(['ur.id_user', 'r.name'])
            ->groupBy('id_user')
            ->map(fn ($filas) => $filas->pluck('name')->all());

        foreach (DB::table('sec_user')->get(['id', 'type']) as $cuenta) {
            $roles = $rolesPorCuenta->get($cuenta->id, []);

            $tipo = match (true) {
                array_intersect($roles, ['administrador', 'operador']) !== [] => 'panel',
                array_intersect($roles, ['proveedor', 'piloto']) !== [] => 'proveedor',
                in_array('cliente', $roles, true) || $cuenta->type === 'cliente' => 'cliente',
                default => 'panel',
            };

            if ($tipo !== 'panel') {
                DB::table('sec_user')->where('id', $cuenta->id)->update(['tipo_cuenta' => $tipo]);
            }
        }
    }
};
