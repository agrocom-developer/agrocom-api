<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — anticipos con tope validado (espec
 * Sprint 8 §192; HU-29, tarea 41): "como encargado, quiero registrar
 * anticipos validando el tope, para no adelantar más de lo devengado".
 * Segunda tabla del módulo, después de `fin_devengos_personal` (tarea 40).
 *
 * Sin `UNIQUE` sobre `(persona_id, fecha)` ni nada equivalente: nada impide
 * varios anticipos de la misma persona en el mismo mes calendario — el tope
 * los limita EN CONJUNTO (`Aplicacion/CalcularDisponibleAnticipo`), no
 * exclusivamente uno por mes. La idempotencia por `uuid_cliente` (invariante
 * 1 de CLAUDE.md) no aplica: un anticipo nace en el panel, no en la app de
 * campo.
 *
 * Sin máquina de estados (a diferencia de HU-34, rendiciones, que sí la va a
 * tener): alta y baja simple, más cercano a un ABM acotado. Por eso no lleva
 * columna `estado`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_anticipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('per_personas')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('motivo', 200)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('persona_id');
        });

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_anticipos
                ADD CONSTRAINT {$prefijo}fin_anticipos_monto_chk
                    CHECK (monto > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_anticipos');
    }
};
