<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ALTER TABLE com_contratos ADD campania_id` (ADR 0015 punto 1, tarea 69,
 * corregido el 8/9/2026): el contrato es con un cliente y para una campaña
 * SUYA. FK real + entero plano (ADR 0003 regla 3, nunca `belongsTo`
 * cross-módulo) — mismo criterio que `sec_user.persona_id`.
 *
 * Migración de datos, en la misma migración (ADR 0015, consecuencias
 * asumidas): nace una campaña `2025-2026` (`abierta`, 1/7/2025 a 30/6/2026)
 * por cada cliente que YA tenga contratos —incluidos los soft-deleted,
 * porque la columna termina `NOT NULL` y ninguna fila puede quedar sin
 * valor—, y cada contrato se asigna a la de SU PROPIO cliente. Recién
 * después la columna pasa a `NOT NULL`.
 *
 * Con `DB::table` en lugar del modelo Eloquent `Campania` (que puede cambiar
 * de forma en el futuro y romper esta migración retroactivamente) — mismo
 * criterio que el resto de las migraciones de este repo que tocan datos.
 *
 * `SET NOT NULL` solo en pgsql (mismo motivo que los `CHECK` del resto del
 * repo: alterar una columna existente sin `doctrine/dbal`, que este repo no
 * trae, no es posible en SQLite vía Blueprint). Los tests igual verifican la
 * invariante a nivel de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('campania_id')->nullable()->after('cliente_id');
        });

        $clientesConContrato = DB::table('com_contratos')
            ->select('cliente_id')
            ->distinct()
            ->pluck('cliente_id');

        foreach ($clientesConContrato as $clienteId) {
            $campaniaId = DB::table('cpn_campanias')->insertGetId([
                'cliente_id' => $clienteId,
                'codigo' => '2025-2026',
                'nombre' => null,
                'fecha_inicio' => '2025-07-01',
                'fecha_fin' => '2026-06-30',
                'estado' => 'abierta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('com_contratos')
                ->where('cliente_id', $clienteId)
                ->update(['campania_id' => $campaniaId]);
        }

        Schema::table('com_contratos', function (Blueprint $table) {
            $table->foreign('campania_id')->references('id')->on('cpn_campanias')->restrictOnDelete();
            $table->index('campania_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}com_contratos ALTER COLUMN campania_id SET NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->dropForeign(['campania_id']);
            $table->dropIndex(['campania_id']);
            $table->dropColumn('campania_id');
        });
    }
};
