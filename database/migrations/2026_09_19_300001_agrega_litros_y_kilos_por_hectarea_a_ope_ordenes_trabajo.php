<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido del dueño del 19/9/2026: cada Orden de Trabajo (tanda) registra con
 * qué caudal salió — litros por hectárea si la orden de aplicación es de
 * insumo líquido, kilos por hectárea si es de sólido. Van en la cabecera de la
 * tanda, junto al Ph: son compartidos por todos sus equipos.
 *
 * `litros_ha` ya existe en `ope_ordenes_aplicacion` como lo PEDIDO al emitir
 * la orden; este es lo que efectivamente se cargó para la tanda (el formulario
 * lo propone igual al de la orden y se puede ajustar). Ambas nulas: una tanda
 * de líquido no lleva kilos, una de sólido no lleva litros, y las tandas
 * anteriores a esta migración no tienen ninguna de las dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->decimal('litros_ha', 8, 2)->nullable();
            $table->decimal('kilos_ha', 8, 2)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_litros_ha_chk
                    CHECK (litros_ha IS NULL OR litros_ha > 0),
                ADD CONSTRAINT {$prefijo}ope_ordenes_trabajo_kilos_ha_chk
                    CHECK (kilos_ha IS NULL OR kilos_ha > 0)
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_trabajo
                DROP CONSTRAINT IF EXISTS {$prefijo}ope_ordenes_trabajo_litros_ha_chk,
                DROP CONSTRAINT IF EXISTS {$prefijo}ope_ordenes_trabajo_kilos_ha_chk
            SQL);
        }

        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn(['litros_ha', 'kilos_ha']);
        });
    }
};
