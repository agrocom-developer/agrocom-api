<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dsp_mensajes` (especificación del marketplace §4.8): la conversación de una
 * disputa entre el cliente, el proveedor y soporte. Solo se inserta. Una
 * `apelacion` es el mensaje con que una parte reabre la disputa una vez, con
 * pruebas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disputa_id')->constrained('dsp_disputas')->restrictOnDelete();
            $table->foreignId('autor_usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('autor_rol', 10);
            $table->string('tipo', 12)->default('mensaje');
            $table->text('texto');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['disputa_id', 'id'], 'dsp_mensajes_disputa_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}dsp_mensajes
                ADD CONSTRAINT {$prefijo}dsp_mensajes_rol_chk CHECK (autor_rol IN ('cliente', 'proveedor', 'soporte')),
                ADD CONSTRAINT {$prefijo}dsp_mensajes_tipo_chk CHECK (tipo IN ('mensaje', 'informacion', 'apelacion'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_mensajes');
    }
};
