<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — detalle del contacto "Otro" (tarea "resumen de
 * cliente"): `TipoContactoCliente::Otro` no dice nada por sí solo, así que
 * el formulario pide un texto libre cuando se elige ese tipo (ej. "Contador
 * externo"). Nullable y sin CHECK: se ignora para cualquier otro `tipo`, el
 * formulario ya lo oculta en ese caso (`resources/js/pages/clientes-form.js`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_cliente_contactos', function (Blueprint $table) {
            $table->string('tipo_otro', 100)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('com_cliente_contactos', function (Blueprint $table) {
            $table->dropColumn('tipo_otro');
        });
    }
};
