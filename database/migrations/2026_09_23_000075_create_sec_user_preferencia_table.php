<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_user_preferencia` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000075_create_sec_user_preferencia_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_user_preferencia')) {
            return;
        }

        Schema::create('sec_user_preferencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('tema', 10)->default('claro');
            $table->string('idioma', 5)->default('es');
            $table->foreignId('rol_preferido_id')->nullable()->constrained('sec_role')->restrictOnDelete();
            $table->foreignId('ultimo_rol_id')->nullable()->constrained('sec_role')->restrictOnDelete();
            $table->string('zona_horaria', 64)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_preferencia_user_id_unico ON {$prefijo}sec_user_preferencia USING btree (user_id) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_user_preferencia
                ADD CONSTRAINT {$prefijo}sec_user_preferencia_tema_chk CHECK (tema IN ('claro', 'oscuro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user_preferencia');
    }
};
