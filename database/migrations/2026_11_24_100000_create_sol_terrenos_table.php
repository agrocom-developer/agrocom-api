<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_terrenos` (especificación del marketplace §4.1): el terreno que un
 * cliente registra una vez y sobre el que pide servicios, solicitud por
 * solicitud (ADR 0027). La geometría es el polígono en GeoJSON; las hectáreas
 * son decimales exactos (invariante 6 de CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_terrenos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('sol_clientes')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->jsonb('geometria')->nullable();
            $table->decimal('hectareas', 12, 4);
            $table->string('cultivo', 80)->nullable();
            $table->text('referencias_acceso')->nullable();
            $table->text('colindantes_sensibles')->nullable();
            $table->boolean('acceso_dificil')->default(false);
            $table->foreignId('zona_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id'], 'sol_terrenos_cliente_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_terrenos ADD CONSTRAINT {$prefijo}sol_terrenos_hectareas_chk CHECK (hectareas > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_terrenos');
    }
};
