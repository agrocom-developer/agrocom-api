<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prv_historial_estados` (especificación del marketplace §1, principio 7):
 * cada transición de aprobación de un proveedor, piloto o auxiliar deja una
 * fila con autor, motivo y momento. Solo se inserta. `origen` distingue la
 * decisión del administrador, el reenvío del solicitante y la suspensión
 * automática por documento vencido (que se levanta sola al cargar uno vigente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prv_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->string('sujeto_tipo', 12);
            $table->unsignedBigInteger('sujeto_id');
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
            $table->index(['sujeto_tipo', 'sujeto_id', 'id'], 'prv_historial_estados_sujeto_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}prv_historial_estados
                ADD CONSTRAINT {$prefijo}prv_historial_estados_sujeto_chk CHECK (sujeto_tipo IN ('proveedor', 'piloto', 'auxiliar')),
                ADD CONSTRAINT {$prefijo}prv_historial_estados_origen_chk CHECK (origen IN ('administrador', 'solicitante', 'sistema'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prv_historial_estados');
    }
};
