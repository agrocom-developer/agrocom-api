<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — `DROP TABLE com_campos` (ADR 0020, revierte ADR 0018).
 * Última de las 5 migraciones del colapso: debe correr después de que
 * `add_geometria_a_com_propiedades_table`, `add_propiedad_id_a_com_lotes_table`,
 * `add_propiedad_id_a_ope_estadias_hacienda_table` y
 * `drop_campo_id_de_com_contrato_alcances_table` ya movieron todo lo que
 * dependía de esta tabla — Laravel corre las migraciones en orden de
 * timestamp, así que basta con el nombre/timestamp de este archivo.
 *
 * `down()` recrea el esquema vacío con la forma exacta que tenía en
 * `create_com_campos_table` (columnas, FK, índice único parcial, soft
 * delete, auditoría) — **sin restaurar datos ni las FKs que otras tablas
 * tenían hacia ella** (`com_lotes.campo_id`, `ope_estadias_hacienda.campo_id`,
 * `com_contrato_alcances.campo_id` ya no existen en el dominio): es un
 * rollback de esquema para que `migrate:rollback` no rompa a mitad de
 * camino, no una reconstrucción funcional de `Campo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('com_campos');
    }

    public function down(): void
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

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_campos_nombre_unico
            ON {$prefijo}com_campos (propiedad_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }
};
