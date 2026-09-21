<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla "Estadías en hacienda" (módulo `Operaciones`): dónde se aloja la
 * cuadrilla durante la estadía — dentro de la hacienda, en el pueblo más
 * cercano, o acampando en la propiedad (pedido del dueño, 19/9/2026, junto
 * con el alta manual desde el panel). Ver `Dominio\TipoAlojamiento`.
 *
 * Nullable: un registro que llega por sync de una app de campo vieja (sin
 * este campo todavía en su payload) no lo trae — mismo criterio que
 * `vehiculo_id` en la migración original de esta tabla, un dato opcional que
 * no puede obligar a rechazar el resto del registro.
 *
 * Solo pgsql para el `CHECK`: SQLite (tests locales) no soporta `ADD
 * CONSTRAINT`, mismo criterio que
 * `2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->string('tipo_alojamiento', 20)->nullable()->after('vehiculo_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}ope_estadias_hacienda
            ADD CONSTRAINT {$prefijo}ope_estadias_hacienda_tipo_alojamiento_chk
                CHECK (tipo_alojamiento IS NULL OR tipo_alojamiento IN ('hacienda', 'pueblo', 'camping'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_estadias_hacienda
                DROP CONSTRAINT {$prefijo}ope_estadias_hacienda_tipo_alojamiento_chk
            SQL);
        }

        Schema::table('ope_estadias_hacienda', function (Blueprint $table) {
            $table->dropColumn('tipo_alojamiento');
        });
    }
};
