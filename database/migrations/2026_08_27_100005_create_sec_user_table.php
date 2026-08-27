<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — usuarios (ADR 0004, HU-01 diseño `modulos-roles` §5).
 *
 * `username`, no `login`: decisión posterior del usuario (auth por username +
 * password, nunca correo) que corrige el nombre de columna heredado de ADR 0004.
 *
 * `persona_id` → per_personas.id y `contrato_id` → com_contratos.id son FK de
 * base de datos, atributo plano a nivel Eloquent (sin belongsTo cross-módulo,
 * ADR 0011 extensión 26/8/2026 punto 5) — la migración solo garantiza
 * integridad referencial, la resolución de esa regla es responsabilidad del
 * modelo Eloquent que implementa `backend`.
 *
 * Dos invariantes de "un usuario, un login" (diseño §4), cada una con su
 * propio índice único parcial:
 * - persona_id: una persona operativa, una sola cuenta viva.
 * - username: único entre cuentas vivas, reusable tras una baja lógica
 *   (mismo patrón que com_clientes.nit).
 *
 * `language`, `profile_pic_url`, `initial_path` quedan fuera de HU-01
 * (ADR 0013, van con el tema de color en HU-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_user', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('username', 50);
            $table->string('password', 255);
            $table->string('type', 10)->default('interno');
            $table->foreignId('persona_id')
                ->nullable()
                ->constrained('per_personas')
                ->restrictOnDelete();
            $table->foreignId('contrato_id')
                ->nullable()
                ->constrained('com_contratos')
                ->restrictOnDelete();
            $table->boolean('state')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contrato_id');
        });

        $prefijo = DB::getTablePrefix();

        // Un único username vivo, reusable tras baja lógica (índice parcial, ADR 0001/0007).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_username_unico
            ON {$prefijo}sec_user (username)
            WHERE deleted_at IS NULL
        SQL);

        // Una persona operativa, una sola cuenta viva (índice parcial, diseño §4).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_persona_id_unico
            ON {$prefijo}sec_user (persona_id)
            WHERE persona_id IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_user
                ADD CONSTRAINT {$prefijo}sec_user_type_chk
                    CHECK (type IN ('interno', 'cliente')),
                ADD CONSTRAINT {$prefijo}sec_user_type_fk_chk
                    CHECK (
                        (type = 'interno' AND contrato_id IS NULL)
                        OR (type = 'cliente' AND persona_id IS NULL)
                    )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user');
    }
};
