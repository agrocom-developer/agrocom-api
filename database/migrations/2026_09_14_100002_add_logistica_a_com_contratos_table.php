<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — acomodaciones logísticas del contrato (HU-74, tarea 90).
 * El encargado necesita saber, por contrato, qué logística cubre Agrocom
 * (alimentación/hospedaje/combustible del equipo) sin preguntarlo cada vez
 * que arma una estadía.
 *
 * Los tres booleanos van con `default(false)`: a diferencia de
 * `desnivel`/`limpieza` (tarea 89), acá "no cubre" ES el valor real para todo
 * contrato ya cargado, no un "sin cargar" — por eso sí tiene sentido un
 * default, y por eso no son nullable. Sin `CHECK`: no son catálogo cerrado.
 *
 * Puramente aditivo: no toca el motor de sync ni la máquina de estados, y no
 * se usa todavía en ningún cálculo de costeo (eso es alcance de una tarea
 * futura de Finanzas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->boolean('brinda_alimentacion')->default(false)->after('altura_vuelo_m');
            $table->boolean('brinda_hospedaje')->default(false)->after('brinda_alimentacion');
            $table->boolean('brinda_combustible')->default(false)->after('brinda_hospedaje');
            $table->text('observaciones_logistica')->nullable()->after('brinda_combustible');
        });
    }

    public function down(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->dropColumn(['brinda_alimentacion', 'brinda_hospedaje', 'brinda_combustible', 'observaciones_logistica']);
        });
    }
};
