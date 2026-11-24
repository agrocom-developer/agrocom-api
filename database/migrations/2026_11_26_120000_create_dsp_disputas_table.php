<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dsp_disputas` (especificación del marketplace §4.8, ADR 0033 y 0039): una
 * disputa por solicitud. La abre el cliente (hasta 72 horas después de `ejecutada`)
 * o el proveedor (cobro, acceso al lote o calificación abusiva); soporte media y
 * un administrador resuelve. `cliente_usuario_id` y `proveedor_id` guardan las
 * partes para acotar lo que cada lado ve sin recorrer otros módulos. La
 * resolución y los mensajes son registros nuevos (`dsp_resoluciones`,
 * `dsp_mensajes`): nada se sobrescribe. La apelación sube `ronda` de 1 a 2 una
 * sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_disputas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('cliente_usuario_id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->string('abierta_por_rol', 10);
            $table->foreignId('abierta_por_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('motivo', 30);
            $table->text('detalle');
            $table->decimal('monto_en_disputa', 14, 2);
            $table->string('estado', 12)->default('abierta');
            $table->unsignedSmallInteger('ronda')->default(1);
            $table->boolean('movio_solicitud')->default(false);
            $table->unsignedBigInteger('atendida_por')->nullable();
            $table->dateTime('responde_antes_de')->nullable();
            $table->dateTime('apelable_hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'id'], 'dsp_disputas_estado_index');
            $table->index(['cliente_usuario_id', 'id'], 'dsp_disputas_cliente_index');
            $table->index(['proveedor_id', 'id'], 'dsp_disputas_proveedor_index');
        });

        $prefijo = DB::getTablePrefix();

        // Una disputa por solicitud (política de disputas, «Cuándo se abre»).
        DB::statement("CREATE UNIQUE INDEX {$prefijo}dsp_disputas_solicitud_unica ON {$prefijo}dsp_disputas (solicitud_id) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}dsp_disputas
                ADD CONSTRAINT {$prefijo}dsp_disputas_rol_chk CHECK (abierta_por_rol IN ('cliente', 'proveedor')),
                ADD CONSTRAINT {$prefijo}dsp_disputas_estado_chk CHECK (estado IN ('abierta', 'en_revision', 'resuelta', 'retirada')),
                ADD CONSTRAINT {$prefijo}dsp_disputas_ronda_chk CHECK (ronda IN (1, 2)),
                ADD CONSTRAINT {$prefijo}dsp_disputas_monto_chk CHECK (monto_en_disputa > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_disputas');
    }
};
