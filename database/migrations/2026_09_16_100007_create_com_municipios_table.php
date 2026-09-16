<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — municipios del catálogo cerrado de Bolivia, encadenados
 * a `com_provincias` (adenda 16/9/2026 a ADR 0018 punto 1). Reemplaza el
 * `municipio` de texto libre que tenía `com_propiedades` desde HU-76. Ver
 * docblock de `2026_09_16_100005_create_com_departamentos_table.php` para el
 * porqué de vivir en Comercial y de conservar los IDs del dump de origen.
 *
 * `localidad` NO gana catálogo acá: sigue siendo texto libre en
 * `com_propiedades` (dentro del municipio elegido) porque no hay datos de
 * esa granularidad en el dump del dueño.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('com_provincias')->restrictOnDelete();
            $table->string('nombre', 100);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provincia_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_municipios_nombre_unico
            ON {$prefijo}com_municipios (provincia_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_municipios');
    }
};
