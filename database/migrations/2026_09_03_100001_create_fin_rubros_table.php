<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — catálogo de rubros de gasto (espec §4.4,
 * línea 143: "los 8 del presupuesto + Indirectos"; HU-33, tarea 47): "como
 * encargado, quiero cargar gastos con su categoría y comprobante, para que la
 * campaña tenga costo real". `presupuesto_bs_ha` es el presupuesto de
 * referencia del rubro (Bs por hectárea de campaña), sembrado junto con el
 * nombre — sin uso todavía más allá de mostrarlo (no hay reporte de
 * desviación real vs. presupuesto en esta tarea).
 *
 * Índice único parcial (mismo patrón que `com_lotes_codigo_unico`, skill
 * `modelo-datos`): un `unique()` normal chocaría con el soft delete, porque
 * una fila borrada seguiría ocupando el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_rubros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->decimal('presupuesto_bs_ha', 12, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_rubros_nombre_unico
            ON {$prefijo}fin_rubros (nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_rubros');
    }
};
