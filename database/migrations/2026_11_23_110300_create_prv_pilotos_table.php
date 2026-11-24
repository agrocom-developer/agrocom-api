<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prv_pilotos` (módulo Proveedores, especificación del marketplace §4.3):
 * quien opera el dron dentro de un proveedor. Una cuenta es piloto de un solo
 * proveedor a la vez. Comparte el ciclo de aprobación del ADR 0032.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prv_pilotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('licencia_numero', 40);
            $table->date('licencia_vence');
            $table->string('categoria_habilitacion', 40);
            $table->string('estado', 12)->default('pendiente');
            $table->text('motivo_estado')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('aprobado_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'estado'], 'prv_pilotos_proveedor_estado_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}prv_pilotos_usuario_unico ON {$prefijo}prv_pilotos (usuario_id) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}prv_pilotos
                ADD CONSTRAINT {$prefijo}prv_pilotos_estado_chk CHECK (estado IN ('pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prv_pilotos');
    }
};
