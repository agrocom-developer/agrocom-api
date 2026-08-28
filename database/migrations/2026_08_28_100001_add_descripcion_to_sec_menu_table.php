<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — layout de tres niveles del panel (quinta vuelta,
 * maquetas aprobadas 4a/5a/5b/5c): el nivel 2 (sidebar de ítems) muestra,
 * bajo el nombre del módulo, una línea de descripción. Igual que `label`,
 * `descripcion` guarda una CLAVE de traducción (ADR 0013), nunca texto
 * plano — se resuelve vía `__()` recién en presentación. Nullable: los
 * ítems hoja no la necesitan (solo las raíces/módulos la muestran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sec_menu', function (Blueprint $table) {
            $table->string('descripcion', 150)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('sec_menu', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
