<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo con el que un administrador cierra la negociación de un proveedor sobre
 * una solicitud (`marketplace.md` §4.5, columna `motivo`). Nace vacío: las ofertas
 * que cierran los participantes o el reloj no lo llevan. Solo agrega una columna;
 * `down()` la quita (nadie más la lee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neg_ofertas', function (Blueprint $table) {
            $table->text('motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('neg_ofertas', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
