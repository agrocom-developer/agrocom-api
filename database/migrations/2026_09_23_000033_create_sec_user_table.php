<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_user` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000033_create_sec_user_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_user')) {
            return;
        }

        Schema::create('sec_user', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('username', 50);
            $table->string('password');
            $table->string('type', 10)->default('interno');
            $table->foreignId('persona_id')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('com_contratos')->restrictOnDelete();
            $table->boolean('state')->default(true);
            $table->string('email', 150)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contrato_id'], 'sec_user_contrato_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_email_unico ON {$prefijo}sec_user USING btree (email) WHERE (email IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_persona_id_unico ON {$prefijo}sec_user USING btree (persona_id) WHERE (persona_id IS NOT NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_username_unico ON {$prefijo}sec_user USING btree (username) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_user
                ADD CONSTRAINT {$prefijo}sec_user_type_chk CHECK (type IN ('interno', 'cliente')),
                ADD CONSTRAINT {$prefijo}sec_user_type_fk_chk CHECK (((type = 'interno') AND (contrato_id IS NULL)) OR ((type = 'cliente') AND (persona_id IS NULL)))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user');
    }
};
