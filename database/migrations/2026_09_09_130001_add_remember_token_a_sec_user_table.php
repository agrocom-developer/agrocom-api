<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — "Recordarme" del login (ADR 0004, ampliación 9/9/2026).
 *
 * La pantalla de ingreso ofrecía la casilla "Recordarme" desde HU-02, pero
 * `sec_user` no tenía dónde guardar el token de sesión persistente y
 * `SecUser::getRememberTokenName()` devolvía cadena vacía para que el guard
 * ni la buscara: marcar la casilla no hacía absolutamente nada y la sesión
 * moría con la cookie del navegador. Esta columna es lo que faltaba.
 *
 * Nullable y sin índice: Laravel la escribe al autenticar con `$remember` y
 * la lee siempre por `id` (la cookie `remember_web_*` trae identificador +
 * token), nunca busca por el token solo. 100 caracteres es el largo que
 * asume `Illuminate\Auth\SessionGuard` (`Str::random(60)`), y el mismo del
 * `rememberToken()` de Laravel.
 *
 * Nunca entra a la bitácora: `BitacoraObserver::COLUMNAS_SENSIBLES` ya
 * incluye `remember_token` junto con `password` y `token`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('remember_token', 100)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
