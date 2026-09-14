<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-83 (tarea 98): separa "con cuántos ciclos entró la batería al
 * catálogo" de "cuántos lleva hoy". `ciclos_inicial` es el punto de partida,
 * fijado al alta e inmutable después (`ActualizarBateria` no lo toca);
 * `ciclos_acumulados` sigue siendo el total corriente, editable con el uso
 * igual que antes (eso lo corrige recién la tarea 102/HU-87, que depende de
 * que esta migración esté integrada).
 *
 * Suma también `mantenimiento` al `CHECK` de `estado`: baja temporal de
 * servicio, distinta de `retirada` (definitiva). `EstadoBateria` sigue sin
 * ser una máquina de estados de negocio (ver su docblock) — agregar un caso
 * no gobierna ninguna transición (CLAUDE.md invariante 7 no aplica acá).
 *
 * Solo pgsql para los `CHECK`: SQLite (tests locales) no soporta `ADD
 * CONSTRAINT`, mismo criterio que
 * `2026_09_13_100001_add_pausado_a_com_contratos_estado_chk.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_baterias', function (Blueprint $table) {
            $table->unsignedInteger('ciclos_inicial')->default(0)->after('identificador');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_baterias
            ADD CONSTRAINT {$prefijo}man_baterias_ciclos_inicial_chk
                CHECK (ciclos_inicial >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_baterias
            DROP CONSTRAINT {$prefijo}man_baterias_estado_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}man_baterias
            ADD CONSTRAINT {$prefijo}man_baterias_estado_chk
                CHECK (estado IN ('activa', 'retirada', 'mantenimiento'))
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_baterias
                DROP CONSTRAINT {$prefijo}man_baterias_estado_chk
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_baterias
                ADD CONSTRAINT {$prefijo}man_baterias_estado_chk
                    CHECK (estado IN ('activa', 'retirada'))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}man_baterias
                DROP CONSTRAINT {$prefijo}man_baterias_ciclos_inicial_chk
            SQL);
        }

        Schema::table('man_baterias', function (Blueprint $table) {
            $table->dropColumn('ciclos_inicial');
        });
    }
};
