<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — hectáreas totales DECLARADAS de la propiedad (pedido
 * directo del dueño, 16/9/2026): la hacienda completa (terreno de siembra +
 * lo que no se fumiga, p. ej. ganadería) puede ser mucho más grande que la
 * suma de sus lotes — eso es justo lo que factura (invariante 6), esto es
 * solo contexto/contenedor. Mismo criterio que `com_lotes.hectareas`: dato
 * DECLARADO a mano, nunca calculado del polígono dibujado en el mapa — un
 * polígono mal trazado no puede hacer que el sistema le diga a un cliente
 * (el portal es visible para él) "tu propiedad tiene 2.950 ha" cuando en el
 * título dice 3.000, que se leería como un cobro de más aunque estas
 * hectáreas no sean las que se facturan.
 *
 * Nullable (a diferencia de `com_lotes.hectareas`, que es NOT NULL): una
 * propiedad puede existir sin que todavía se conozca o se haya cargado la
 * superficie total del título.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->decimal('hectareas', 10, 2)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->dropColumn('hectareas');
        });
    }
};
