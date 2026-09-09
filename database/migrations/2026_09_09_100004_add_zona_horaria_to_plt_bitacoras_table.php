<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea 63: "el mismo instante en cualquier lugar del mundo" — `created_at`
 * ya es UTC (config/app.php), esta columna agrega la otra mitad: en QUÉ zona
 * IANA estaba el actor cuando ocurrió la mutación (ej. `America/La_Paz`),
 * para que la pantalla de bitácora pueda mostrar tanto la hora del que mira
 * como la hora en que realmente ocurrió, cuando difieren.
 *
 * Nullable para lo ya escrito (no se reescribe historial) y para toda
 * mutación sin actor con preferencia resuelta (seeders, comandos, API de
 * campo sin ese dato todavía) — ver `BitacoraObserver::zonaHorariaDeActor()`.
 * String, no un catálogo: la fuente de verdad de qué identificadores son
 * válidos es `DateTimeZone::listIdentifiers()`, no una tabla propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plt_bitacoras', function (Blueprint $table) {
            $table->string('zona_horaria', 64)->nullable()->after('despues');
        });
    }

    public function down(): void
    {
        Schema::table('plt_bitacoras', function (Blueprint $table) {
            $table->dropColumn('zona_horaria');
        });
    }
};
