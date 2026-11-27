<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Justificación del precio (ADR 0049): texto opcional con que una parte explica su
 * precio. El cliente la escribe en su solicitud y cada autor en su oferta o
 * contraoferta; ninguna fila la edita más de un rol. Solo agrega columnas nulas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['sol_solicitudes_servicio', 'neg_ofertas'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'justificacion_precio')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->string('justificacion_precio', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'justificacion_precio')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('justificacion_precio');
                });
            }
        }
    }
};
