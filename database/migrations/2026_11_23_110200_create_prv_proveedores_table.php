<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prv_proveedores` (módulo Proveedores, especificación del marketplace §4.3,
 * ADR 0028 y 0032): la entidad que presta el servicio y recibe ofertas.
 * `tipo`: `propio` (Agrocom, único), `independiente` (un piloto que es su
 * propio proveedor) o `tercero` (empresa o dueño de drones con pilotos a su
 * cargo). Nace `pendiente`; solo un administrador lo aprueba.
 *
 * `usuario_titular_id` es nulo solo para el proveedor propio mientras Agrocom
 * no le asigna una cuenta titular. `motivo_estado` guarda el motivo de la
 * última decisión (rechazo, suspensión o baja) para mostrarlo al afectado; el
 * historial completo vive en `prv_historial_estados`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prv_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_titular_id')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->string('tipo', 14);
            $table->string('razon_social', 150);
            $table->string('documento_fiscal', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->foreignId('zona_base_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
            $table->decimal('radio_operacion_km', 6, 1)->default(50);
            $table->string('estado', 12)->default('pendiente');
            $table->text('motivo_estado')->nullable();
            $table->decimal('calificacion_promedio', 3, 2)->default(0);
            $table->unsignedInteger('calificaciones_conteo')->default(0);
            $table->decimal('tasa_aceptacion', 5, 4)->default(1);
            $table->decimal('tasa_cancelacion', 5, 4)->default(0);
            $table->foreignId('aprobado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobado_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'tipo'], 'prv_proveedores_estado_tipo_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}prv_proveedores_titular_unico ON {$prefijo}prv_proveedores (usuario_titular_id) WHERE usuario_titular_id IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}prv_proveedores_documento_unico ON {$prefijo}prv_proveedores (documento_fiscal) WHERE documento_fiscal IS NOT NULL AND deleted_at IS NULL");
        // Agrocom existe como un único proveedor de tipo `propio` (especificación §4.3).
        DB::statement("CREATE UNIQUE INDEX {$prefijo}prv_proveedores_propio_unico ON {$prefijo}prv_proveedores (tipo) WHERE tipo = 'propio' AND deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}prv_proveedores
                ADD CONSTRAINT {$prefijo}prv_proveedores_tipo_chk CHECK (tipo IN ('propio', 'independiente', 'tercero')),
                ADD CONSTRAINT {$prefijo}prv_proveedores_estado_chk CHECK (estado IN ('pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja')),
                ADD CONSTRAINT {$prefijo}prv_proveedores_radio_chk CHECK (radio_operacion_km > 0),
                ADD CONSTRAINT {$prefijo}prv_proveedores_calificacion_chk CHECK (calificacion_promedio BETWEEN 0 AND 5),
                ADD CONSTRAINT {$prefijo}prv_proveedores_tasas_chk CHECK (tasa_aceptacion BETWEEN 0 AND 1 AND tasa_cancelacion BETWEEN 0 AND 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prv_proveedores');
    }
};
