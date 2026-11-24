<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prv_auxiliares` (módulo Proveedores, especificación del marketplace §4.3):
 * quien prepara la mezcla y asiste en el lote. Lo registra un piloto o el
 * proveedor (`registrado_por_piloto_id` es nulo cuando lo registra el
 * proveedor) y un administrador lo aprueba (ADR 0032, punto 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prv_auxiliares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('registrado_por_piloto_id')->nullable()->constrained('prv_pilotos')->restrictOnDelete();
            $table->string('estado', 12)->default('pendiente');
            $table->text('motivo_estado')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobado_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'estado'], 'prv_auxiliares_proveedor_estado_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}prv_auxiliares_usuario_unico ON {$prefijo}prv_auxiliares (usuario_id) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}prv_auxiliares
                ADD CONSTRAINT {$prefijo}prv_auxiliares_estado_chk CHECK (estado IN ('pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prv_auxiliares');
    }
};
