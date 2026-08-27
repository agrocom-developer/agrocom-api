<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — pivote usuario↔rol, multi-rol con un único login
 * (ADR 0004, HU-01 diseño `modulos-roles` §5).
 *
 * Modelo Eloquent propio, NO pivote implícito de Laravel: revocar un rol es
 * un soft delete de la fila (auditoría de quién asignó/revocó), no un
 * DELETE — un pivote `belongsToMany` sin `->using()` no pasa por
 * ModeloDominio y perdería ese rastro.
 *
 * `id_user`/`id_role` (prefijo, no sufijo): inconsistencia heredada de
 * ADR 0004 frente a `persona_id`/`contrato_id` de sec_user — se preserva
 * tal cual, no se normaliza (diseño §5, nota de naming).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('id_role')->constrained('sec_role')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_role');
        });

        $prefijo = DB::getTablePrefix();

        // Sin rol duplicado vivo por usuario; revocar y reasignar el mismo rol
        // después queda permitido (índice parcial, ADR 0001).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_user_role_unico
            ON {$prefijo}sec_user_role (id_user, id_role)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_user_role');
    }
};
