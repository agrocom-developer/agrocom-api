<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `plt_configuraciones` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PLT.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000067_create_plt_configuraciones_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plt_configuraciones')) {
            return;
        }

        Schema::create('plt_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 150);
            $table->text('valor')->nullable();
            $table->string('grupo', 30);
            $table->string('descripcion')->nullable();
            $table->boolean('es_secreto')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}plt_configuraciones_clave_unico ON {$prefijo}plt_configuraciones USING btree (clave) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}plt_configuraciones
                ADD CONSTRAINT {$prefijo}plt_configuraciones_grupo_chk CHECK (grupo IN ('mapas', 'correo', 'integraciones'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_configuraciones');
    }
};
