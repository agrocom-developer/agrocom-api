<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Solicitudes de eliminar una cuenta de app (ADR 0047, punto 1 y la sección 4.10 de la especificación). Una fila por
 * solicitud: la persona la pide, tiene 7 días para arrepentirse (`ejecutar_desde`) y una tarea diaria la ejecuta
 * (anonimiza en el lugar). Nunca guarda nombre, teléfono, correo ni documento: solo el id de la cuenta, el tipo, las
 * fechas y un resumen de conteos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_eliminaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('tipo_cuenta', 12);
            $table->string('origen', 10)->default('app');
            $table->string('estado', 12)->default('solicitada');
            $table->timestamp('solicitada_en');
            $table->timestamp('ejecutar_desde');
            $table->timestamp('ejecutada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->string('motivo_posposicion', 255)->nullable();
            $table->json('resumen')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'estado'], 'sec_eliminaciones_cuenta_estado');
            $table->index(['estado', 'ejecutar_desde'], 'sec_eliminaciones_pendientes');
            $table->foreign('usuario_id')->references('id')->on('sec_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_eliminaciones');
    }
};
