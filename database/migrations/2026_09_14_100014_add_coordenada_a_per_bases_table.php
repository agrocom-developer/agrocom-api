<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal — coordenada estructurada de `per_bases` (HU-85, tarea
 * 100): mismo patrón que `com_propiedades` (HU-76, tarea 92) para ubicar la
 * base en el mapa.
 *
 * `ubicacion` (texto libre) NO se toca: queda como referencia libre,
 * coexistiendo con `latitud`/`longitud`. Nunca una sin la otra (validado en
 * el Request vía `required_with` mutuo); el CHECK de rango va solo en pgsql,
 * mismo patrón que `com_propiedades_latitud_chk`/`com_propiedades_longitud_chk`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('per_bases', function (Blueprint $table) {
            $table->decimal('latitud', 9, 6)->nullable()->after('ubicacion');
            $table->decimal('longitud', 9, 6)->nullable()->after('latitud');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_bases
                ADD CONSTRAINT {$prefijo}per_bases_latitud_chk
                    CHECK (latitud IS NULL OR (latitud >= -90 AND latitud <= 90)),
                ADD CONSTRAINT {$prefijo}per_bases_longitud_chk
                    CHECK (longitud IS NULL OR (longitud >= -180 AND longitud <= 180))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_bases
                DROP CONSTRAINT {$prefijo}per_bases_latitud_chk,
                DROP CONSTRAINT {$prefijo}per_bases_longitud_chk
            SQL);
        }

        Schema::table('per_bases', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
