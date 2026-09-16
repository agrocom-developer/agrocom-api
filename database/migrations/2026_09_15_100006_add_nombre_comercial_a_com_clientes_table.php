<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — nombre comercial del cliente (tarea "resumen de
 * cliente"): una sociedad opera muchas veces con un nombre distinto al de su
 * razón social registrada (ej. razón social "Agropecuaria del Oriente SRL",
 * nombre comercial "El Tajibo"). Solo tiene sentido para `tipo_persona =
 * juridica` (ADR 0018 punto 3) — el formulario lo oculta para persona física,
 * pero la columna queda nullable sin CHECK cruzado: es dato de presentación,
 * no una regla que otra tabla dependa de cuadrar (invariante 6 de CLAUDE.md
 * no aplica, no es dinero ni hectáreas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_clientes', function (Blueprint $table) {
            $table->string('nombre_comercial', 200)->nullable()->after('razon_social');
        });
    }

    public function down(): void
    {
        Schema::table('com_clientes', function (Blueprint $table) {
            $table->dropColumn('nombre_comercial');
        });
    }
};
