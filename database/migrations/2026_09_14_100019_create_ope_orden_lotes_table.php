<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-92 (tarea 107): la Orden de Aplicación pasa a cubrir varios lotes de la
 * propiedad (docs/negocio/observaciones_operaciones_comercial_2026-09-14.md
 * §4) — amplía HU-70 (PR #189), que asumía 1 orden = 1 lote.
 *
 * `orden_id` en `cascadeOnDelete` (mismo criterio que
 * `mez_mezcla_detalles.mezcla_id`): el detalle no es una entidad
 * independiente referenciada desde otro lado, no tiene sentido sin su
 * cabecera. `lote_id` en `restrictOnDelete`, mismo criterio que tenía
 * `ope_ordenes_aplicacion.lote_id` (esta migración se lo saca, ver abajo):
 * un lote referenciado por una orden no se borra lógicamente por debajo.
 * `hectareas_solicitadas` reemplaza, por lote, lo que antes tapaba
 * `com_lotes.hectareas` completo como tope de `AsignarEquiposOrden` — con
 * varios lotes por orden, cada uno pide su propia porción.
 *
 * Con soft delete (mismo criterio que `com_contrato_ventanas` y
 * `mez_mezcla_detalles`, las dos tablas de detalle comparables del repo).
 *
 * "Una única orden vigente por lote" (antes, índice parcial sobre
 * `ope_ordenes_aplicacion.lote_id`) ya NO se puede expresar como índice de
 * Postgres: con N lotes por orden, la regla cruza esta tabla (`lote_id`) con
 * `ope_ordenes_aplicacion.estado` — un índice parcial no puede condicionar
 * sobre una tabla ajena. La guarda se mueve a
 * `MaquinaEstadosOrden::activar()` (verificación explícita + lock, dentro de
 * la misma transacción, ver su docblock) — por eso acá se DROPEA el índice
 * parcial viejo (`ope_ordenes_aplicacion_lote_vigente_unico`) junto con la
 * columna que lo sostenía.
 *
 * `ope_ordenes_aplicacion.lote_id` se ELIMINA (no se deja nullable/muerta):
 * dejarla viva sin uso habría sido una segunda fuente de verdad sobre qué
 * lote cubre la orden, exactamente lo que la tarea prohíbe. Antes de
 * borrarla, cada orden existente migra su valor a una fila de
 * `ope_orden_lotes` con `hectareas_solicitadas = com_lotes.hectareas` —el
 * mismo tope que usaba `AsignarEquiposOrden` hasta ahora, para no perder esa
 * semántica en las órdenes ya cargadas.
 *
 * De paso, suma `ope_ordenes_aplicacion.cantidad_equipos_necesarios`
 * (`DEFAULT 1`): cuántos equipos hacen falta para cubrir la orden — la
 * pantalla de reparto la usa para decidir si ofrece la confirmación de un
 * solo equipo (todas las hectáreas de todos los lotes) o la de N equipos
 * (cada uno elige sus lotes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ope_orden_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ope_ordenes_aplicacion')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('com_lotes')->restrictOnDelete();
            $table->decimal('hectareas_solicitadas', 10, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('orden_id');
            $table->index('lote_id');
        });

        $prefijo = DB::getTablePrefix();

        // Un lote no se repite dentro de la misma orden (índice parcial,
        // mismo criterio que `com_contrato_ventanas_unicas`).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_orden_lotes_orden_lote_unico
            ON {$prefijo}ope_orden_lotes (orden_id, lote_id)
            WHERE deleted_at IS NULL
        SQL);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_orden_lotes
                ADD CONSTRAINT {$prefijo}ope_orden_lotes_hectareas_chk
                    CHECK (hectareas_solicitadas > 0)
            SQL);
        }

        // Migración de datos: cada orden existente conserva su único lote de
        // hoy como su primera (y por ahora única) fila de detalle, con el
        // mismo tope de hectáreas que ya usaba `AsignarEquiposOrden`
        // (`com_lotes.hectareas` completo).
        DB::table('ope_ordenes_aplicacion')
            ->select('id', 'lote_id')
            ->orderBy('id')
            ->chunkById(200, function ($ordenes): void {
                $ahora = now();

                foreach ($ordenes as $orden) {
                    $hectareas = DB::table('com_lotes')->where('id', $orden->lote_id)->value('hectareas') ?? '0';

                    DB::table('ope_orden_lotes')->insert([
                        'orden_id' => $orden->id,
                        'lote_id' => $orden->lote_id,
                        'hectareas_solicitadas' => $hectareas,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            });

        // El índice parcial viejo depende de `lote_id`: se dropea explícito
        // antes de la columna (ver docblock) en vez de confiar en el cascade
        // del motor, mismo criterio defensivo que el resto del repo.
        DB::statement("DROP INDEX IF EXISTS {$prefijo}ope_ordenes_aplicacion_lote_vigente_unico");

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropIndex(['lote_id', 'estado']);
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });

        // Cuántos equipos hacen falta para cubrir la orden (HU-92): informativo
        // para la pantalla de reparto (con 1, se asigna el total de una — con
        // 2+, cada equipo elige sus lotes y hectáreas, ver
        // `AsignacionEquiposController`). `DEFAULT 1`, no nullable: toda orden
        // existente antes de esta tarea era, de hecho, de un solo equipo.
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad_equipos_necesarios')->default(1)->after('nro_aplicacion');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ope_ordenes_aplicacion
                ADD CONSTRAINT {$prefijo}ope_ordenes_aplicacion_cantidad_equipos_chk
                    CHECK (cantidad_equipos_necesarios >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->dropColumn('cantidad_equipos_necesarios');
        });

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->foreignId('lote_id')->nullable()->after('contrato_id')->constrained('com_lotes')->restrictOnDelete();
        });

        DB::table('ope_orden_lotes')
            ->select('orden_id', 'lote_id')
            ->orderBy('id')
            ->chunkById(200, function ($filas): void {
                foreach ($filas as $fila) {
                    DB::table('ope_ordenes_aplicacion')->where('id', $fila->orden_id)->update(['lote_id' => $fila->lote_id]);
                }
            });

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}ope_ordenes_aplicacion_lote_vigente_unico
            ON {$prefijo}ope_ordenes_aplicacion (lote_id)
            WHERE estado = 'vigente' AND deleted_at IS NULL
        SQL);

        Schema::table('ope_ordenes_aplicacion', function (Blueprint $table) {
            $table->index(['lote_id', 'estado']);
        });

        Schema::dropIfExists('ope_orden_lotes');
    }
};
