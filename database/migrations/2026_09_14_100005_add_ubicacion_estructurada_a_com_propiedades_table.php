<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — ubicación estructurada de `com_propiedades` (HU-76,
 * tarea 92): departamento/municipio/localidad + coordenada, para ubicar la
 * propiedad en el mapa y filtrar por zona. Pedido explícito llegado el
 * 13/9/2026 (`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`,
 * fila HU-76), que amplía ADR 0018 punto 1 — ver la adenda fechada al pie de
 * ese punto.
 *
 * `ubicacion` (texto libre) NO se reemplaza: queda como referencia
 * libre/histórica, coexistiendo con estas columnas nuevas.
 *
 * `departamento`/`municipio`/`localidad` texto libre, mismo criterio que
 * `ubicacion` — no hay pedido de catálogo cerrado. `latitud`/`longitud`
 * nunca una sin la otra (validado en el Request vía `required_with` mutuo,
 * mismo patrón que `ventanas.*.hora_inicio`/`hora_fin` de
 * `CrearContratoRequest`); el CHECK de rango va solo en pgsql, mismo patrón
 * que `com_lotes_hectareas_chk`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->string('departamento', 100)->nullable()->after('ubicacion');
            $table->string('municipio', 100)->nullable()->after('departamento');
            $table->string('localidad', 150)->nullable()->after('municipio');
            $table->decimal('latitud', 9, 6)->nullable()->after('localidad');
            $table->decimal('longitud', 9, 6)->nullable()->after('latitud');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_propiedades
                ADD CONSTRAINT {$prefijo}com_propiedades_latitud_chk
                    CHECK (latitud IS NULL OR (latitud >= -90 AND latitud <= 90)),
                ADD CONSTRAINT {$prefijo}com_propiedades_longitud_chk
                    CHECK (longitud IS NULL OR (longitud >= -180 AND longitud <= 180))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_propiedades
                DROP CONSTRAINT {$prefijo}com_propiedades_latitud_chk,
                DROP CONSTRAINT {$prefijo}com_propiedades_longitud_chk
            SQL);
        }

        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->dropColumn(['departamento', 'municipio', 'localidad', 'latitud', 'longitud']);
        });
    }
};
