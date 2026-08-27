<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — preferencia de panel por usuario (tema/idioma), HU-02
 * (ADR 0011, extensión 27/8/2026, puntos 7-8; ADR 0002 punto 4; ADR 0013
 * punto 2).
 *
 * Tabla satélite de `sec_user`, no columnas nuevas en esa tabla: mantiene
 * `sec_*` como RBAC puro (identidad, login, roles) separado de la
 * personalización de panel, que va a seguir creciendo. Explícitamente NO es
 * un módulo `Identidad` — ver ADR 0011 punto 7: dos columnas sin lógica de
 * negocio propia no ameritan `Contratos/`, `Aplicacion/`, `Dominio/` aparte.
 *
 * `idioma` sin CHECK todavía (ADR 0013: español es el único valor habilitado
 * en v1, validado en la capa de aplicación — `ActualizarPreferenciaUsuario`
 * — no en la base) para no tener que migrar de nuevo cuando se habilite un
 * segundo idioma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_user_preferencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('tema', 10)->default('claro');
            $table->string('idioma', 5)->default('es');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Una preferencia por usuario (índice parcial, mismo patrón que
        // sec_user_username_unico: libera el hueco si la fila se soft-deletea).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_preferencia_user_id_unico
            ON {$prefijo}sec_user_preferencia (user_id)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_user_preferencia
                ADD CONSTRAINT {$prefijo}sec_user_preferencia_tema_chk
                    CHECK (tema IN ('claro', 'oscuro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user_preferencia');
    }
};
