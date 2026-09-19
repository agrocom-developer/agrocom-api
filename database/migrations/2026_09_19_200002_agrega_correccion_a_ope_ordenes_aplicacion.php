<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0022, adenda del 19/9/2026: una orden ya `vigente` o `pausada` puede
 * corregir los datos de la aplicación (tipo, insumo, dosis, equipos, contacto,
 * fecha, observaciones) con un motivo obligatorio. Deja en la propia orden la
 * última corrección — `motivo_correccion` y `corregida_at` — y el historial
 * completo, con los valores antes y después, en la bitácora de auditoría
 * (`RegistraBitacora`, invariante 9): mismo criterio que `motivo_pausa` /
 * `pausada_at`, que guardan la última pausa y dejan el resto a la bitácora.
 * Nulas en una orden que nunca se corrigió después de emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->text('motivo_correccion')->nullable();
            $table->dateTime('corregida_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn(['motivo_correccion', 'corregida_at']);
        });
    }
};
