<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — contactos del cliente (insumos §4).
 *
 * Reemplaza las columnas "contacto dueño / contacto agrónomo" de la espec §4.1
 * por una tabla en 3FN, e incorpora el hallazgo de campo: el "encargado de la
 * propiedad" (indica lotes, prepara caldo, ordena pausas — opera mucho, no
 * firma nada). El agrónomo de esta tabla es quien emite órdenes y firma actas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('com_cliente_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('tipo', 30)->comment('dueno | agronomo | encargado_propiedad | otro');
            $table->string('nombre', 150);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('observaciones')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });

        // CHECK de enum en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT;
        // el motor real del proyecto es PostgreSQL 16.
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_cliente_contactos
                ADD CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk
                CHECK (tipo IN ('dueno', 'agronomo', 'encargado_propiedad', 'otro'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_cliente_contactos');
    }
};
