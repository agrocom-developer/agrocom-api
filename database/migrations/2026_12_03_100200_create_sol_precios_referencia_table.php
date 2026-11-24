<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_precios_referencia` (`negociacion-contrato.md` §3.1): el precio por hectárea
 * de referencia por tipo de aplicación, con tres niveles de detalle (zona,
 * macrorregión o general). Es una guía para proponer y ofertar, nunca un límite.
 * Los valores son datos sembrados que el administrador ajusta; el precio es decimal
 * exacto (invariante 6 de CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_precios_referencia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_aplicacion', 30);
            $table->foreignId('zona_id')->nullable()->constrained('sol_zonas')->restrictOnDelete();
            $table->string('macrorregion', 30)->nullable();
            $table->decimal('precio_ha', 14, 4);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tipo_aplicacion'], 'sol_precios_referencia_tipo_index');
        });

        $prefijo = DB::getTablePrefix();

        // Un precio por (tipo, zona), por (tipo, macrorregión) y por tipo general.
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_zona_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion, zona_id) WHERE zona_id IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_macro_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion, macrorregion) WHERE zona_id IS NULL AND macrorregion IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_precios_ref_general_unico ON {$prefijo}sol_precios_referencia (tipo_aplicacion) WHERE zona_id IS NULL AND macrorregion IS NULL AND deleted_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$prefijo}sol_precios_referencia ADD CONSTRAINT {$prefijo}sol_precios_referencia_precio_chk CHECK (precio_ha > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_precios_referencia');
    }
};
