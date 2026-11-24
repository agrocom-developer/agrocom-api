<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_zonas` (módulo Solicitudes, especificación del marketplace §4.1): zonas
 * geográficas con centroide y radio. El proveedor declara su zona base y el
 * motor de asignación (ADR 0030) las usa para el escalado por radio. Esta
 * migración crea solo la tabla; el catálogo lo siembra `ZonasSeeder`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->decimal('centroide_lat', 10, 7);
            $table->decimal('centroide_lng', 10, 7);
            $table->decimal('radio_km', 6, 1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_zonas_nombre_unico ON {$prefijo}sol_zonas (nombre) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sol_zonas
                ADD CONSTRAINT {$prefijo}sol_zonas_radio_km_chk CHECK (radio_km > 0),
                ADD CONSTRAINT {$prefijo}sol_zonas_latitud_chk CHECK (centroide_lat BETWEEN -90 AND 90),
                ADD CONSTRAINT {$prefijo}sol_zonas_longitud_chk CHECK (centroide_lng BETWEEN -180 AND 180)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_zonas');
    }
};
