<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal — bases (espec §4.2), alcance mínimo para HU-01
 * (ADR 0011, extensión 26/8/2026, punto 6): solo id/nombre/ubicacion.
 *
 * created_by/updated_by sin FK a propósito, mismo criterio que TE-03: el
 * módulo Seguridad recién nace en esta misma HU y sec_user.id todavía no
 * es un destino estable para retroalimentar tablas ya migradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_bases', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('ubicacion', 200)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_bases');
    }
};
