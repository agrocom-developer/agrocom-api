<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Mezclas` (`mez_`, ADR 0011) — renglón de producto + cantidad +
 * unidad de una mezcla (espec §7, HU-78, tarea 94, revierte CR-01). Ver
 * decisión de módulo en `create_mez_productos_table` y `runs/94.md`.
 *
 * `mezcla_id` en `cascadeOnDelete` (mismo criterio que
 * `fin_planilla_detalles.planilla_id`, ver su docblock): el detalle no es
 * una entidad independiente referenciada desde otro lado, no tiene sentido
 * sin su cabecera. `producto_id` en `restrictOnDelete`: el catálogo de
 * `mez_productos` no se borra lógicamente si hay detalles que lo referencian.
 *
 * `unidad`: catálogo cerrado (`l`, `ml`, `kg`, `g`), validado en
 * `Contratos\ItemMezcla::intentarDesdeArreglo()` — no en un CHECK de esta
 * migración, mismo criterio que `motivo_cierre`/`tipo_incidencia` en el resto
 * del motor de sync (columna `string` libre, catálogo fijado en el DTO).
 *
 * Sin `uuid_cliente` propio: el detalle nace siempre junto con su cabecera,
 * dentro de la misma transacción de un único registro `mezcla` del lote de
 * sync — la idempotencia se resuelve en la cabecera (`mez_mezclas.uuid_cliente`);
 * un reintento nunca llega a evaluar los detalles otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mez_mezcla_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mezcla_id')->constrained('mez_mezclas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('mez_productos')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->string('unidad', 10);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mezcla_id');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}mez_mezcla_detalles
                ADD CONSTRAINT {$prefijo}mez_mezcla_detalles_cantidad_chk
                    CHECK (cantidad > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_mezcla_detalles');
    }
};
