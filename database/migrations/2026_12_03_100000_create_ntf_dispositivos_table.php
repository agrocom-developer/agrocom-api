<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ntf_dispositivos` (`negociacion-contrato.md` §7): el token de push que una
 * app registra al iniciar sesión y retira al cerrarla. Un token está activo en
 * una sola cuenta a la vez (índice único parcial): si el aparato cambia de
 * cuenta, el registro pasa a la nueva. El borrado es lógico: un token retirado
 * deja de recibir avisos pero queda el rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ntf_dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('token', 512);
            $table->string('plataforma', 10);
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['usuario_id'], 'ntf_dispositivos_usuario_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}ntf_dispositivos_token_unico ON {$prefijo}ntf_dispositivos (token) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}ntf_dispositivos ADD CONSTRAINT {$prefijo}ntf_dispositivos_plataforma_chk CHECK (plataforma IN ('android', 'ios'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ntf_dispositivos');
    }
};
