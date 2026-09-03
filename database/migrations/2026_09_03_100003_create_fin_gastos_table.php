<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — gasto real de campaña (espec §4.4,
 * línea 145; HU-33, tarea 47): "como encargado, quiero cargar gastos con su
 * categoría y comprobante, para que la campaña tenga costo real".
 *
 * `monto` NO es una columna calculada de Postgres: la persiste
 * `Aplicacion/CrearGasto` con `Brick\Math\BigDecimal` (`cantidad ×
 * precio_unitario`, invariante 6 de CLAUDE.md) — recalculable siempre desde
 * `cantidad`/`precio_unitario`, nunca reeditado por separado (no hay caso de
 * uso de edición, ver `Aplicacion/CrearGasto`).
 *
 * `base_id`/`trabajo_id` son FK planas (ADR 0003 regla 3, mismo criterio que
 * `Anticipo::persona_id`): constraint real en la base, sin `belongsTo`
 * cross-módulo en el modelo Eloquent. Ambas nullable — "imputable a trabajo,
 * base o general" del CA esencial: un gasto con las dos en NULL es general.
 * Sin `CHECK` de exclusión mutua: un gasto de un trabajo puntual bien puede
 * tener también su `base_id` (la base desde la que operó ese trabajo), así
 * que no son mutuamente excluyentes.
 *
 * Recortes deliberados frente al modelo completo de la especificación (sin
 * respaldo de dato real hoy, documentado en el prompt de la tarea):
 * - Sin `dron_id`/`vehiculo_id`: no hay módulo `Vehículo`, y "imputable a
 *   dron" no es el CA esencial de esta HU.
 * - Sin `medio_pago`: nada hoy lo usa (ni siquiera para decidir si el
 *   comprobante es obligatorio — ver `comprobante_url`, nullable, abajo).
 * - Sin `tiene_comprobante` booleano: la sola presencia de `comprobante_url`
 *   ya lo dice, una columna aparte solo podría desincronizarse de la real.
 * - Sin `rendicion_id`: la agrega la tarea 48 (HU-34) por `ALTER TABLE`
 *   cuando exista `fin_rendiciones` — mismo patrón que `capacidad_l` se
 *   agregó a `ope_drones` por `ALTER` en vez de anticiparse (tarea 36).
 *
 * `comprobante_url`/`comprobante_hash` nullable: el comprobante es opcional
 * en esta tarea (ver docblock de `CrearGastoRequest` para el porqué — sin
 * `medio_pago`, no hay dato que sostenga cuándo sería obligatorio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_gastos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('rubro_id')->constrained('fin_rubros')->restrictOnDelete();
            $table->foreignId('subrubro_id')->nullable()->constrained('fin_subrubros')->restrictOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->unsignedBigInteger('base_id')->nullable();
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->unsignedBigInteger('trabajo_id')->nullable();
            $table->foreign('trabajo_id')->references('id')->on('ope_trabajos')->restrictOnDelete();
            $table->string('comprobante_url', 500)->nullable();
            $table->string('comprobante_hash', 64)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('rubro_id');
            $table->index('base_id');
            $table->index('trabajo_id');
            $table->index('fecha');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_gastos
                ADD CONSTRAINT {$prefijo}fin_gastos_cantidad_chk
                    CHECK (cantidad > 0)
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_gastos
                ADD CONSTRAINT {$prefijo}fin_gastos_precio_unitario_chk
                    CHECK (precio_unitario > 0)
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_gastos
                ADD CONSTRAINT {$prefijo}fin_gastos_monto_chk
                    CHECK (monto > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_gastos');
    }
};
