<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `flo_drones` (módulo Flota, especificación del marketplace §4.4, ADR 0029):
 * el dron es el vehículo y tiene dueño. `dueno_proveedor_id` lo posee;
 * `operador_proveedor_id` lo pone a trabajar (por defecto el mismo). Necesita
 * aprobación de un administrador antes de recibir ofertas.
 *
 * `ope_dron_id` enlaza el dron del marketplace con la fila de `ope_drones` que
 * usa la ejecución en el lote (sesiones de vuelo): el catálogo operativo se
 * reutiliza, no se duplica. `ciclos_bateria` es el único dato de mantenimiento
 * que se conserva (ADR 0029, punto 4, y ADR 0035); sube con cada recarga
 * registrada en una sesión de ese dron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flo_drones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dueno_proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('operador_proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('ope_dron_id')->nullable()->constrained('ope_drones')->restrictOnDelete();
            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->string('numero_serie', 60);
            $table->decimal('capacidad_tanque_l', 6, 2);
            $table->decimal('ancho_pasada_m', 5, 2);
            $table->unsignedInteger('ciclos_bateria')->default(0);
            $table->string('estado', 12)->default('pendiente');
            $table->text('motivo_estado')->nullable();
            $table->string('origen', 20);
            $table->foreignId('aprobado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobado_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['operador_proveedor_id', 'estado'], 'flo_drones_operador_estado_index');
            $table->index(['dueno_proveedor_id'], 'flo_drones_dueno_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}flo_drones_numero_serie_unico ON {$prefijo}flo_drones (numero_serie) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}flo_drones_ope_dron_unico ON {$prefijo}flo_drones (ope_dron_id) WHERE ope_dron_id IS NOT NULL AND deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}flo_drones
                ADD CONSTRAINT {$prefijo}flo_drones_estado_chk CHECK (estado IN ('pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja')),
                ADD CONSTRAINT {$prefijo}flo_drones_origen_chk CHECK (origen IN ('flota_agrocom', 'vendido_por_agrocom', 'externo')),
                ADD CONSTRAINT {$prefijo}flo_drones_capacidad_chk CHECK (capacidad_tanque_l > 0),
                ADD CONSTRAINT {$prefijo}flo_drones_ancho_pasada_chk CHECK (ancho_pasada_m > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flo_drones');
    }
};
