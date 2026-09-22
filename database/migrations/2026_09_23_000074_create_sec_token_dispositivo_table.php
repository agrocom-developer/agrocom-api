<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_token_dispositivo` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo SEC.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000074_create_sec_token_dispositivo_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_token_dispositivo')) {
            return;
        }

        Schema::create('sec_token_dispositivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('sec_role')->restrictOnDelete();
            $table->string('uuid_dispositivo', 36);
            $table->string('nombre_dispositivo', 80)->nullable();
            $table->string('token', 64);
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['token'], 'sec_token_dispositivo_token_unique');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_token_dispositivo_unico ON {$prefijo}sec_token_dispositivo USING btree (user_id, uuid_dispositivo) WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_token_dispositivo');
    }
};
