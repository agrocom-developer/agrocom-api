<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Códigos de un solo uso de las cuentas de app (ADR 0046, puntos 27 a 31): verificar el teléfono, recuperar el
 * acceso, cambiar la contraseña o el teléfono y eliminar la cuenta. Solo guarda el hash del código y el destino
 * enmascarado: ni el código ni el teléfono o correo completos. Es una tabla de credenciales efímeras, sin bitácora
 * de valores: la fila misma registra cuándo se pidió (`created_at`) y cuándo se confirmó (`usado_en`). Un envío fallido la borra
 * (lógico) para no gastar el cupo de la cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_codigos_verificacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('proposito', 30);
            $table->string('canal', 10);
            $table->string('destino_enmascarado', 60);
            $table->string('codigo_hash', 64);
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('vence_en');
            $table->timestamp('usado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index(['usuario_id', 'proposito', 'usado_en'], 'sec_codigos_cuenta_proposito');
            $table->index(['usuario_id', 'created_at'], 'sec_codigos_cuenta_envios');
            $table->foreign('usuario_id')->references('id')->on('sec_user')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_codigos_verificacion');
    }
};
