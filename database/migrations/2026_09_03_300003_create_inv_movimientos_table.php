<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Inventario` (`inv_`) — asiento de cada movimiento de stock (HU-36,
 * tarea 52): `compra`, `salida`, `ajuste` o `traslado`. Es el registro
 * auditable (autor, momento, motivo) del que se deriva `inv_stock` — nunca al
 * revés: `RegistrarMovimientoStock` escribe primero acá y después actualiza el
 * agregado, dentro de la misma transacción.
 *
 * Extiende `ModeloDominio` (soft delete incluido), mismo razonamiento que
 * `inv_stock`: `ArquitecturaModulosTest` exige extender la base de plataforma
 * para todo modelo del namespace `Infraestructura\Eloquent`, sin excepción
 * por archivo. Conceptualmente es un asiento contable (no se "corrige" con un
 * UPDATE ni se anula con un DELETE físico — eso ya lo prohíbe el ADR 0007 para
 * cualquier modelo), pero no hay ninguna ruta `DELETE` para movimientos en
 * esta tarea: el soft delete de la base queda sin uso desde el caso de uso,
 * no se convierte en una función de negocio de "anular movimientos". Abrir acá
 * la excepción de la invariante 8 hubiese exigido tocar el test de
 * arquitectura para eximir un solo archivo — preferible no abrir esa grieta en
 * una garantía transversal ya probada.
 *
 * `base_id`: la base AFECTADA — en `compra`/`salida`/`ajuste`, la única; en
 * `traslado`, la de ORIGEN (se decrementa). `base_destino_id` (nullable) solo
 * se usa en `traslado` (se incrementa) — la coherencia "solo traslado la
 * completa" la valida `RegistrarMovimientoStock`/el `FormRequest`, no un CHECK
 * de base: expresar esa dependencia entre dos columnas como CHECK no aporta
 * nada que la capa de aplicación no cubra ya, y complica el `down()` de
 * cualquier migración futura que la toque.
 *
 * `cantidad`: SIEMPRE una magnitud positiva, sea cual sea `tipo` — nunca
 * negativa. Para `ajuste`, que puede sumar o restar, el signo NO vive acá:
 * vive en `sentido` (`incremento`/`decremento`, NULL para los otros tres
 * tipos, donde `tipo` ya determina el signo sin ambigüedad). Se eligió un
 * campo aparte en vez de un signo embebido en `cantidad` para que "cantidad"
 * sea siempre una magnitud comparable entre movimientos, sin tener que mirar
 * `tipo` para saber si `abs()` hace falta.
 *
 * `costo_unitario`: solo tiene sentido en `compra` (es lo que sobrescribe
 * `inv_repuestos.costo_unitario`, ver docblock de esa migración) — NULL en
 * los otros tres tipos.
 *
 * `motivo`: libre, para `ajuste`/`traslado` — NULL en `compra`/`salida`
 * (mismo criterio que dejar sin motivo un movimiento que no lo necesita).
 *
 * `orden_mantenimiento_id`: `unsignedBigInteger` nullable, SIN FK real — la
 * tabla que referenciaría (`man_ordenes_mantenimiento`) todavía no existe
 * (la crea la tarea 53, HU-37). Es la correlación de TEXTO/id plano que esa
 * tarea va a completar; no se le agrega `->constrained()` contra una tabla
 * inexistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('inv_repuestos')->restrictOnDelete();
            $table->unsignedBigInteger('base_id');
            $table->foreign('base_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->unsignedBigInteger('base_destino_id')->nullable();
            $table->foreign('base_destino_id')->references('id')->on('per_bases')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->decimal('cantidad', 12, 2);
            $table->string('sentido', 20)->nullable();
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('orden_mantenimiento_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('repuesto_id');
            $table->index('base_id');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}inv_movimientos
                ADD CONSTRAINT {$prefijo}inv_movimientos_tipo_chk
                    CHECK (tipo IN ('compra', 'salida', 'ajuste', 'traslado')),
                ADD CONSTRAINT {$prefijo}inv_movimientos_cantidad_chk
                    CHECK (cantidad > 0),
                ADD CONSTRAINT {$prefijo}inv_movimientos_sentido_chk
                    CHECK (sentido IS NULL OR sentido IN ('incremento', 'decremento')),
                ADD CONSTRAINT {$prefijo}inv_movimientos_costo_unitario_chk
                    CHECK (costo_unitario IS NULL OR costo_unitario >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_movimientos');
    }
};
