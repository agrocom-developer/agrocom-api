<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `mez_mezclas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo MEZ.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000046_create_mez_mezclas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mez_mezclas')) {
            return;
        }

        Schema::create('mez_mezclas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->dateTime('hora');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['trabajo_id'], 'mez_mezclas_trabajo_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}mez_mezclas_uuid_cliente_unico ON {$prefijo}mez_mezclas USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_mezclas');
    }
};
