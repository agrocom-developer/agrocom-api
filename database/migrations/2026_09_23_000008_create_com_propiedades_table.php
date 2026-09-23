<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_propiedades` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000008_create_com_propiedades_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_propiedades')) {
            return;
        }

        Schema::create('com_propiedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('localidad', 150)->nullable();
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();
            $table->jsonb('geometria')->nullable();
            $table->foreignId('departamento_id')->nullable()->constrained('com_departamentos')->restrictOnDelete();
            $table->foreignId('provincia_id')->nullable()->constrained('com_provincias')->restrictOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('com_municipios')->restrictOnDelete();
            $table->string('color', 7)->nullable();
            $table->decimal('hectareas', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id'], 'com_propiedades_cliente_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_propiedades_nombre_unico ON {$prefijo}com_propiedades USING btree (cliente_id, nombre) WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_propiedades
                ADD CONSTRAINT {$prefijo}com_propiedades_color_chk CHECK ((color IS NULL) OR color IN ('#B6202D', '#CD5E1D', '#AA8C18', '#218349', '#1E8F80', '#1F80AD', '#2B50CA', '#4A30A6', '#9331C4', '#A32995', '#B92770', '#B82343', '#755238', '#566F81', '#467C61', '#7B4D80', '#766B42', '#487A73', '#4E597E', '#5B4B81', '#7C4665', '#7E4449', '#121212')),
                ADD CONSTRAINT {$prefijo}com_propiedades_latitud_chk CHECK ((latitud IS NULL) OR ((latitud >= -90) AND (latitud <= 90))),
                ADD CONSTRAINT {$prefijo}com_propiedades_longitud_chk CHECK ((longitud IS NULL) OR ((longitud >= -180) AND (longitud <= 180)))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_propiedades');
    }
};
