<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ntf_alertas_vistas` — extensión del ADR 0025 (tarea 141, seguimiento).
 * Módulo NTF (prefijo `ntf_`, ADR 0011).
 *
 * Qué hizo cada cuenta con cada alerta técnica de `ope_alertas` que vio en la
 * campana: si la abrió (`leida_en`) y si la limpió (`limpiada_en`). Las
 * alertas tienen un ciclo de vida compartido (`pendiente → atendida`) que
 * atiende quien tiene el permiso; esto NO lo toca: es solo el estado de
 * lectura de UNA cuenta, para que abrirla en la campana la deje leída ahí sin
 * declarar atendida la alerta para todos.
 *
 * - Una fila por cuenta y alerta: `UNIQUE (usuario_id, alerta_id)`, NO parcial
 *   (mismo criterio que `ntf_notificaciones`). Abrir dos veces es idempotente.
 * - `alerta_id` no lleva FK: apunta a una tabla de otro módulo y el estado
 *   tiene que sobrevivir a que la alerta se dé de baja.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('ntf_alertas_vistas');
    }
};
