<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — cierre de trabajo (espec §4.3, HU-05, tarea 13).
 * `cierre_uuid_cliente` es el `uuid_cliente` del EVENTO de cierre — distinto
 * del `uuid_cliente` de apertura que ya identifica la fila — porque el cierre
 * es una mutación sobre un registro existente, no una fila nueva: el `UNIQUE`
 * parcial de la apertura no protege un reintento de `UPDATE` (invariante 1 de
 * CLAUDE.md, mecanismo documentado en runs/13.md). El índice único parcial de
 * abajo evita que el mismo evento de cierre quede aplicado sobre dos filas
 * distintas; la idempotencia del reintento SOBRE LA MISMA fila la resuelve
 * `EscrituraSincronizacionEloquent::cerrarTrabajo()` comparando el valor ya
 * persistido antes de escribir.
 *
 * `trabajo` no lleva `motivo_cierre` propio (espec §4.3): su cierre es
 * directo, a diferencia de `sesion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->string('cierre_uuid_cliente', 36)->nullable()->after('fin');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_trabajos_cierre_uuid_cliente_unico
            ON {$prefijo}ope_trabajos (cierre_uuid_cliente)
            WHERE cierre_uuid_cliente IS NOT NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('ope_trabajos', function (Blueprint $table) {
            $table->dropColumn('cierre_uuid_cliente');
        });
    }
};
