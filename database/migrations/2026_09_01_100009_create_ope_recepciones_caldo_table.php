<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — recepción de caldo (espec §7.2, HU-10 redefinida por
 * CR-01, tarea 18). El cliente prepara el caldo y se lo entrega a Agrocom
 * hecho; esto registra ESE hecho puntual (cuánto, cuándo, quién lo entregó),
 * nunca su composición (§7.1: ningún dato de fórmula/producto/dosis entra al
 * alcance). Mismo criterio que `ope_condiciones` (tarea 17): un HECHO sin
 * máquina de estados propia, no una entidad con ciclo de vida — por eso vive
 * dentro de `Operaciones` (dueño de `trabajo`) y no en un módulo `Mezclas`
 * nuevo (ver runs/18.md, "decisión de módulo").
 *
 * Puede haber VARIOS eventos de recepción por trabajo (§7.2: "cuánto caldo
 * entrega el cliente" no fija una sola entrega) — por eso es tabla propia y
 * no una columna de `ope_trabajos`, a diferencia de `litros_consumidos`
 * (`ope_sesiones`) y `litros_sobrante` (`ope_trabajos`), que sí son un único
 * valor declarado por sesión/trabajo (ver esas migraciones).
 *
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), mismo
 * `UNIQUE` parcial que el resto de las tablas de sync. `trabajo_id` referencia
 * la fila ya resuelta por `uuid_cliente` (el motor de sync aplica `trabajo`
 * antes que `recepcion_caldo` en `SincronizarLote::ORDEN_CAUSAL`).
 *
 * `entregado_por`: texto libre, quién del lado del CLIENTE entregó el caldo
 * (§7.2, literal) — no hay una tabla de contactos operativos del cliente a la
 * que referenciar por FK; quién de Agrocom REGISTRÓ el evento ya queda en
 * `created_by` (heredado de `ModeloDominio`/`RegistraAutoria`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_recepciones_caldo', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('litros', 10, 2);
            $table->string('entregado_por');
            $table->dateTime('hora');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('trabajo_id');
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_recepciones_caldo_uuid_cliente_unico
            ON {$prefijo}ope_recepciones_caldo (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_recepciones_caldo
                ADD CONSTRAINT {$prefijo}ope_recepciones_caldo_litros_chk
                    CHECK (litros >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_recepciones_caldo');
    }
};
