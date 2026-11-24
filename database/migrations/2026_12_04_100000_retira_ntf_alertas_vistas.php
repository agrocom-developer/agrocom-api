<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina `ntf_alertas_vistas`: el estado de lectura por cuenta de las alertas
 * técnicas de Operaciones. La campana ya no lista alertas (solo avisos del motor,
 * `ntf_notificaciones`) y ningún código lee ni escribe la tabla.
 *
 * Es destructiva a propósito: no hay datos que conservar. `down()` recrea la
 * estructura vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ntf_alertas_vistas');
    }

    public function down(): void
    {
        if (Schema::hasTable('ntf_alertas_vistas')) {
            return;
        }

        Schema::create('ntf_alertas_vistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->unsignedBigInteger('alerta_id');
            $table->dateTime('leida_en')->nullable();
            $table->dateTime('limpiada_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['usuario_id', 'alerta_id'], 'ntf_alertas_vistas_cuenta_unico');
        });
    }
};
