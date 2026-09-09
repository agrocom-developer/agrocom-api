<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions` es infraestructura de framework (driver de sesión = `database`,
 * `.env` `SESSION_DRIVER`), no una tabla de dominio: sin soft delete ni
 * columnas de auditoría (ADR 0007 aplica a modelos de dominio, no a esto).
 *
 * Reemplaza al `create_users_table` del esqueleto de Laravel (HU-01, diseño
 * `modulos-roles` §6): `users` se elimina sin reemplazo — el login sigue
 * siendo username/password (`sec_user`). `password_reset_tokens` también se
 * eliminó acá, pero SÍ vuelve más adelante: tarea 66 (ADR 0004, ampliación
 * 9/9/2026) agrega `sec_user.email` y un flujo real de recuperación por
 * correo — ver `2026_09_09_100005_create_password_reset_tokens_table`.
 *
 * `sessions.user_id` nunca tuvo FK real en el esqueleto de Laravel (es un
 * `unsignedBigInteger` nullable indexado, sin `constrained()`) — sigue sin
 * atarse a ninguna tabla de usuarios ahora que `sec_user` es la única fuente
 * de identidad; el guard de sesión completa esta columna en runtime con el
 * id de `SecUser`, no hace falta una migración aparte para eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
