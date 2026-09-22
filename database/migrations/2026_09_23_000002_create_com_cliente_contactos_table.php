<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `com_cliente_contactos` — estado consolidado del esquema al 2026_09_23 (ADR 0024). Módulo COM.
 * Reemplaza a las migraciones legadas que la crearon y modificaron:
 *   - 2026_09_23_000002_create_com_cliente_contactos_table
 *
 * Una base que ya pasó por esas migraciones tiene la tabla: la guarda de
 * `hasTable` la deja como está y solo registra esta migración como aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_cliente_contactos')) {
            return;
        }

        Schema::create('com_cliente_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('com_clientes')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->string('nombre', 150);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('tipo_otro', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cliente_id'], 'com_cliente_contactos_cliente_id_index');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_cliente_contactos
                ADD CONSTRAINT {$prefijo}com_cliente_contactos_tipo_chk CHECK (tipo IN ('dueno', 'agronomo', 'encargado_propiedad', 'otro', 'gerente_general', 'finanzas', 'secretario'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_cliente_contactos');
    }
};
