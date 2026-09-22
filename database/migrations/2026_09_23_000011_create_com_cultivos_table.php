<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_cultivos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000011_create_com_cultivos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_cultivos')) {
            return;
        }

        Schema::create('com_cultivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comun', 80);
            $table->boolean('activo')->default(true);
            $table->string('nombre_cientifico', 150)->nullable();
            $table->string('tipo_cultivo', 20)->nullable();
            $table->string('ciclo_vida', 20)->nullable();
            $table->text('notas_agronomicas')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_cultivos_nombre_comun_unico ON {$prefijo}com_cultivos USING btree (nombre_comun) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_cultivos
                ADD CONSTRAINT {$prefijo}com_cultivos_ciclo_vida_chk CHECK ((ciclo_vida IS NULL) OR ciclo_vida IN ('anual', 'bienal', 'perenne')),
                ADD CONSTRAINT {$prefijo}com_cultivos_tipo_cultivo_chk CHECK ((tipo_cultivo IS NULL) OR tipo_cultivo IN ('cereal', 'oleaginosa', 'leguminosa', 'forrajera', 'horticola', 'frutal', 'otro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_cultivos');
    }
};
