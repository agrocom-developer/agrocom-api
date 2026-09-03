<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Inventario` (`inv_`) — agregado de stock por `(repuesto_id,
 * base_id)` (HU-36, tarea 52). Una fila por combinación, recalculada por
 * `RegistrarMovimientoStock` a partir de cada asiento de `inv_movimientos` —
 * nunca un alta humana directa (no hay ruta `POST /panel/stock`, se crea con
 * `firstOrCreate` la primera vez que se mueve ese repuesto en esa base).
 *
 * Extiende `ModeloDominio` (soft delete incluido) igual que el resto del
 * catálogo, aunque el negocio nunca la borra: `tests/Unit/ArquitecturaModulosTest.php`
 * exige que TODO modelo en `Infraestructura/Eloquent/` de un módulo extienda
 * la base de plataforma (ADR 0007), sin excepción por archivo — abrir una acá
 * exigiría tocar ese test, y esta fila no lo necesita (no hay caso de uso que
 * la elimine). El soft delete queda como capacidad de plataforma sin uso
 * desde el dominio, no como una funcionalidad de negocio de "borrar stock".
 *
 * `cantidad`: `DECIMAL` (no entero) porque `unidad` en `inv_repuestos` es
 * libre y puede ser fraccionaria (litros, kg). `CHECK >= 0` en la base es el
 * backstop (ADR 0001): el camino feliz es la guarda de aplicación con
 * `lockForUpdate()` en `RegistrarMovimientoStock`, que nunca deja llegar una
 * resta que la viole.
 *
 * `stock_minimo`: punto de reposición por `(repuesto, base)` — coincide con
 * el CA esencial ("alerta al cruzar el mínimo") y con la especificación, no
 * es un recorte.
 *
 * `base_id`: FK plana a `per_bases.id` (ADR 0003 regla 3), sin `belongsTo`
 * cross-módulo en el modelo Eloquent — mismo criterio que `man_baterias.base_id`.
 * A diferencia de esa columna, acá NO es nullable: una fila de stock siempre
 * nace atada a una base concreta (es la clave del agregado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('inv_repuestos')->restrictOnDelete();
            $table->unsignedBigInteger('base_id');
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['repuesto_id', 'base_id']);
            $table->index('base_id');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_stock
                ADD CONSTRAINT {$prefijo}inv_stock_cantidad_chk
                    CHECK (cantidad >= 0),
                ADD CONSTRAINT {$prefijo}inv_stock_stock_minimo_chk
                    CHECK (stock_minimo >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock');
    }
};
