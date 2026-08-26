<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — campos (propiedades) del cliente (espec §4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_campos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('ubicacion', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });

        $prefijo = DB::getTablePrefix();

        // Nombre único por cliente entre campos activos (índice parcial).
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$prefijo}com_campos_nombre_unico
            ON {$prefijo}com_campos (cliente_id, nombre)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('com_campos');
    }
};
