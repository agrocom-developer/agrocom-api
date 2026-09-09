<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Campania (`cpn_`, ADR 0011 — extensión del 7/9/2026, punto 4: no
 * `cmp_`, que se descartó por parecerse a `com_`) — la campaña **del
 * cliente** como eje transversal (ADR 0015 punto 1, corregido el 8/9/2026):
 * "avance por cliente, contrato y campaña" (HU-32) y "para que la campaña
 * tenga costo real" (`fin_gastos`) por fin tienen tabla detrás.
 *
 * `cliente_id` — FK real + entero plano (ADR 0003 regla 3, nunca `belongsTo`
 * cross-módulo): la campaña la corre el cliente, no Agrocom ("cada cliente
 * maneja sus campañas, nosotros solo vamos a fumigar"). `restrictOnDelete`:
 * un cliente con campañas no se borra sin antes resolverlas.
 *
 * `codigo` (`2025-2026`) único **por cliente** entre filas activas (índice
 * parcial sobre `(cliente_id, codigo)`, mismo criterio que
 * `com_campos_nombre_unico`): dos clientes pueden tener cada uno su
 * `2025-2026`, son campañas distintas. `estado` con CHECK
 * (`planificada`/`abierta`/`cerrada`, ADR 0015) — las transiciones y sus
 * guardas viven en el servicio de dominio de la máquina de estados
 * (invariante 7 de CLAUDE.md), nunca acá. Sin guarda de solapamiento de
 * fechas: hay tantas campañas abiertas como clientes en campaña, y un mismo
 * cliente puede tener dos a la vez (soya de verano, maíz de invierno).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpn_campanias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 150)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('planificada');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });

        $prefijo = DB::getTablePrefix();

        // Código único por cliente entre campañas activas (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}cpn_campanias_cliente_codigo_unico
            ON {$prefijo}cpn_campanias (cliente_id, codigo)
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
