<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `DROP TABLE com_contrato_ventanas`: decisión del dueño (16/9/2026) —
 * reemplazo completo, no coexistencia. El rango horario permitido para
 * fumigar deja de ser dato del contrato (N ventanas por contrato) y pasa a
 * ser dato de cada lote dentro del contrato (`com_contrato_lotes.hora_inicio`
 * / `hora_fin`, agregadas en la migración previa de esta misma tanda,
 * `2026_09_16_100003_agrega_horario_a_com_contrato_lotes_table.php`).
 * Verificado contra el compose real antes de esta migración: 0 filas en
 * `com_contrato_ventanas` (dato de prueba de esta misma sesión de trabajo,
 * no hay nada que se pierda).
 *
 * `down()` recrea la tabla completa con su forma actual (mismo criterio que
 * `2026_09_16_100002_elimina_com_contrato_alcances_table.php`), por si hace
 * falta revertir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('com_contrato_ventanas');
    }

    public function down(): void
    {
        Schema::create('com_contrato_ventanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('com_contratos')->restrictOnDelete();
            $table->time('hora_inicio');
            $table->time('hora_fin');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_contrato_ventanas_unicas
            ON {$prefijo}com_contrato_ventanas (contrato_id, hora_inicio, hora_fin)
            WHERE deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contrato_ventanas
                ADD CONSTRAINT {$prefijo}com_contrato_ventanas_horas_chk
                CHECK (hora_fin > hora_inicio)
            SQL);
        }
    }
};
