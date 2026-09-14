<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-91 (tarea 106): el contrato deja de pedir `adelanto_pct` (nunca se pidió
 * en la práctica) y toda la sección "Parámetros de vuelo" — esos 7 límites
 * pasan a heredar siempre de la Orden o del valor por defecto del sistema
 * (RF-60), nunca del contrato. Confirmado con el dueño el 14/9/2026
 * (docs/negocio/observaciones_operaciones_comercial_2026-09-14.md §1 y §2).
 *
 * `com_contrato_ventanas` no se toca: la mención a "ventana de aplicación"
 * del documento original era la misma instrucción de sacar parámetros de
 * vuelo, no una reubicación.
 *
 * `ope_ordenes_aplicacion` tampoco: tiene sus PROPIAS columnas de clima/vuelo
 * a nivel de orden, que ahora son el único nivel que manda.
 *
 * Sin migración de datos: el dueño confirmó que `adelanto_pct` nunca estuvo
 * pedido, no hay nada que preservar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contratos
                DROP CONSTRAINT {$prefijo}com_contratos_adelanto_pct_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_viento_max_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_temperatura_max_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_humedad_min_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_humedad_max_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_humedad_rango_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_velocidad_max_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_umbral_reporte_chk,
                DROP CONSTRAINT {$prefijo}com_contratos_altura_vuelo_chk
            SQL);
        }

        Schema::table('com_contratos', function (Blueprint $table) {
            $table->dropColumn([
                'adelanto_pct',
                'viento_max_kmh',
                'temperatura_max_c',
                'humedad_min_pct',
                'humedad_max_pct',
                'velocidad_max_kmh',
                'umbral_reporte_avance_ha',
                'altura_vuelo_m',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('com_contratos', function (Blueprint $table) {
            $table->decimal('adelanto_pct', 5, 2)->nullable();
            $table->decimal('viento_max_kmh', 5, 2)->nullable();
            $table->decimal('temperatura_max_c', 5, 2)->nullable();
            $table->decimal('humedad_min_pct', 5, 2)->nullable();
            $table->decimal('humedad_max_pct', 5, 2)->nullable();
            $table->decimal('velocidad_max_kmh', 5, 2)->nullable();
            $table->decimal('umbral_reporte_avance_ha', 10, 2)->nullable();
            $table->decimal('altura_vuelo_m', 5, 2)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_contratos
                ADD CONSTRAINT {$prefijo}com_contratos_adelanto_pct_chk
                    CHECK (adelanto_pct IS NULL OR (adelanto_pct >= 0 AND adelanto_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_viento_max_chk
                    CHECK (viento_max_kmh IS NULL OR viento_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_temperatura_max_chk
                    CHECK (temperatura_max_c IS NULL OR (temperatura_max_c > -10 AND temperatura_max_c < 60)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_min_chk
                    CHECK (humedad_min_pct IS NULL OR (humedad_min_pct >= 0 AND humedad_min_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_max_chk
                    CHECK (humedad_max_pct IS NULL OR (humedad_max_pct >= 0 AND humedad_max_pct <= 100)),
                ADD CONSTRAINT {$prefijo}com_contratos_humedad_rango_chk
                    CHECK (humedad_min_pct IS NULL OR humedad_max_pct IS NULL OR humedad_min_pct <= humedad_max_pct),
                ADD CONSTRAINT {$prefijo}com_contratos_velocidad_max_chk
                    CHECK (velocidad_max_kmh IS NULL OR velocidad_max_kmh > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_umbral_reporte_chk
                    CHECK (umbral_reporte_avance_ha IS NULL OR umbral_reporte_avance_ha > 0),
                ADD CONSTRAINT {$prefijo}com_contratos_altura_vuelo_chk
                    CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0)
            SQL);
        }
    }
};
