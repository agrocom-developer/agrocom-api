<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — correo de la cuenta (tarea 66; ADR 0004, ampliación
 * 9/9/2026). El correo vive en `sec_user.email`, no en `per_personas` ni se
 * lee solo de `com_cliente_contactos`: el reset de contraseña es de la
 * CUENTA, no de la persona operativa ni del contacto comercial — una persona
 * puede no tener cuenta y un cliente tiene varios contactos con email.
 *
 * Nullable (no toda cuenta declara correo) y único entre cuentas vivas por
 * índice parcial, mismo patrón que `sec_user_username_unico`: una cuenta
 * borrada libera su correo para que otra lo reuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_user', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('username');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_email_unico
            ON {$prefijo}sec_user (email)
            WHERE email IS NOT NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}sec_user_email_unico");

        Schema::table('sec_user', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
