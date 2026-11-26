<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_provincias` (ADR 0045, punto 3): las 112 provincias de Bolivia con su código
 * del INE (cuatro dígitos: el del departamento y dos más). Catálogo oficial de solo
 * lectura que lo siembra `UbicacionesSeeder`. La pareja (departamento, código) es
 * única para que un terreno o una solicitud apunte a ella con llave compuesta y
 * una provincia no pueda quedar bajo un departamento que no es el suyo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_provincias', function (Blueprint $table) {
            $table->id();
            $table->string('departamento_codigo', 2);
            $table->string('codigo', 4)->unique('sol_provincias_codigo_unico');
            $table->string('nombre', 80);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['departamento_codigo', 'codigo'], 'sol_provincias_departamento_codigo_unico');
            $table->foreign('departamento_codigo', 'sol_provincias_departamento_fk')
                ->references('codigo')->on('sol_departamentos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_provincias');
    }
};
