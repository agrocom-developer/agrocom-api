<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — recargas del dron (espec §4.3, tabla `recargas`,
 * reencuadrada por CR-01; HU-13, tarea 23). Registra el HECHO puntual de
 * cada ciclo de cambio de batería/recarga de caldo durante una sesión de
 * vuelo — mismo criterio que `ope_condiciones`/`ope_recepciones_caldo`: sin
 * máquina de estados propia.
 *
 * Sin `mezcla_id` (CR-01, 1/9/2026): Agrocom no prepara la mezcla, no existe
 * módulo `Mezclas`. `bateria_saliente_id` es texto libre: no hay catálogo de
 * baterías en el esquema (el prompt de la tarea lo prohíbe explícitamente).
 *
 * `alerta_temperatura`: TRUE cuando `temperatura_bateria_c > 50` al momento
 * de la recarga (CA de HU-13: "alerta local si temperatura > 50 °C") — NO
 * bloquea el registro, se persiste igual (a diferencia de
 * `ope_condiciones.autorizado`, acá no hay ningún caso de rechazo por esta
 * regla). Calculada una sola vez al insertar (`RegistroRecarga::alertaTemperatura()`),
 * nunca recalculada después.
 *
 * `motivo_retraso_caldo`: reencuadre de `problema_caldo` de la espec §4.3
 * (escrita antes de CR-01) como "motivo del retraso/rechazo por caldo" (CA
 * vigente de `plan_sprints.md`) — nullable, solo se completa si hubo
 * retraso, no en cada recarga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_recargas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->unsignedInteger('secuencia');
            $table->decimal('litros_caldo', 10, 2);
            $table->string('bateria_saliente_id');
            $table->decimal('temperatura_bateria_c', 5, 2);
            $table->boolean('alerta_temperatura');
            $table->string('motivo_retraso_caldo', 30)->nullable();
            $table->dateTime('hora_retraso')->nullable();
            $table->decimal('litros_combustible_generador', 10, 2)->nullable();
            $table->dateTime('hora');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sesion_id', 'secuencia']);
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_recargas_uuid_cliente_unico
            ON {$prefijo}ope_recargas (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_recargas
                ADD CONSTRAINT {$prefijo}ope_recargas_litros_caldo_chk
                    CHECK (litros_caldo >= 0),
                ADD CONSTRAINT {$prefijo}ope_recargas_combustible_chk
                    CHECK (litros_combustible_generador IS NULL OR litros_combustible_generador >= 0),
                ADD CONSTRAINT {$prefijo}ope_recargas_motivo_retraso_chk
                    CHECK (motivo_retraso_caldo IS NULL OR motivo_retraso_caldo IN
                        ('filtro_tapado', 'grumos', 'decantacion', 'espuma', 'color_olor_anormal'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_recargas');
    }
};
