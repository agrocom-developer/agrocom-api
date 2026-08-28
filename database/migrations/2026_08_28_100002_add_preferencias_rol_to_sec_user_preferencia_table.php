<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — pantalla de selección de rol (quinta vuelta, maqueta
 * 5c): dos preferencias de rol por usuario, en la tabla satélite de
 * preferencias — NUNCA columnas en `sec_user` ni el rol activo en sí
 * (ADR 0004: el rol activo sigue siendo estado de sesión; esto son
 * preferencias de conveniencia alrededor de esa elección):
 *
 * - `rol_preferido_id` ("Entrar siempre con este rol"): si está fijado y el
 *   rol sigue vivo para el usuario, el login/middleware lo activa solo y la
 *   pantalla de selección se saltea — reaparece solo al cambiar de rol
 *   explícitamente desde el menú. NULL = preguntar en cada login (default).
 * - `ultimo_rol_id` (badge "ÚLTIMO USADO" de la pantalla): último rol que
 *   quedó activo en cualquier sesión, registrado por `ElegirRolActivo` (el
 *   único punto que activa roles). Puramente informativo, nunca gobierna
 *   permisos.
 *
 * Ambos con RESTRICT (mismo criterio que `user_id`): `sec_role` se baja por
 * soft delete, no físicamente, y la revalidación de "rol vivo" al usarlos es
 * responsabilidad de la capa de aplicación — un id apuntando a un rol
 * soft-deleteado simplemente se ignora y se vuelve a preguntar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user_preferencia', function (Blueprint $table) {
            $table->foreignId('rol_preferido_id')->nullable()->after('idioma')->constrained('sec_role')->restrictOnDelete();
            $table->foreignId('ultimo_rol_id')->nullable()->after('rol_preferido_id')->constrained('sec_role')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sec_user_preferencia', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rol_preferido_id');
            $table->dropConstrainedForeignId('ultimo_rol_id');
        });
    }
};
