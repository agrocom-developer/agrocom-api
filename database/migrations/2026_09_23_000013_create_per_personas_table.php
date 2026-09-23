<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `per_personas` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PER.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000013_create_per_personas_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('per_personas')) {
            return;
        }

        Schema::create('per_personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('rol', 30);
            $table->foreignId('base_id')->nullable()->constrained('per_bases')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->string('nombres', 80)->nullable();
            $table->string('apellido_paterno', 80)->nullable();
            $table->string('apellido_materno', 80)->nullable();
            $table->string('ci', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['base_id'], 'per_personas_base_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}per_personas_ci_unico ON {$prefijo}per_personas USING btree (ci) WHERE (deleted_at IS NULL) AND (ci IS NOT NULL)
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_personas
                ADD CONSTRAINT {$prefijo}per_personas_rol_chk CHECK (rol IN ('piloto', 'auxiliar', 'jefe_campo', 'encargado_operaciones', 'dueno'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_personas');
    }
};
