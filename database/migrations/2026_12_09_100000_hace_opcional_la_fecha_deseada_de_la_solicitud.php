<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La solicitud no vence sola y la fecha deseada de fumigación es opcional (ADR 0045, punto 20):
 * `ventana_desde` y `ventana_hasta` admiten nulo. El CHECK `ventana_hasta >= ventana_desde` sigue
 * valiendo (un nulo no lo viola). Cambio aditivo: no se borra ni se transforma ningún dato; las
 * columnas `vence_en` y `expirada_en` quedan sin uso y se retiran en la reconsolidación final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->date('ventana_desde')->nullable()->change();
            $table->date('ventana_hasta')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('sol_solicitudes_servicio')->where(fn ($q) => $q->whereNull('ventana_desde')->orWhereNull('ventana_hasta'))->exists()) {
            throw new RuntimeException('Hay solicitudes sin fecha deseada en sol_solicitudes_servicio: no caben en el esquema anterior; ponles fecha antes de revertir esta migración.');
        }

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->date('ventana_desde')->nullable(false)->change();
            $table->date('ventana_hasta')->nullable(false)->change();
        });
    }
};
