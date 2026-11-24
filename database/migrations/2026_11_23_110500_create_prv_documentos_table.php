<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `prv_documentos` (módulo Proveedores, especificación del marketplace §4.3,
 * política de verificación): documentos con vencimiento de un proveedor,
 * piloto, auxiliar o dron. `sujeto_tipo` + `sujeto_id` apuntan a la tabla del
 * sujeto sin FK (cuatro tablas de dos módulos). El archivo vive en el disco
 * privado, bajo la ruta por objeto del ADR 0026 (`archivo_ref`); nunca se
 * sirve por URL pública.
 *
 * `aviso_30_en` y `aviso_7_en` registran cuándo se envió cada aviso previo al
 * vencimiento, para que el proceso diario no repita el aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prv_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('sujeto_tipo', 12);
            $table->unsignedBigInteger('sujeto_id');
            $table->string('tipo', 30);
            $table->string('archivo_ref', 255);
            $table->string('nombre_original', 150)->nullable();
            $table->string('mime', 60);
            $table->unsignedBigInteger('bytes');
            $table->date('vence_en')->nullable();
            $table->string('estado', 12)->default('en_revision');
            $table->foreignId('subido_por')->constrained('sec_user')->restrictOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('sec_user')->restrictOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->date('aviso_30_en')->nullable();
            $table->date('aviso_7_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sujeto_tipo', 'sujeto_id', 'tipo'], 'prv_documentos_sujeto_tipo_index');
            $table->index(['estado', 'vence_en'], 'prv_documentos_estado_vence_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}prv_documentos
                ADD CONSTRAINT {$prefijo}prv_documentos_sujeto_tipo_chk CHECK (sujeto_tipo IN ('proveedor', 'piloto', 'auxiliar', 'dron')),
                ADD CONSTRAINT {$prefijo}prv_documentos_tipo_chk CHECK (tipo IN ('identidad', 'licencia_piloto', 'habilitacion_dron', 'seguro_responsabilidad', 'registro_dron', 'certificado_fumigacion', 'antecedentes', 'otro')),
                ADD CONSTRAINT {$prefijo}prv_documentos_estado_chk CHECK (estado IN ('en_revision', 'aprobado', 'rechazado', 'vencido'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prv_documentos');
    }
};
