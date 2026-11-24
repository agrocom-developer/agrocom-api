<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `flo_historial_estados` (módulo Flota): historial de aprobación de cada
 * dron, con la misma forma que `prv_historial_estados` (cada módulo escribe
 * solo sus tablas, ADR 0003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flo_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dron_id')->constrained('flo_drones')->restrictOnDelete();
            $table->string('estado_anterior', 12);
            $table->string('estado_nuevo', 12);
            $table->string('accion', 12);
            $table->string('origen', 14);
            $table->foreignId('actor_usuario_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dron_id', 'id'], 'flo_historial_estados_dron_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}flo_historial_estados
                ADD CONSTRAINT {$prefijo}flo_historial_estados_origen_chk CHECK (origen IN ('administrador', 'solicitante', 'sistema'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flo_historial_estados');
    }
};
