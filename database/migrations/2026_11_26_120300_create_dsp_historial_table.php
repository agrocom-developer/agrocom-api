<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `dsp_historial` (ADR 0007, especificación del marketplace §1.7): cada
 * transición de una disputa deja una fila con autor, rol, motivo y momento. Solo se
 * inserta. La primera fila (apertura) no tiene estado anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disputa_id')->constrained('dsp_disputas')->restrictOnDelete();
            $table->string('estado_anterior', 12)->nullable();
            $table->string('estado_nuevo', 12);
            $table->unsignedBigInteger('actor_usuario_id')->nullable();
            $table->string('actor_rol', 14);
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['disputa_id', 'id'], 'dsp_historial_disputa_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_historial');
    }
};
