<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla estándar de Laravel para el broker de reset de contraseña (tarea 66;
 * ADR 0004, ampliación 9/9/2026) — eliminada sin reemplazo en la migración
 * original de `sessions` ("no hay flujo de recuperación por email en este
 * sistema"), decisión que esta tarea revierte explícitamente.
 *
 * Una sola tabla física para los DOS brokers (`interno`/`cliente`,
 * `config/auth.php`): `sec_user.email` es único entre cuentas VIVAS sin
 * importar el `type`, así que un email nunca puede pertenecer a la vez a una
 * cuenta interna y a una de portal — no hace falta separar la tabla por
 * broker para que un token no cruce de guard.
 *
 * Sin soft delete ni columnas de auditoría (ADR 0007 aplica a modelos de
 * dominio, no a esto) — mismo criterio que `sessions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
