<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-89 (tarea 104): segunda descripción, propia del cierre, para dejar
 * constancia de qué se hizo realmente al cerrar la orden — sin pisar
 * `descripcion` (la de apertura, fijada en `abrir()`).
 *
 * Nullable a nivel de esquema a propósito: las órdenes ya `cerrada` antes de
 * esta tarea no tienen este dato y `up()` no puede backfillarlo. La
 * obligatoriedad para cierres nuevos es de dominio
 * (`MaquinaEstadosOrdenMantenimiento::cerrar()`) y de request
 * (`CerrarOrdenMantenimientoRequest`), no de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('man_ordenes_mantenimiento', function (Blueprint $table) {
            $table->text('descripcion_final')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('man_ordenes_mantenimiento', function (Blueprint $table) {
            $table->dropColumn('descripcion_final');
        });
    }
};
