<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `asg_ofertas` (especificación del marketplace §4.5 y §5.3, ADR 0030 y 0038):
 * lo que el motor le ofrece a un proveedor. Dos garantías las da la base y no
 * solo el código: un proveedor recibe a lo sumo una oferta por solicitud y
 * ciclo (el reintento del motor no duplica) y solo una oferta por solicitud y
 * ciclo llega a `aceptada` (el segundo que acepta pierde la carrera).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asg_ofertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('flo_equipos')->restrictOnDelete();
            $table->unsignedInteger('ciclo');
            $table->unsignedInteger('ronda');
            $table->string('grupo', 10);
            $table->string('origen', 14)->default('motor');
            $table->string('estado', 10)->default('ofrecida');
            $table->dateTime('ofrecida_en');
            $table->dateTime('vence_en');
            $table->dateTime('respondida_en')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->string('motivo_retiro', 24)->nullable();
            $table->decimal('pago_estimado_proveedor', 14, 2)->nullable();
            $table->decimal('distancia_km', 8, 2)->nullable();
            $table->decimal('puntuacion', 8, 4)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'vence_en'], 'asg_ofertas_estado_vence_index');
            $table->index(['proveedor_id', 'estado'], 'asg_ofertas_proveedor_estado_index');
            $table->index(['solicitud_id', 'ciclo', 'ronda'], 'asg_ofertas_solicitud_ciclo_ronda_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}asg_ofertas_proveedor_ciclo_unico ON {$prefijo}asg_ofertas (solicitud_id, proveedor_id, ciclo) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}asg_ofertas_aceptada_unica ON {$prefijo}asg_ofertas (solicitud_id, ciclo) WHERE estado = 'aceptada' AND deleted_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}asg_ofertas
                ADD CONSTRAINT {$prefijo}asg_ofertas_grupo_chk CHECK (grupo IN ('propio', 'tercero')),
                ADD CONSTRAINT {$prefijo}asg_ofertas_origen_chk CHECK (origen IN ('motor', 'administrador')),
                ADD CONSTRAINT {$prefijo}asg_ofertas_estado_chk CHECK (estado IN ('ofrecida', 'aceptada', 'rechazada', 'vencida', 'retirada')),
                ADD CONSTRAINT {$prefijo}asg_ofertas_motivo_retiro_chk CHECK (motivo_retiro IS NULL OR motivo_retiro IN ('otro_acepto', 'cancelada', 'reasignada', 'asignacion_forzada')),
                ADD CONSTRAINT {$prefijo}asg_ofertas_vence_chk CHECK (vence_en >= ofrecida_en)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asg_ofertas');
    }
};
