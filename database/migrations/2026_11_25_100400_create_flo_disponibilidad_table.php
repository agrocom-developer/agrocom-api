<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `flo_disponibilidad` (módulo Flota, especificación del marketplace §4.4): el
 * proveedor se pone «en línea» desde la app para recibir ofertas. Una fila por
 * equipo; la última ubicación declarada (opcional) alimenta la distancia de la
 * asignación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flo_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('flo_equipos')->restrictOnDelete();
            $table->boolean('en_linea')->default(false);
            $table->decimal('ubicacion_lat', 10, 7)->nullable();
            $table->decimal('ubicacion_lng', 10, 7)->nullable();
            $table->dateTime('actualizado_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('equipo_id', 'flo_disponibilidad_equipo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flo_disponibilidad');
    }
};
