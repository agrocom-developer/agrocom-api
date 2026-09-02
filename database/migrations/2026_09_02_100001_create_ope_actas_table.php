<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Operaciones — acta de conformidad por lote (espec §4.3, tabla
 * `actas`, líneas 139/200/314-315; HU-17, tarea 24). Registra el cierre
 * comercial de un trabajo: hectáreas conformadas y la firma del agrónomo
 * (en pantalla, o foto del acta física) — nunca la mezcla ni el reporte
 * técnico completo, eso es HU-18 (tarea aparte).
 *
 * `uuid_cliente`: aunque esta fila no viaja en el lote de `POST /api/sync`
 * (igual que `ope_evidencias`), SÍ nace de una acción disparada desde
 * `agrocom-field` — "Generar y presentar acta" es del piloto/jefe de campo
 * (espec §3), que no tienen pantalla de panel. Invariante 1 de CLAUDE.md
 * aplica igual: el dispositivo genera el uuid al pedir el acta, y ese mismo
 * valor identifica el acta en `POST /api/actas/{uuid_cliente}/firmar` —
 * resuelve que el dispositivo nunca conoce el `id` autoincremental del
 * servidor (ver runs/24.md, "Identificación por uuid_cliente, no por id").
 *
 * `trabajo_id`: `UNIQUE` real (no parcial como `uuid_cliente`) — un trabajo
 * tiene a lo sumo un acta viva; no hay flujo que la borre, así que no hace
 * falta el patrón "parcial por soft delete" del resto del esquema.
 *
 * `hectareas_conformadas`: snapshot de `ope_trabajos.hectareas_declaradas`
 * al momento de GENERAR el acta, nunca recalculado después — una corrección
 * posterior de una sesión no debe mover un acta ya firmada (runs/24.md).
 * Por eso no hay `hectareas_validadas` nueva en `ope_trabajos` (recorte de
 * alcance ya fijado desde la tarea 09): el snapshot vive acá, no allá.
 *
 * `firmante`: nombre del agrónomo, texto libre — no existe tabla de
 * contactos del cliente en este esquema (mismo criterio que
 * `ope_recargas.bateria_saliente_id`, tarea 23: sin catálogo, sin FK
 * inventada). Nullable: se completa recién al firmar.
 *
 * `evidencia_firma_id`: FK real a `ope_evidencias.id` (mismo módulo,
 * relación Eloquent directa — ADR 0003 regla 3), nullable hasta la firma,
 * mismo patrón que `ope_trabajos.imagen_campo_evidencia_id` (tarea 21).
 * Índice único parcial: una firma no debería respaldar dos actas.
 *
 * `pdf_path`: ruta en el disco `r2` (mismo disco que `ope_evidencias`,
 * config/filesystems.php ya lo documenta para "evidencias y reportes") del
 * PDF ya renderizado — no en el diseño original del prompt, agregada porque
 * "un segundo llamado... no regenera el PDF" (CA de la tarea) exige un
 * artefacto persistido que servir, igual que `ope_evidencias.archivo_url`;
 * sin esta columna, "no regenerar" no tendría qué verificar. Ver runs/24.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_actas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_cliente', 36);
            $table->foreignId('trabajo_id')->unique()->constrained('ope_trabajos')->restrictOnDelete();
            $table->decimal('hectareas_conformadas', 10, 2);
            $table->string('pdf_path')->nullable();
            $table->string('firmante')->nullable();
            $table->dateTime('fecha_firma')->nullable();
            $table->foreignId('evidencia_firma_id')->nullable()->constrained('ope_evidencias')->nullOnDelete();
            $table->string('observaciones', 500)->nullable();
            $table->string('estado', 20)->default('pendiente');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        // Idempotencia real (invariante 1): un reintento del mismo pedido de
        // generación choca acá, nunca con un SELECT previo en el caso de uso.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_actas_uuid_cliente_unico
            ON {$prefijo}ope_actas (uuid_cliente)
            WHERE deleted_at IS NULL
        SQL);

        // Mismo criterio preventivo que `ope_trabajos_imagen_campo_evidencia_id_unico`
        // (tarea 21): sin esto, una sola firma podría "respaldar" dos actas.
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_actas_evidencia_firma_id_unico
            ON {$prefijo}ope_actas (evidencia_firma_id)
            WHERE evidencia_firma_id IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_actas
                ADD CONSTRAINT {$prefijo}ope_actas_estado_chk
                    CHECK (estado IN ('pendiente', 'firmada')),
                ADD CONSTRAINT {$prefijo}ope_actas_hectareas_conformadas_chk
                    CHECK (hectareas_conformadas >= 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ope_actas');
    }
};
