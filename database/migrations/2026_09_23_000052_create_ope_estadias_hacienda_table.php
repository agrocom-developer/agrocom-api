<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ope_estadias_hacienda` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo OPE.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000052_create_ope_estadias_hacienda_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ope_estadias_hacienda')) {
            return;
        }

        Schema::create('ope_estadias_hacienda', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('equipo_trabajo_id')->constrained('per_equipos_trabajo')->restrictOnDelete();
            $table->dateTime('entrada');
            $table->dateTime('salida')->nullable();
            $table->foreignId('vehiculo_id')->nullable()->constrained('man_vehiculos')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->string('cierre_uuid_cliente', 36)->nullable();
            $table->foreignId('propiedad_id')->constrained('com_propiedades')->restrictOnDelete();
            $table->string('tipo_alojamiento', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['equipo_trabajo_id'], 'ope_estadias_hacienda_equipo_trabajo_id_index');
            $table->index(['propiedad_id'], 'ope_estadias_hacienda_propiedad_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_equipo_abierta_unico ON {$prefijo}ope_estadias_hacienda USING btree (equipo_trabajo_id) WHERE (salida IS NULL) AND (deleted_at IS NULL)
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_estadias_hacienda_uuid_cliente_unico ON {$prefijo}ope_estadias_hacienda USING btree (uuid_cliente) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_estadias_hacienda
                ADD CONSTRAINT {$prefijo}ope_estadias_hacienda_salida_chk CHECK ((salida IS NULL) OR (salida > entrada)),
                ADD CONSTRAINT {$prefijo}ope_estadias_hacienda_tipo_alojamiento_chk CHECK ((tipo_alojamiento IS NULL) OR tipo_alojamiento IN ('hacienda', 'pueblo', 'camping'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_estadias_hacienda');
    }
};
