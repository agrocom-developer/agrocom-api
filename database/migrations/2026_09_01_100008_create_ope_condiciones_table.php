<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — condiciones de vuelo (espec §4.3/§5, HU-06, tarea 17).
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), referencia la
 * sesión por FK real (a diferencia del DTO de sync, que la resuelve por
 * `uuid_cliente`) — para cuando esta migración corre ya existe
 * `ope_sesiones`. `trabajo_id` es redundante con `sesion.trabajo_id`
 * (denormalizado a propósito, mismo motivo que la propia espec lo lista en la
 * tabla `condiciones`: reportar/filtrar por trabajo sin pasar por join).
 *
 * Alcance de esta tarea: `momento` solo admite `inicio_sesion` — `incidencia`
 * es HU-08 (sprint 3), y el `CHECK` de abajo lo refleja tal cual el patrón ya
 * usado para `estado` en `ope_sesiones`/`ope_trabajos` (catálogo que crece
 * con una migración `ADD`/`DROP CONSTRAINT` posterior, nunca editando esta).
 *
 * `autorizado` (bool): TRUE cuando las condiciones cayeron dentro de rango
 * (espec §5: "existe orden de aplicación vigente y condiciones dentro de
 * rango"); FALSE cuando cayeron fuera de rango pero se aceptaron igual por
 * traer observación firmada del agrónomo (espec §5:
 * "autorizado_con_observación"). Un registro fuera de rango SIN observación
 * nunca llega a esta tabla — se rechaza en el motor de sync, no se persiste
 * (ver `EscrituraSincronizacionEloquent::registrarCondiciones()` y
 * runs/17.md). El `CHECK` de abajo blinda esa regla también en la base.
 *
 * Sin `evidencia_id` (TE-07 no existe todavía, espec §4.3 nota "sin evidencia
 * real"): `firma_observacion` es un campo de texto plano, no una FK a una
 * tabla de evidencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_condiciones', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->constrained('ope_trabajos')->restrictOnDelete();
            $table->foreignId('sesion_id')->constrained('ope_sesiones')->restrictOnDelete();
            $table->string('momento', 20)->default('inicio_sesion');
            $table->decimal('viento_kmh', 5, 2);
            $table->decimal('temperatura_c', 5, 2);
            $table->decimal('humedad_pct', 5, 2);
            $table->boolean('autorizado');
            $table->text('observacion_agronomo')->nullable();
            $table->string('firma_observacion')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sesion_id');
            $table->index(['trabajo_id', 'momento']);
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_condiciones_uuid_cliente_unico
            ON {$prefijo}ope_condiciones (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_condiciones
                ADD CONSTRAINT {$prefijo}ope_condiciones_momento_chk
                    CHECK (momento IN ('inicio_sesion')),
                ADD CONSTRAINT {$prefijo}ope_condiciones_viento_chk
                    CHECK (viento_kmh >= 0),
                ADD CONSTRAINT {$prefijo}ope_condiciones_temperatura_chk
                    CHECK (temperatura_c > -10 AND temperatura_c < 60),
                ADD CONSTRAINT {$prefijo}ope_condiciones_humedad_chk
                    CHECK (humedad_pct >= 0 AND humedad_pct <= 100),
                ADD CONSTRAINT {$prefijo}ope_condiciones_observacion_si_no_autorizado_chk
                    CHECK (autorizado = TRUE OR (observacion_agronomo IS NOT NULL AND firma_observacion IS NOT NULL))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_condiciones');
    }
};
