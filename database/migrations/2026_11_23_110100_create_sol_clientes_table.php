<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sol_clientes` (especificación del marketplace §4.1): quien solicita el
 * servicio. Es la ficha de negocio de una cuenta `sec_user` con el rol
 * `cliente`; se crea en el registro público (ADR 0036). La calificación
 * promedio la mantendrá el módulo de calificaciones: nace en cero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sol_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('sec_user')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->string('razon_social', 150);
            $table->string('documento_fiscal', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('estado', 12)->default('activo');
            $table->decimal('calificacion_promedio', 3, 2)->default(0);
            $table->unsignedInteger('calificaciones_conteo')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $prefijo = DB::getTablePrefix();

        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_clientes_usuario_unico ON {$prefijo}sol_clientes (usuario_id) WHERE deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX {$prefijo}sol_clientes_documento_unico ON {$prefijo}sol_clientes (documento_fiscal) WHERE documento_fiscal IS NOT NULL AND deleted_at IS NULL");

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}sol_clientes
                ADD CONSTRAINT {$prefijo}sol_clientes_tipo_chk CHECK (tipo IN ('persona', 'empresa')),
                ADD CONSTRAINT {$prefijo}sol_clientes_estado_chk CHECK (estado IN ('activo', 'suspendido')),
                ADD CONSTRAINT {$prefijo}sol_clientes_calificacion_chk CHECK (calificacion_promedio BETWEEN 0 AND 5)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sol_clientes');
    }
};
