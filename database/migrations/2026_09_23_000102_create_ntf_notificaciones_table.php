<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ntf_notificaciones` — tarea 141, motor de notificaciones interno (ADR 0025).
 * Módulo NTF (prefijo `ntf_`, ADR 0011).
 *
 * Una fila por cuenta destinataria, creada cuando ocurre el hecho: el estado
 * de lectura es de cada cuenta, así que el aviso se reparte al emitir y no se
 * calcula al mirar.
 *
 * - `clave_evento` es la identidad del hecho (`contrato_creado:12`). Junto con
 *   `usuario_id` forma el índice ÚNICO que da la idempotencia: correr el
 *   listener dos veces sobre el mismo evento no repite el aviso. El índice NO
 *   es parcial (`WHERE deleted_at IS NULL`, como los demás del repo) a
 *   propósito: si una cuenta descartó el aviso, un reintento del evento no lo
 *   resucita.
 * - `recurso_tipo` + `recurso_id` dicen a qué lleva el click. No hay FK: apunta
 *   a tablas de varios módulos y el aviso tiene que sobrevivir a que el
 *   recurso se dé de baja. La URL no se guarda: se resuelve al abrir, contra
 *   el rol activo (ADR 0025, punto 6).
 * - `parametros` (jsonb) es lo que el texto del aviso necesita (cliente,
 *   hectáreas, número de aplicación), tomado del payload del evento — este
 *   módulo nunca consulta las tablas ajenas para armarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ntf_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('clave_evento', 120);
            $table->jsonb('parametros')->nullable();
            $table->string('recurso_tipo', 30);
            $table->unsignedBigInteger('recurso_id');
            $table->dateTime('leida_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['usuario_id', 'clave_evento'], 'ntf_notificaciones_evento_unico');
            $table->index(['usuario_id', 'leida_en', 'created_at'], 'ntf_notificaciones_usuario_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}ntf_notificaciones
                ADD CONSTRAINT {$prefijo}ntf_notificaciones_tipo_chk CHECK (tipo IN ('contrato_creado', 'orden_trabajo_creada', 'trabajo_cerrado')),
                ADD CONSTRAINT {$prefijo}ntf_notificaciones_recurso_tipo_chk CHECK (recurso_tipo IN ('contrato', 'orden_trabajo', 'trabajo'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ntf_notificaciones');
    }
};
