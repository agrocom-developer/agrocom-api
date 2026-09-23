<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sec_vistas_como` (tarea 140): un renglón por cada vez que un administrador
 * de la plataforma mira el panel o el portal como otro usuario. Es la fuente de
 * la bitácora de esa capacidad (invariante 9 de CLAUDE.md): el modelo lleva
 * `RegistraBitacora`, así que la entrada queda como un `creado` y la salida —
 * `finalizada_at` y `motivo_fin` — como un `actualizado`, con el actor real (el
 * administrador) en `plt_bitacoras.user_id`.
 *
 * - `admin_id`: quien mira DE VERDAD. Nunca cambia a lo largo de la vista.
 * - `usuario_id`: la cuenta observada (interna o de portal).
 * - `tipo`: `interno` o `cliente`; coincide con el guard bajo el que se ve.
 * - `rol_id`: el rol activo bajo el que se ve una cuenta interna; nulo en una
 *   cuenta de portal, que no tiene roles (ADR 0004).
 * - `iniciada_at` / `finalizada_at`: cuándo entró y cuándo volvió; nula la
 *   segunda mientras la vista sigue abierta.
 * - `motivo_fin`: cómo terminó (`manual` o `invalidada`). Una vista que muere con
 *   la sesión no lo tiene: no hay request que la cierre.
 *
 * Migración `create` nueva (ADR 0024): la tabla no existía en ninguna base.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sec_vistas_como')) {
            return;
        }

        Schema::create('sec_vistas_como', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->foreignId('rol_id')->nullable()->constrained('sec_role')->restrictOnDelete();
            $table->dateTime('iniciada_at');
            $table->dateTime('finalizada_at')->nullable();
            $table->string('motivo_fin', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['admin_id'], 'sec_vistas_como_admin_id_index');
            $table->index(['usuario_id'], 'sec_vistas_como_usuario_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_vistas_como
                ADD CONSTRAINT {$prefijo}sec_vistas_como_tipo_chk CHECK (tipo IN ('interno', 'cliente'))
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_vistas_como
                ADD CONSTRAINT {$prefijo}sec_vistas_como_motivo_fin_chk CHECK (motivo_fin IN ('manual', 'invalidada'))
            SQL);

            // Una vista cerrada dice cuándo y por qué; una abierta no dice ninguna de las dos.
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_vistas_como
                ADD CONSTRAINT {$prefijo}sec_vistas_como_cierre_chk CHECK ((finalizada_at IS NULL) = (motivo_fin IS NULL))
            SQL);

            // Una cuenta interna se ve bajo un rol; una de portal no tiene ninguno.
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_vistas_como
                ADD CONSTRAINT {$prefijo}sec_vistas_como_rol_chk CHECK ((tipo = 'interno') = (rol_id IS NOT NULL))
            SQL);

            // Nadie se ve "como sí mismo": no aporta nada y confunde la bitácora.
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sec_vistas_como
                ADD CONSTRAINT {$prefijo}sec_vistas_como_distinto_chk CHECK (admin_id <> usuario_id)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_vistas_como');
    }
};
