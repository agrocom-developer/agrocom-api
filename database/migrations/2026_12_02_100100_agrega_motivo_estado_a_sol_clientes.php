<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo con el que un administrador suspende a un cliente desde el panel. Nace
 * vacío y se limpia al reactivarlo. Solo agrega una columna nula: no toca filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->text('motivo_estado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sol_clientes', function (Blueprint $table) {
            $table->dropColumn('motivo_estado');
        });
    }
};
