<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo y momento de la suspensión de un cliente desde el panel: un cliente
 * suspendido no puede solicitar servicios y el administrador deja constancia
 * de por qué. Solo agrega columnas anulables; no toca ni borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->text('motivo_suspension')->nullable();
            $table->dateTime('suspendido_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->dropColumn(['motivo_suspension', 'suspendido_en']);
        });
    }
};
