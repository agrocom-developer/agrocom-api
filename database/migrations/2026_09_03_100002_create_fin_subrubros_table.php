<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — subrubros de gasto (espec §4.4, línea
 * 144; HU-33, tarea 47). La especificación completa trae `tipo_imputacion`
 * (`directo_dron` / `directo_vehiculo` / `compartido`) — esta tarea la deja
 * FUERA: no existe módulo `Vehículo` todavía, y sin ese dato la columna no
 * sostiene ninguna guarda real (recorte igual al de `rendicion_id` en
 * `fin_gastos`, ver esa migración). Si una guarda concreta la necesita más
 * adelante, se agrega por `ALTER TABLE` cuando el dato exista — mismo
 * criterio que `capacidad_l` en `ope_drones` (tarea 36).
 *
 * FK plana a `fin_rubros` DENTRO del mismo módulo — no es una referencia
 * cruzada (ADR 0003 regla 3), así que sí lleva `belongsTo` en el modelo
 * Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_subrubros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubro_id')->constrained('fin_rubros')->restrictOnDelete();
            $table->string('nombre', 100);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('rubro_id');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_subrubros_rubro_nombre_unico
            ON {$prefijo}fin_subrubros (rubro_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_subrubros');
    }
};
