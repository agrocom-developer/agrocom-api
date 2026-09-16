<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — provincias del catálogo cerrado de Bolivia, encadenadas
 * a `com_departamentos` (adenda 16/9/2026 a ADR 0018 punto 1). Ver docblock
 * de `2026_09_16_100005_create_com_departamentos_table.php` para el porqué
 * de vivir en Comercial y de conservar los IDs del dump de origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_provincias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('com_departamentos')->restrictOnDelete();
            $table->string('nombre', 100);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('departamento_id');
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único por departamento entre provincias activas — dos
        // departamentos distintos pueden tener una provincia homónima.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_provincias_nombre_unico
            ON {$prefijo}com_provincias (departamento_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_provincias');
    }
};
