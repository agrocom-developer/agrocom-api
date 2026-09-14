<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo `Mezclas` (`mez_`, ADR 0011) — catálogo de productos cargados al
 * caldo (espec §7, HU-78, tarea 94, revierte CR-01 del 1/9/2026).
 *
 * Decisión de módulo (contraste con la tarea 18, que puso `recepcion_caldo`
 * dentro de `Operaciones` por ser "un HECHO sin máquina de estados propia,
 * no una entidad con ciclo de vida" — ver docblock de
 * `2026_09_01_100009_create_ope_recepciones_caldo_table.php`): la mezcla de
 * HU-78 SÍ trae un catálogo propio, compartido entre trabajos, que la tarea
 * 95 (HU-79) va a extender con una columna de tipo sólido/líquido para
 * filtrar — es la estructura que ADR 0011 anticipó al reservar el prefijo
 * `mez_` para un módulo propio desde TE-03 ("Operación ya se partió entre
 * Operaciones y Mezclas"). Ese catálogo evolutivo, y no el simple registro
 * puntual del hecho, es lo que cambia el criterio frente a `recepcion_caldo`
 * y justifica un módulo nuevo en vez de una tabla más de `Operaciones`
 * (runs/94.md, "decisión de módulo").
 *
 * Tabla, no enum embebido (pedido explícito del prompt de la tarea 94): un
 * enum no se puede extender por `ALTER TABLE` con una columna adicional de
 * metadata (tipo sólido/líquido, tarea 95) sin romper todo lo que ya lo usa.
 *
 * Catálogo ABIERTO, no administrado desde un CRUD en esta tarea: crece por
 * uso — `EscrituraMezclasEloquent::registrarMezcla()` hace `firstOrCreate()`
 * por `nombre` a medida que llegan registros `mezcla` del lote de sync. El
 * piloto transcribe el nombre que lee en el envase (texto libre, sin pull de
 * catálogo previo — a diferencia de `orden_id`/`lote_id`/`piloto_id`, que sí
 * resuelven contra `GET /api/sync/catalogo`): Agrocom no valida el dato, solo
 * lo registra (§7.1 sigue vigente en esto). Sin columna de tipo sólido/
 * líquido todavía (tarea 95, no anticipada acá — el prompt lo pide
 * explícito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mez_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Índice parcial (patrón `com_lotes`): un `unique()` normal chocaría
        // con el borrado lógico, que deja la fila ocupando el nombre.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}mez_productos_nombre_unico
            ON {$prefijo}mez_productos (nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('mez_productos');
    }
};
