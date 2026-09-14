<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — ficha de cliente ampliada (HU-75, tarea 91): dónde
 * queda la oficina central del cliente y su logo, para mostrarlo en el
 * panel/documentos a futuro.
 *
 * `logo_path` nunca es input directo del formulario — lo fija
 * `ActualizarCliente`/`CrearCliente` tras guardar el archivo (mismo criterio
 * que `SecDatosEmpresa.logo_path`, ADR 0019), por eso no está en `$fillable`
 * de `Cliente`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_clientes', function (Blueprint $table) {
            $table->string('ubicacion_oficina', 255)->nullable()->after('tipo_persona');
            $table->string('logo_path', 255)->nullable()->after('ubicacion_oficina');
        });
    }

    public function down(): void
    {
        Schema::table('com_clientes', function (Blueprint $table) {
            $table->dropColumn(['ubicacion_oficina', 'logo_path']);
        });
    }
};
