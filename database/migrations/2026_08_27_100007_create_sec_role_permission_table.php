<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — pivote rol↔permiso (ADR 0004, HU-01 diseño
 * `modulos-roles` §5).
 *
 * Modelo Eloquent propio, NO pivote implícito de Laravel: quitar un permiso
 * de un rol es un soft delete de la fila (auditoría de quién lo otorgó/quitó),
 * mismo criterio que sec_user_role.
 *
 * `id_role`/`id_permission` (prefijo, no sufijo): misma inconsistencia
 * heredada de ADR 0004 que sec_user_role — se preserva tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_role')->constrained('sec_role')->restrictOnDelete();
            $table->foreignId('id_permission')->constrained('sec_permission')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_permission');
        });

        $prefijo = DB::getTablePrefix();

        // Sin permiso duplicado vivo por rol; quitar y reasignar el mismo
        // permiso después queda permitido (índice parcial, ADR 0001).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_role_permission_unico
            ON {$prefijo}sec_role_permission (id_role, id_permission)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_role_permission');
    }
};
