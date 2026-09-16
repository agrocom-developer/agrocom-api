<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE cpn_campanias DROP COLUMN cliente_id` (ADR 0015, corrección
 * del 15/9/2026): la campaña deja de ser del cliente y pasa a ser un
 * catálogo compartido — "casi todas las campañas son las mismas para casi
 * todos los clientes" (mensaje del dueño, 15/9/2026). El vínculo
 * cliente-campaña vive únicamente en `com_contratos` (que ya tiene
 * `cliente_id` + `campania_id`); de ahí en más es muchos-a-muchos.
 *
 * El índice único pasa de `(cliente_id, codigo)` a `codigo` solo, global
 * entre filas activas: dos clientes ya no pueden tener cada uno su propia
 * fila `2025-2026` — hay una sola.
 *
 * Sin backfill: al momento de escribir esta migración `cpn_campanias` y
 * `com_contratos` están vacías en todos los entornos (nada llegó a
 * producción todavía con este modelo), así que no hay filas de distintos
 * clientes que reconciliar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}cpn_campanias_cliente_codigo_unico");

        Schema::table('cpn_campanias', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}cpn_campanias_codigo_unico
            ON {$prefijo}cpn_campanias (codigo)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        $prefijo = DB::getTablePrefix();

        DB::statement("DROP INDEX IF EXISTS {$prefijo}cpn_campanias_codigo_unico");

        Schema::table('cpn_campanias', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('id')->constrained('com_clientes')->restrictOnDelete();
        });

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}cpn_campanias_cliente_codigo_unico
            ON {$prefijo}cpn_campanias (cliente_id, codigo)
            WHERE deleted_at IS NULL
        SQL);
    }
};
