<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad — roles (ADR 0004, HU-01 diseño `modulos-roles` §5).
 *
 * `name` es UNIQUE plano (no parcial): es un catálogo de sistema
 * (piloto, auxiliar, jefe_campo, encargado_operaciones, dueno), no un
 * registro operativo que se libera al dar de baja (diseño §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_role', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('description', 150);
            $table->boolean('state')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_role');
    }
};
