<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — datos de presentación de Agrocom SRL (nombre, rubro,
 * contacto), pestaña "Organización" de `/panel/organizacion`. Antes era un
 * mock sin persistencia (ver docblock de `OrganizacionController`); el dueño
 * pidió empezar a guardarlos de verdad aunque el resto de esa pestaña (plan
 * de suscripción, multi-sucursal) siga siendo mock hasta que el pivot
 * multi-tenant tenga su propio ADR.
 *
 * Mismo criterio que `sec_datos_fiscales` (tarea 78): fila única por diseño
 * (una empresa, un juego de datos), sin `UNIQUE` de aplicación porque no hay
 * una clave de negocio que lo exprese — la unicidad la sostiene
 * `GuardarDatosEmpresa`, que siempre reutiliza la única fila viva.
 *
 * Deliberadamente NO se llama `sec_organizacion` por la misma razón que esa
 * tabla: no reabre la decisión de tenancy pendiente, solo cubre los campos
 * de presentación que el dueño pidió.
 *
 * `logo_path` (ADR 0019, excepción puntual a ADR 0009 categoría 3): ruta
 * relativa en el disco `public` (`logos/empresa/...`), nunca URL absoluta —
 * NO es `fillable` en el modelo a propósito, la escribe únicamente
 * `GuardarDatosEmpresa` tras guardar el archivo, nunca un `fill()` directo
 * del request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_datos_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('rubro', 255);
            $table->string('logo_path', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_datos_empresa');
    }
};
