<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_solicitud_historial` (especificación del marketplace §4.2): cada
 * transición de una solicitud de servicio deja una fila con autor, origen,
 * motivo y momento. Solo se inserta. La primera fila (creación) no tiene
 * estado anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_solicitud_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->string('estado_anterior', 24)->nullable();
            $table->string('estado_nuevo', 24);
            $table->string('origen', 14);
            $table->foreignId('actor_usuario_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['solicitud_id', 'id'], 'sol_solicitud_historial_solicitud_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_solicitud_historial ADD CONSTRAINT {$prefijo}sol_solicitud_historial_origen_chk CHECK (origen IN ('cliente', 'proveedor', 'administrador', 'sistema'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_solicitud_historial');
    }
};
