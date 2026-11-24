<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_terreno_fotos` (especificación del marketplace §4.1): fotos del estado del
 * terreno que el cliente sube y que el piloto ve antes de ofertar. Imagen de menos
 * de 300 KB en el disco privado; nunca una URL pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_terreno_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('sol_terrenos')->restrictOnDelete();
            $table->string('archivo', 255);
            $table->string('hash', 64);
            $table->unsignedInteger('bytes');
            $table->string('descripcion', 140)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['terreno_id', 'id'], 'sol_terreno_fotos_terreno_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_terreno_fotos');
    }
};
