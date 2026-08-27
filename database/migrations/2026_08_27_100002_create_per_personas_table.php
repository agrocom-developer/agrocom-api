<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Personal — personas (espec §4.2), alcance mínimo para HU-01
 * (ADR 0011, extensión 26/8/2026, punto 6): id, nombre, rol, base_id, activo.
 *
 * `rol` es la clasificación operativa única de la persona (piloto, auxiliar,
 * jefe_campo, encargado_operaciones, dueno) — NO confundir con `sec_role`,
 * que gobierna permisos y admite varios roles por usuario vía sec_user_role
 * (HU-01, diseño `modulos-roles`, §5).
 *
 * `tarifa_ha` y `sueldo_mensual` quedan diferidos a cuando se implemente
 * devengos/planilla (ADR 0011): son DECIMAL de dinero sin la lógica de
 * cálculo detrás todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('rol', 30);
            $table->foreignId('base_id')
                ->nullable()
                ->constrained('per_bases')
                ->restrictOnDelete();
            $table->boolean('activo')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('base_id');
        });

        $prefijo = DB::getTablePrefix();

        // Rangos y enums en la base, no solo en la aplicación (ADR 0001).
        // Solo pgsql: SQLite (tests locales rápidos) no soporta ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}per_personas
                ADD CONSTRAINT {$prefijo}per_personas_rol_chk
                    CHECK (rol IN ('piloto', 'auxiliar', 'jefe_campo', 'encargado_operaciones', 'dueno'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('per_personas');
    }
};
