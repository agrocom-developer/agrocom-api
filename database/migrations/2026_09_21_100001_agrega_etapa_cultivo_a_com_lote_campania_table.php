<?php

use App\Dominios\Comercial\Dominio\EtapaCultivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — en qué etapa de su ciclo está el cultivo de cada lote
 * (21/9/2026, pedido directo). Cuando el servicio es sobre un cultivo en
 * pie, lo que se registra es qué hay en el lote y en qué momento del ciclo
 * está; nullable: una siembra ya cargada no la tiene, y no siempre se sabe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_lote_campania', function (Blueprint $table) {
            $table->string('etapa_cultivo', 20)->nullable()->after('cultivo_id');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();
            $etapas = collect(EtapaCultivo::cases())->map(fn ($etapa) => "'{$etapa->value}'")->implode(', ');

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_lote_campania
                ADD CONSTRAINT {$prefijo}com_lote_campania_etapa_cultivo_chk
                    CHECK (etapa_cultivo IS NULL OR etapa_cultivo IN ({$etapas}))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}com_lote_campania DROP CONSTRAINT IF EXISTS {$prefijo}com_lote_campania_etapa_cultivo_chk");
        }

        Schema::table('com_lote_campania', function (Blueprint $table) {
            $table->dropColumn('etapa_cultivo');
        });
    }
};
