<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — catálogo cerrado de departamentos de Bolivia (adenda
 * 16/9/2026 a ADR 0018 punto 1): reemplaza el `departamento` de texto libre
 * que tenía `com_propiedades` desde HU-76. Vive en Comercial (`com_`) y no en
 * un módulo propio porque es dato de referencia sin lógica de negocio ni
 * ciclo de vida — mismo criterio que `per_bases`, no el de `Campania`.
 *
 * Seedeado desde `GeografiaBoliviaSeeder` (`database/seeders/Catalogo/`),
 * con los mismos IDs que el dump de origen del dueño, para que las FK
 * encadenadas (`com_provincias.departamento_id`, `com_municipios.provincia_id`)
 * calcen sin remapeo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_departamentos_nombre_unico
            ON {$prefijo}com_departamentos (nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_departamentos');
    }
};
