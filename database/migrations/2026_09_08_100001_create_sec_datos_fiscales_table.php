<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — datos fiscales de Agrocom SRL (tarea 78, HU-55), pestaña
 * "Facturación" de `/panel/organizacion`. Pedido del dueño 7/9/2026, separado
 * a propósito de `plt_configuraciones` (tarea 78 también): esto es un DATO de
 * la empresa (quién factura), no un parámetro de infraestructura (con qué
 * llave funciona el sistema).
 *
 * Deliberadamente NO se llama `sec_organizacion`: el resto de la pantalla
 * `/panel/organizacion` sigue siendo el mockup sin persistencia de una
 * visión multi-tenant sin ADR (ver `OrganizacionController`) — esta tabla
 * cubre SOLO los cinco campos fiscales que el dueño pidió, no reabre esa
 * decisión pendiente.
 *
 * Fila única por diseño (una empresa, un juego de datos fiscales): sin
 * `UNIQUE` de aplicación porque no hay una clave de negocio que lo exprese —
 * la unicidad la sostiene `GuardarDatosFiscales`, que siempre reutiliza la
 * única fila viva en vez de crear una segunda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_datos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social_fiscal', 255);
            $table->string('nit', 50);
            $table->string('domicilio_fiscal', 255);
            $table->string('actividad_economica', 255);
            $table->text('leyenda_pie')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_datos_fiscales');
    }
};
