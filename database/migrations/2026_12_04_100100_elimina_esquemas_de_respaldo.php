<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina los esquemas `respaldo_*` que dejaron los retiros anteriores
 * (operación interna, contratos y órdenes, y el paso al modelo de negociación).
 * El sistema es nuevo y no hay datos que conservar: ninguna parte de la
 * aplicación lee esos esquemas.
 *
 * Es destructiva a propósito. `down()` solo recrea los esquemas vacíos; los datos
 * no vuelven. Solo corre en PostgreSQL (en SQLite no hay esquemas).
 */
return new class extends Migration
{
    private const ESQUEMAS = [
        'respaldo_operacion_interna',
        'respaldo_contratos_y_ordenes',
        'respaldo_estados',
        'respaldo_comision',
        'respaldo_asignacion',
        'respaldo_disponibilidad',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::ESQUEMAS as $esquema) {
            DB::statement("DROP SCHEMA IF EXISTS {$esquema} CASCADE");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::ESQUEMAS as $esquema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");
        }
    }
};
