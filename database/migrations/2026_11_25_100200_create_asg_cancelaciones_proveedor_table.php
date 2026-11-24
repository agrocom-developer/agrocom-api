<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `asg_cancelaciones_proveedor` (política de cancelación, ADR 0033 y 0038): un
 * proveedor que cancela un servicio ya aceptado deja una fila con el peso que
 * cuenta en su tasa de cancelación (doble si cancela con menos de 12 horas, cero
 * si la causa no es suya). La tasa y las sanciones se calculan desde estas filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asg_cancelaciones_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('oferta_id')->nullable()->constrained('asg_ofertas')->restrictOnDelete();
            $table->unsignedBigInteger('equipo_id')->nullable();
            $table->string('causa', 20);
            $table->text('motivo');
            $table->unsignedSmallInteger('peso');
            $table->unsignedBigInteger('actor_usuario_id')->nullable();
            $table->dateTime('cancelada_en');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'cancelada_en'], 'asg_cancelaciones_proveedor_fecha_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}asg_cancelaciones_proveedor
                ADD CONSTRAINT {$prefijo}asg_cancelaciones_proveedor_causa_chk CHECK (causa IN ('otra', 'falla_de_equipo', 'emergencia')),
                ADD CONSTRAINT {$prefijo}asg_cancelaciones_proveedor_peso_chk CHECK (peso BETWEEN 0 AND 10)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asg_cancelaciones_proveedor');
    }
};
