<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_departamentos` (ADR 0045, punto 3): los 9 departamentos de Bolivia con su
 * código del INE (dos dígitos). Es un catálogo oficial de solo lectura, sin
 * geometría, centro ni parámetros: no es una zona administrable y nadie lo edita
 * desde el panel. Esta migración crea solo la tabla; lo siembra `UbicacionesSeeder`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique('sol_departamentos_codigo_unico');
            $table->string('nombre', 60);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_departamentos');
    }
};
