<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Campania (`cpn_`, ADR 0011 — extensión del 7/9/2026, punto 4: no
 * `cmp_`, que se descartó por parecerse a `com_`) — la campaña como eje
 * transversal (ADR 0015 punto 1): "avance por cliente, contrato y campaña"
 * (HU-32) y "para que la campaña tenga costo real" (`fin_gastos`) por fin
 * tienen tabla detrás.
 *
 * `codigo` (`2025-2026`) único entre filas activas (índice parcial, mismo
 * criterio que `com_campos_nombre_unico`): dos campañas activas con el mismo
 * código serían indistinguibles en cualquier reporte. `estado` con CHECK
 * (`planificada`/`abierta`/`cerrada`, ADR 0015) — las transiciones y sus
 * guardas viven en el servicio de dominio de la máquina de estados
 * (invariante 7 de CLAUDE.md), nunca acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpn_campanias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nombre', 150)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('planificada');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Código único entre campañas activas (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}cpn_campanias_codigo_unico
            ON {$prefijo}cpn_campanias (codigo)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}cpn_campanias
                ADD CONSTRAINT {$prefijo}cpn_campanias_estado_chk
                    CHECK (estado IN ('planificada', 'abierta', 'cerrada')),
                ADD CONSTRAINT {$prefijo}cpn_campanias_fechas_chk
                    CHECK (fecha_fin >= fecha_inicio)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cpn_campanias');
    }
};
