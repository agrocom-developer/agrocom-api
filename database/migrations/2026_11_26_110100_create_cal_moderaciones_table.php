<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `cal_moderaciones` (política de calificación, «Calificación inmutable»): cada
 * cambio de estado de una calificación deja una fila con quién, por qué y cuándo.
 * Una calificación no se edita: se corrige con una moderación registrada. Solo se
 * inserta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cal_moderaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calificacion_id')->constrained('cal_calificaciones')->restrictOnDelete();
            $table->string('estado_anterior', 14);
            $table->string('estado_nuevo', 14);
            $table->string('causa', 30)->nullable();
            $table->text('motivo');
            $table->foreignId('autor_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('autor_rol', 14);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['calificacion_id', 'id'], 'cal_moderaciones_calificacion_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}cal_moderaciones
                ADD CONSTRAINT {$prefijo}cal_moderaciones_estado_chk CHECK (estado_anterior IN ('visible', 'en_moderacion', 'oculta') AND estado_nuevo IN ('visible', 'en_moderacion', 'oculta')),
                ADD CONSTRAINT {$prefijo}cal_moderaciones_rol_chk CHECK (autor_rol IN ('administrador', 'proveedor'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cal_moderaciones');
    }
};
