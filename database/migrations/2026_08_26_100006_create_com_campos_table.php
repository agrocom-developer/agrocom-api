<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — campos físicos de una propiedad (ADR 0018).
 *
 * Cuelga de `com_propiedades`, no directo de `com_clientes`: una propiedad
 * puede estar dividida en más de un campo físico delimitado (caso
 * "Gamelera", dos mitades de 1500 ha separadas por una carretera, cada una
 * con su propia campaña) — antes de ese ADR "propiedad" y "campo" eran la
 * misma fila y no había forma de representarlo.
 *
 * `geometria` (GeoJSON, igual formato que `com_lotes.geometria`, sin
 * PostGIS — ADR 0001): el perímetro del campo, delimitado antes que sus
 * lotes. Es solo una capa de referencia visual en el editor de mapa, no una
 * restricción validada en base (no hay forma de comprobar que el polígono
 * de un lote cae dentro sin PostGIS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_campos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('com_propiedades')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->jsonb('geometria')->nullable()->comment('GeoJSON (Polygon) del perímetro del campo, de referencia');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('propiedad_id');
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único por propiedad entre campos activos (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_campos_nombre_unico
            ON {$prefijo}com_campos (propiedad_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_campos');
    }
};
