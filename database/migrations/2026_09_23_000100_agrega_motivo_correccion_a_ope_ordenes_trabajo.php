<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea 127: la Orden de Trabajo se puede editar. Sobre una tanda que ya
 * tiene algún trabajo cerrado, la corrección exige un motivo — mismo patrón
 * que `ope_ordenes_aplicacion.motivo_correccion`/`corregida_at` (ADR 0022).
 * Migración `add` (ADR 0024): la tabla ya está consolidada, esto no se
 * reconsolida acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->text('motivo_correccion')->nullable();
            $table->dateTime('corregida_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn(['motivo_correccion', 'corregida_at']);
        });
    }
};
