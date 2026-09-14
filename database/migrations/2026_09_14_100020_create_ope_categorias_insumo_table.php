<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — catálogo de categorías de insumo (HU-79, tarea 110):
 * qué se aplica (fertilizante, herbicida, semilla de pasto...) y si ese
 * insumo es sólido (la orden pide kilos por vuelo) o líquido (litros por
 * hectárea). Mismo criterio que `com_cultivos` (catálogo simple, con
 * modelo propio en vez de un enum embebido) — el dueño pidió esto explícito
 * para poder sumar categorías después sin tocar código, aunque por ahora no
 * hay pantalla de alta y el catálogo entra sembrado por
 * `OperacionesCategoriasInsumoSeeder`.
 *
 * `tipo_insumo` es el único dato que necesita `ope_ordenes_aplicacion` para
 * saber qué campo mostrar/exigir (kilos_por_vuelo vs. litros_ha) — la orden
 * NO repite su propio `tipo_insumo`: sería una segunda fuente de verdad
 * sobre el mismo dato que ya vive acá (mismo criterio que sacarle `lote_id`
 * a la orden en la tarea 107).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_categorias_insumo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('tipo_insumo', 10);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único entre categorías activas (índice parcial, mismo
        // patrón que com_cultivos_nombre_unico): un unique() normal chocaría
        // con el soft delete.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_categorias_insumo_nombre_unico
            ON {$prefijo}ope_categorias_insumo (nombre)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_categorias_insumo
                ADD CONSTRAINT {$prefijo}ope_categorias_insumo_tipo_insumo_chk
                    CHECK (tipo_insumo IN ('solido', 'liquido'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_categorias_insumo');
    }
};
