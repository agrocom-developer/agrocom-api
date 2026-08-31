<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — token de acceso por dispositivo para las apps de campo
 * (HU-03; ADR 0008: `routes/api.php` sirve a `agrocom-field` con Sanctum,
 * token por dispositivo).
 *
 * NO se publica la migración `personal_access_tokens` de Sanctum: esa tabla
 * no lleva prefijo de módulo (ADR 0011) ni las columnas de auditoría y borrado
 * lógico que ADR 0007 exige a todo registro de dominio — y un token de acceso
 * es justamente lo que más interesa auditar (quién lo emitió, quién lo
 * revocó y cuándo). Tabla propia dentro de `Seguridad`, patrón satélite de
 * `sec_user` ya usado por `sec_user_preferencia`.
 *
 * Tampoco lleva las columnas polimórficas `tokenable_type`/`tokenable_id` del
 * esquema de Sanctum: acá el único portador posible de un token es una cuenta
 * `sec_user`, así que la referencia es una FK real (integridad en la base,
 * ADR 0011) en vez de un par de columnas sin constraint.
 *
 * `role_id` es el rol activo del dispositivo: el token ES la sesión de la app
 * de campo, y una sesión opera bajo un único rol activo, nunca la unión de
 * los roles del usuario (invariante 10 de CLAUDE.md). Se elige al emitir el
 * token y se revalida contra la base en cada request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_token_dispositivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('sec_role')->restrictOnDelete();

            // Lo genera el dispositivo, igual que `uuid_cliente` en los
            // registros que nacen en campo (invariante 1): reintentar el
            // login desde el mismo teléfono no acumula tokens vivos.
            $table->string('uuid_dispositivo', 36);
            $table->string('nombre_dispositivo', 80)->nullable();

            // Contrato de Sanctum: `token` guarda el sha256 del valor en
            // claro (que solo existe en la respuesta de emisión y en el
            // dispositivo), nunca el token en sí.
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Un solo token vivo por usuario y dispositivo. Índice parcial (mismo
        // patrón que sec_user_preferencia_user_id_unico): revocar es un
        // borrado lógico, y el hueco tiene que quedar libre para que el mismo
        // teléfono pueda volver a loguearse.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}sec_token_dispositivo_unico
            ON {$prefijo}sec_token_dispositivo (user_id, uuid_dispositivo)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_token_dispositivo');
    }
};
