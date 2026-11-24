<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de la negociación en la solicitud (`negociacion-contrato.md` §1 y §3):
 * precio por hectárea propuesto y acordado, vigencia, avance y los momentos del
 * ciclo. También la macrorregión de las zonas, el año del dron y los datos de la
 * persona del proveedor (§9). Solo agrega: nada se borra. Cada tabla se toca solo
 * si existe, para que las pruebas que levantan un esquema parcial puedan correrla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sol_solicitudes_servicio')) {
            Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
                $table->decimal('precio_ha_propuesto', 14, 4)->nullable();
                $table->decimal('precio_ha_acordado', 14, 4)->nullable();
                $table->unsignedBigInteger('oferta_adjudicada_id')->nullable();
                $table->decimal('hectareas_ejecutadas', 12, 4)->nullable();
                $table->decimal('porcentaje_avance', 5, 2)->default(0);
                $table->dateTime('vence_en')->nullable();
                $table->dateTime('adjudicada_en')->nullable();
                $table->dateTime('iniciada_en')->nullable();
                $table->dateTime('cierre_solicitado_en')->nullable();
                $table->dateTime('finalizada_en')->nullable();
                $table->dateTime('expirada_en')->nullable();
                $table->dateTime('ultimo_avance_en')->nullable();
                $table->index(['estado', 'vence_en'], 'sol_solicitudes_servicio_estado_vence_index');
            });
        }

        if (Schema::hasTable('sol_zonas')) {
            Schema::table('sol_zonas', function (Blueprint $table) {
                $table->string('macrorregion', 20)->nullable();
            });
        }

        if (Schema::hasTable('flo_drones')) {
            Schema::table('flo_drones', function (Blueprint $table) {
                $table->unsignedSmallInteger('anio')->nullable();
            });
        }

        if (Schema::hasTable('prv_proveedores')) {
            Schema::table('prv_proveedores', function (Blueprint $table) {
                $table->string('nombres', 80)->nullable();
                $table->string('apellidos', 80)->nullable();
                $table->string('documento_identidad', 30)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->string('foto_perfil', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('prv_proveedores', function (Blueprint $table) {
            $table->dropColumn(['nombres', 'apellidos', 'documento_identidad', 'fecha_nacimiento', 'foto_perfil']);
        });
        Schema::table('flo_drones', function (Blueprint $table) {
            $table->dropColumn('anio');
        });
        Schema::table('sol_zonas', function (Blueprint $table) {
            $table->dropColumn('macrorregion');
        });
        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->dropIndex('sol_solicitudes_servicio_estado_vence_index');
            $table->dropColumn([
                'precio_ha_propuesto', 'precio_ha_acordado', 'oferta_adjudicada_id', 'hectareas_ejecutadas', 'porcentaje_avance',
                'vence_en', 'adjudicada_en', 'iniciada_en', 'cierre_solicitado_en', 'finalizada_en', 'expirada_en', 'ultimo_avance_en',
            ]);
        });
    }
};
