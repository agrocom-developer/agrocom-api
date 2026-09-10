<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — lotes (espec §4.1).
 *
 * Geometría en JSONB como GeoJSON: se guarda y se dibuja, no se consulta
 * espacialmente — sin PostGIS en v1 (ADR 0001). Hectáreas DECIMAL(10,2),
 * jamás float (espec §5): las hectáreas son dinero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_id')->constrained('com_campos')->restrictOnDelete();
            $table->string('codigo', 50);
            $table->decimal('hectareas', 10, 2);
            $table->jsonb('geometria')->nullable()->comment('GeoJSON (Polygon) del perímetro del lote');
            $table->text('restricciones')->nullable()->comment('Cables, viviendas, colmenas, vecinos sensibles');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('campo_id');
        });

        $prefijo = DB::getTablePrefix();

        // Código único por campo entre lotes activos (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_lotes_codigo_unico
            ON {$prefijo}com_lotes (campo_id, codigo)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lotes
                ADD CONSTRAINT {$prefijo}com_lotes_hectareas_chk
                CHECK (hectareas > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_lotes');
    }
};
