<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dsp_resoluciones` (política de disputas, «Proceso»): la decisión de un
 * administrador es un registro nuevo con autor y motivo; la apelación agrega una
 * segunda y la última es la que rige. Guarda el reembolso resuelto, el ajuste de
 * comisión en la misma proporción (la comisión de la solicitud queda congelada: la
 * comisión neta es la congelada menos los ajustes vigentes), si el daño se deriva
 * al seguro del proveedor y si hubo mala fe del cliente. Los reembolsos se liquidan
 * entre las partes fuera de la app (ADR 0034).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_resoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disputa_id')->constrained('dsp_disputas')->restrictOnDelete();
            $table->unsignedSmallInteger('ronda');
            $table->string('resultado', 20);
            $table->decimal('monto_reembolso', 14, 2)->default(0);
            $table->decimal('comision_ajuste', 14, 2)->default(0);
            $table->boolean('derivada_a_seguro')->default(false);
            $table->boolean('mala_fe_cliente')->default(false);
            $table->text('motivo');
            $table->foreignId('resuelta_por')->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('resuelta_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['disputa_id', 'ronda'], 'dsp_resoluciones_ronda_unica');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}dsp_resoluciones
                ADD CONSTRAINT {$prefijo}dsp_resoluciones_resultado_chk CHECK (resultado IN ('a_favor_cliente', 'a_favor_proveedor', 'parcial', 'sin_lugar')),
                ADD CONSTRAINT {$prefijo}dsp_resoluciones_montos_chk CHECK (monto_reembolso >= 0 AND comision_ajuste >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_resoluciones');
    }
};
