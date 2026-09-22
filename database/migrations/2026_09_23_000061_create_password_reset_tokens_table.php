<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `password_reset_tokens` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo PASSWORD.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000061_create_password_reset_tokens_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens')) {
            return;
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email');
            $table->string('token');
            $table->dateTime('created_at')->nullable();
            $table->primary(['email'], 'password_reset_tokens_pkey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
