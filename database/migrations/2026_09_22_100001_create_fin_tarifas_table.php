<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Finanzas (`fin_`, ADR 0011) — tarifas de pago al personal de campo
 * (pedido del dueño, 22/9/2026; ADR 0023). Es la «configuración de pago
 * base»: lo que se paga por defecto a un piloto y a un ayudante por un tipo
 * de trabajo (fumigación manual, fumigación con mapeo, voleo…), con su
 * modalidad —por día o por hectárea—. La Orden de Trabajo elige una tarifa
 * por equipo y puede negociarla para ese trabajo puntual; el devengo copia y
 * congela la condición al validar la sesión. La tarifa NO vive en la persona
 * (`per_personas.tarifa_ha` desaparece en la migración que sigue): lo que se
 * cobra depende del trabajo, no de quién es.
 *
 * `predeterminada`: la que el alta de Orden de Trabajo propone en cada
 * equipo. A lo sumo una viva (índice único parcial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('modalidad', 10);
            $table->decimal('monto_piloto', 12, 2);
            $table->decimal('monto_auxiliar', 12, 2);
            $table->boolean('predeterminada')->default(false);
            $table->string('descripcion', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_tarifas_nombre_unico
            ON {$prefijo}fin_tarifas (lower(nombre))
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}fin_tarifas_predeterminada_unica
            ON {$prefijo}fin_tarifas (predeterminada)
            WHERE predeterminada AND deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}fin_tarifas
                ADD CONSTRAINT {$prefijo}fin_tarifas_modalidad_chk
                    CHECK (modalidad IN ('por_dia', 'por_ha')),
                ADD CONSTRAINT {$prefijo}fin_tarifas_monto_piloto_chk
                    CHECK (monto_piloto >= 0),
                ADD CONSTRAINT {$prefijo}fin_tarifas_monto_auxiliar_chk
                    CHECK (monto_auxiliar >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_tarifas');
    }
};
