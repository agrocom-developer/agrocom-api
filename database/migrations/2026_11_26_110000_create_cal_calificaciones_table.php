<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `cal_calificaciones` (especificación del marketplace §4.7, ADR 0031 y 0039): la
 * calificación bidireccional de un servicio completado. La escribe un solo actor y
 * nunca se edita: moderar cambia el `estado`, no el contenido. El cliente califica
 * al proveedor, al piloto y al auxiliar; el proveedor califica al cliente, en
 * privado. `proveedor_id` y `cliente_id` guardan quién prestó y quién pidió el
 * servicio para calcular la reputación sin recorrer otros módulos.
 *
 * Única por `(solicitud_id, autor_rol, sujeto_tipo, sujeto_id)`: un servicio se
 * califica una vez por sujeto y por lado (la especificación dice por autor; en el
 * lado del proveedor varias cuentas —titular y pilotos— hablan por el mismo
 * proveedor y el servicio se califica una sola vez, ADR 0039).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cal_calificaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_cliente')->unique();
            $table->foreignId('solicitud_id')->constrained('sol_solicitudes_servicio')->restrictOnDelete();
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->foreignId('autor_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('autor_rol', 10);
            $table->string('sujeto_tipo', 10);
            $table->unsignedBigInteger('sujeto_id');
            $table->unsignedSmallInteger('estrellas');
            $table->json('etiquetas');
            $table->text('comentario')->nullable();
            $table->string('estado', 14)->default('visible');
            $table->unsignedBigInteger('moderada_por')->nullable();
            $table->string('causa_moderacion', 30)->nullable();
            $table->text('motivo_moderacion')->nullable();
            $table->dateTime('moderada_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'sujeto_tipo', 'estado', 'id'], 'cal_calificaciones_proveedor_index');
            $table->index(['cliente_id', 'sujeto_tipo', 'estado', 'id'], 'cal_calificaciones_cliente_index');
            $table->index(['estado', 'id'], 'cal_calificaciones_estado_index');
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}cal_calificaciones_sujeto_unico ON {$prefijo}cal_calificaciones (solicitud_id, autor_rol, sujeto_tipo, sujeto_id) WHERE deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}cal_calificaciones
                ADD CONSTRAINT {$prefijo}cal_calificaciones_autor_chk CHECK (autor_rol IN ('cliente', 'proveedor')),
                ADD CONSTRAINT {$prefijo}cal_calificaciones_sujeto_chk CHECK (sujeto_tipo IN ('proveedor', 'piloto', 'auxiliar', 'cliente')),
                ADD CONSTRAINT {$prefijo}cal_calificaciones_estrellas_chk CHECK (estrellas BETWEEN 1 AND 5),
                ADD CONSTRAINT {$prefijo}cal_calificaciones_estado_chk CHECK (estado IN ('visible', 'en_moderacion', 'oculta')),
                ADD CONSTRAINT {$prefijo}cal_calificaciones_direccion_chk CHECK ((autor_rol = 'cliente' AND sujeto_tipo <> 'cliente') OR (autor_rol = 'proveedor' AND sujeto_tipo = 'cliente'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cal_calificaciones');
    }
};
