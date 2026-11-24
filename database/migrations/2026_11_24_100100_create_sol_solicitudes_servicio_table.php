<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_solicitudes_servicio` (especificación del marketplace §4.2, ADR 0027):
 * la unidad de negocio. El cliente la crea con su `uuid_cliente` (idempotente);
 * la tarifa por hectárea se congela al publicar; `proveedor_id` y `equipo_id`
 * se fijan al aceptar un proveedor. El `estado` solo lo cambia
 * `MaquinaEstadosSolicitud` (invariante 7 de CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('cliente_id')->constrained('sol_clientes')->restrictOnDelete();
            $table->foreignId('terreno_id')->constrained('sol_terrenos')->restrictOnDelete();
            $table->string('tipo_aplicacion', 24);
            $table->decimal('hectareas_solicitadas', 12, 4);
            $table->string('cultivo', 80)->nullable();
            $table->text('producto_indicado');
            $table->string('aporta_producto', 10)->default('cliente');
            $table->date('ventana_desde');
            $table->date('ventana_hasta');
            $table->boolean('urgente')->default(false);
            $table->text('observaciones')->nullable();
            $table->decimal('tarifa_hectarea', 14, 4)->nullable();
            $table->decimal('monto_estimado', 14, 2)->nullable();
            $table->decimal('monto_final', 14, 2)->nullable();
            $table->decimal('comision_porcentaje', 5, 2)->nullable();
            $table->decimal('comision_monto', 14, 2)->nullable();
            $table->string('estado', 24)->default('borrador');
            $table->foreignId('proveedor_id')->nullable()->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('flo_equipos')->restrictOnDelete();
            $table->dateTime('publicada_en')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->string('cancelada_por', 14)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id', 'estado'], 'sol_solicitudes_servicio_cliente_estado_index');
            $table->index(['estado', 'ventana_desde'], 'sol_solicitudes_servicio_estado_ventana_index');
            $table->index(['equipo_id'], 'sol_solicitudes_servicio_equipo_id_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_solicitudes_servicio_uuid_cliente_unico ON {$prefijo}sol_solicitudes_servicio (uuid_cliente) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sol_solicitudes_servicio
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_estado_chk CHECK (estado IN ('borrador', 'publicada', 'en_asignacion', 'sin_proveedor', 'asignada', 'en_camino', 'en_ejecucion', 'suspendida_por_clima', 'ejecutada', 'en_disputa', 'completada', 'cancelada')),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_tipo_chk CHECK (tipo_aplicacion IN ('herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro')),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_aporta_chk CHECK (aporta_producto IN ('cliente', 'proveedor')),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_cancelada_por_chk CHECK (cancelada_por IS NULL OR cancelada_por IN ('cliente', 'proveedor', 'administrador', 'sistema')),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_hectareas_chk CHECK (hectareas_solicitadas > 0),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_ventana_chk CHECK (ventana_hasta >= ventana_desde),
                ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_cancelada_con_motivo_chk CHECK (estado <> 'cancelada' OR (motivo_cancelacion IS NOT NULL AND cancelada_por IS NOT NULL))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_solicitudes_servicio');
    }
};
