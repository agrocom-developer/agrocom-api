<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — `geometria` de `com_propiedades` (ADR 0020, revierte
 * ADR 0018): los terrenos físicos de la propiedad ("islas" como Gamelera,
 * dos mitades separadas por una carretera) pasan a ser geometría, nunca una
 * fila `Campo` con `id` propio.
 *
 * GeoJSON `MultiPolygon` (RFC 7946 §3.1.7), no `Polygon` como
 * `com_campos.geometria`/`com_lotes.geometria`: una propiedad puede tener
 * más de un terreno no contiguo, y `MultiPolygon` con un solo elemento cubre
 * el caso común sin rama especial de código. Igual que el resto del esquema
 * (ADR 0001): jsonb, se guarda y se dibuja, nunca se consulta
 * espacialmente — sin PostGIS en v1.
 *
 * Migración de datos en el mismo `up()`: por cada propiedad, se juntan los
 * `coordinates` de la `geometria` (Polygon) de sus `com_campos` hijos
 * activos en un solo `MultiPolygon`. Hoy es un no-op real (verificado contra
 * el compose: 0 de los 5 `com_campos` existentes tiene `geometria` cargada,
 * ninguna UI la escribió nunca), pero se escribe correcta por si hay datos
 * reales en otro entorno. Propiedad sin campos con geometría: no se toca,
 * queda `geometria` NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->jsonb('geometria')->nullable()->after('longitud')
                ->comment('GeoJSON (MultiPolygon) de los terrenos de la propiedad, de referencia');
        });

        DB::table('com_propiedades')->orderBy('id')->chunkById(200, function ($propiedades) {
            foreach ($propiedades as $propiedad) {
                $poligonos = DB::table('com_campos')
                    ->where('propiedad_id', $propiedad->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geometria')
                    ->pluck('geometria')
                    ->map(function ($geometriaJson) {
                        $geometria = json_decode((string) $geometriaJson, true);

                        return $geometria['coordinates'] ?? null;
                    })
                    ->filter()
                    ->values();

                if ($poligonos->isEmpty()) {
                    continue;
                }

                DB::table('com_propiedades')->where('id', $propiedad->id)->update([
                    'geometria' => json_encode([
                        'type' => 'MultiPolygon',
                        'coordinates' => $poligonos->all(),
                    ]),
                ]);
            }
        });
    }

    public function down(): void
    {
        // No hay forma de deshacer el join de geometrías de vuelta a
        // `com_campos` (aceptado): el rollback solo dropea la columna.
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->dropColumn('geometria');
        });
    }
};
