<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación congelada de la solicitud (ADR 0045, punto 3): al publicar copia el
 * departamento y la provincia del catálogo oficial y el municipio y la comunidad
 * (texto de referencia) del terreno, para que lo que ve el proveedor no cambie si
 * el cliente corrige el terreno después. Nulas en `borrador`. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->string('departamento_codigo', 2)->nullable();
            $table->string('provincia_codigo', 4)->nullable();
            $table->string('municipio', 120)->nullable();
            $table->string('comunidad', 120)->nullable();
            // El departamento es el único filtro de ubicación (ADR 0045, punto 3).
            $table->index('departamento_codigo', 'sol_solicitudes_departamento_idx');
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_departamento_fk FOREIGN KEY (departamento_codigo) REFERENCES {$prefijo}sol_departamentos (codigo) ON DELETE RESTRICT");
            // Llave compuesta (MATCH SIMPLE): la provincia debe ser del departamento elegido.
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_provincia_fk FOREIGN KEY (departamento_codigo, provincia_codigo) REFERENCES {$prefijo}sol_provincias (departamento_codigo, codigo) ON DELETE RESTRICT");
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio ADD CONSTRAINT {$prefijo}sol_solicitudes_servicio_provincia_con_departamento_chk CHECK (provincia_codigo IS NULL OR departamento_codigo IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT IF EXISTS {$prefijo}sol_solicitudes_servicio_provincia_con_departamento_chk");
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT IF EXISTS {$prefijo}sol_solicitudes_servicio_provincia_fk");
            DB::statement("ALTER TABLE {$prefijo}sol_solicitudes_servicio DROP CONSTRAINT IF EXISTS {$prefijo}sol_solicitudes_servicio_departamento_fk");
        }

        Schema::table('sol_solicitudes_servicio', function (Blueprint $table) {
            $table->dropIndex('sol_solicitudes_departamento_idx');
            $table->dropColumn(['departamento_codigo', 'provincia_codigo', 'municipio', 'comunidad']);
        });
    }
};
