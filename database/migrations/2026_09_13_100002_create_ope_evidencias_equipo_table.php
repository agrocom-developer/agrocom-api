<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — "Reporte de Equipos" (ronda del dueño, 13/9/2026;
 * HU-80, tarea 86; espec `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`).
 * Registra el HECHO puntual de que el equipo (dron/batería) quedó
 * inspeccionado al cierre de un trabajo — mismo criterio que
 * `Condiciones`/`RecepcionCaldo`/`Recarga`: sin máquina de estados propia.
 *
 * Nace en la app de campo con su `uuid_cliente` (invariante 1), un registro
 * por trabajo (`trabajo_id` `UNIQUE`, mismo criterio que
 * `ope_reportes_tecnicos.trabajo_id`: el "Reporte de Equipos" es una sola
 * foto del estado del equipo al cerrar ese trabajo, no un historial).
 *
 * `horas_vuelo_dron`: DECLARADO por el auxiliar/piloto al cerrar, no
 * calculado — a diferencia de
 * `Operaciones\Contratos\LecturaHorasVueloPorModelo` (que SÍ se recalcula
 * siempre desde las sesiones), este es un dato de evidencia puntual pedido
 * explícitamente por el dueño ("horas de vuelo del dron" en la sección
 * "Reporte de Equipos" del Word), sin pretensión de cuadrar con la suma de
 * sesiones.
 *
 * Los tres `foto_*_id` referencian `ope_evidencias.id` con FK real (mismo
 * criterio que `ope_incidencias.evidencia_foto_id`: ambas tablas viven en
 * `Operaciones`, ADR 0003 regla 3 no aplica) y NOT NULL: el Word pide las
 * tres fotos como parte del mismo chequeo ("foto de control, foto de cada
 * ciclo de batería y balanceo, foto de dron limpio"), ninguna es opcional —
 * `Aplicacion/EscrituraSincronizacionEloquent::registrarEvidenciaEquipo()`
 * rechaza el registro completo si falta cualquiera de las tres.
 *
 * Tres índices únicos parciales, uno por columna de foto, mismo motivo que
 * `ope_incidencias_evidencia_foto_id_unico`: una misma foto no puede
 * respaldar dos registros de equipo distintos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_evidencias_equipo', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->unique()->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('horas_vuelo_dron', 6, 2);
            $table->foreignId('foto_control_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->foreignId('foto_ciclo_bateria_balanceo_id')->constrained('ope_evidencias')->restrictOnDelete();
            $table->foreignId('foto_dron_limpio_id')->constrained('ope_evidencias')->restrictOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo lote choca
        // acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_uuid_cliente_unico
            ON {$prefijo}ope_evidencias_equipo (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_control_unico
            ON {$prefijo}ope_evidencias_equipo (foto_control_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_ciclo_bateria_unico
            ON {$prefijo}ope_evidencias_equipo (foto_ciclo_bateria_balanceo_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_evidencias_equipo_foto_dron_limpio_unico
            ON {$prefijo}ope_evidencias_equipo (foto_dron_limpio_id)
            WHERE deleted_at IS NULL
        SQL);

        // Rangos en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_evidencias_equipo
                ADD CONSTRAINT {$prefijo}ope_evidencias_equipo_horas_vuelo_chk
                    CHECK (horas_vuelo_dron >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_evidencias_equipo');
    }
};
