<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `asg_sanciones` (política de calificación y de cancelación, ADR 0038): lo que
 * el motor le aplica a un proveedor por sus tasas y su calificación. Una
 * `advertencia` solo avisa; una `restriccion_ofertas` lo deja sin ofertas hasta
 * `hasta` (o hasta que un administrador la levante, si no tiene fecha); una
 * `revision_suspension` deja pendiente una decisión del administrador. Ninguna
 * da de baja ni suspende la cuenta: eso lo decide una persona (ADR 0032).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asg_sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('prv_proveedores')->restrictOnDelete();
            $table->string('tipo', 22);
            $table->string('causa', 24);
            $table->text('motivo');
            $table->dateTime('desde');
            $table->dateTime('hasta')->nullable();
            $table->dateTime('levantada_en')->nullable();
            $table->unsignedBigInteger('levantada_por')->nullable();
            $table->text('levantada_motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proveedor_id', 'tipo', 'desde'], 'asg_sanciones_proveedor_tipo_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}asg_sanciones
                ADD CONSTRAINT {$prefijo}asg_sanciones_tipo_chk CHECK (tipo IN ('advertencia', 'restriccion_ofertas', 'revision_suspension')),
                ADD CONSTRAINT {$prefijo}asg_sanciones_causa_chk CHECK (causa IN ('cancelaciones', 'calificacion', 'aceptacion'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asg_sanciones');
    }
};
