<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reputación del cliente (política de calificación, ADR 0039): el momento del
 * aviso por promedio bajo y el momento en que se limitó su publicación hasta una
 * revisión. Solo agrega columnas nulas: no toca filas ni migraciones existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->dateTime('aviso_calificacion_en')->nullable()->after('calificaciones_conteo');
            $table->dateTime('publicacion_limitada_en')->nullable()->after('aviso_calificacion_en');
        });
    }

    public function down(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->dropColumn(['aviso_calificacion_en', 'publicacion_limitada_en']);
        });
    }
};
