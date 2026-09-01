<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — sesión (espec §4.3, TE-05). Cada sesión es una unidad
 * de trabajo continua de un piloto con un dron; llega en el mismo lote de
 * sync que `trabajo` y lo referencia por `uuid_cliente` de cliente (espec
 * §2.1 punto 5), no por id de servidor — puede no tenerlo si el trabajo se
 * creó en el mismo lote. Esa resolución es responsabilidad del caso de uso
 * de `Sincronizacion` (TE-05), no de esta tabla.
 *
 * Recorte de alcance de la tarea 09: sin `dron_id` (tabla `drones`, módulo
 * `Mantenimiento`/`Inventario` inexistente — ADR 0011 punto 3) ni
 * `captura_rc_id` (tabla `evidencias`, TE-07/Sprint 3). Tampoco
 * `validado_por`/`fecha_validacion`/`motivo_cierre`: dependen del flujo de
 * validación (HU-14) y del cierre real con hectáreas (HU-05), que esta tarea
 * no implementa.
 *
 * `piloto_id`/`auxiliar_id` referencian `per_personas` (módulo `Personal`)
 * solo por FK + entero plano (ADR 0003, regla 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_sesiones', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->unsignedSmallInteger('secuencia');
            $table->foreignId('piloto_id')->constrained('per_personas')->restrictOnDelete();
            $table->foreignId('auxiliar_id')->nullable()->constrained('per_personas')->restrictOnDelete();
            $table->decimal('hectareas_declaradas', 10, 2)->default(0);
            $table->string('estado', 20)->default('abierto');
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trabajo_id', 'secuencia']);
            $table->index('piloto_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_sesiones_uuid_cliente_unico
            ON {$prefijo}ope_sesiones (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_sesiones
                ADD CONSTRAINT {$prefijo}ope_sesiones_estado_chk
                    CHECK (estado IN ('abierto', 'cerrado')),
                ADD CONSTRAINT {$prefijo}ope_sesiones_secuencia_chk
                    CHECK (secuencia >= 1),
                ADD CONSTRAINT {$prefijo}ope_sesiones_hectareas_declaradas_chk
                    CHECK (hectareas_declaradas >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_sesiones');
    }
};
