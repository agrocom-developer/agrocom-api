<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `flo_equipos` (módulo Flota, especificación del marketplace §4.4): la
 * combinación operativa piloto + dron (+ auxiliar opcional) que ejecuta una
 * solicitud. Un piloto y un dron no pueden estar en dos equipos vigentes a la
 * vez: el caso de uso lo valida con bloqueo de fila y, como red de seguridad
 * ante dos pedidos simultáneos, un índice único parcial cubre el caso de la
 * vigencia abierta (`vigente_hasta` nulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flo_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->foreignId('piloto_id')->constrained('prv_pilotos')->restrictOnDelete();
            $table->foreignId('dron_id')->constrained('flo_drones')->restrictOnDelete();
            $table->foreignId('auxiliar_id')->nullable()->constrained('prv_auxiliares')->restrictOnDelete();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'vigente_hasta'], 'flo_equipos_proveedor_vigencia_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}flo_equipos_piloto_abierto_unico ON {$prefijo}flo_equipos (piloto_id) WHERE vigente_hasta IS NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}flo_equipos_dron_abierto_unico ON {$prefijo}flo_equipos (dron_id) WHERE vigente_hasta IS NULL AND deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}flo_equipos
                ADD CONSTRAINT {$prefijo}flo_equipos_vigencia_chk CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flo_equipos');
    }
};
