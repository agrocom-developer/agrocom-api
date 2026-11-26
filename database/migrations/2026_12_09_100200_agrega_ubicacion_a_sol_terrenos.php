<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación del terreno (ADR 0045, punto 3): departamento y provincia del catálogo
 * oficial (seleccionables; obligatorios para publicar una solicitud sobre el
 * terreno, no para registrarlo) y municipio y comunidad como texto libre de
 * referencia. Aditiva: `zona_id` sigue hasta que se retire la zona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sol_terrenos', function (Blueprint $table) {
            $table->string('departamento_codigo', 2)->nullable();
            $table->string('provincia_codigo', 4)->nullable();
            $table->string('municipio', 120)->nullable();
            $table->string('comunidad', 120)->nullable();
        });

        // Solo pgsql: SQLite no soporta ADD CONSTRAINT (tests/Unit no migran).
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_terrenos ADD CONSTRAINT {$prefijo}sol_terrenos_departamento_fk FOREIGN KEY (departamento_codigo) REFERENCES {$prefijo}sol_departamentos (codigo) ON DELETE RESTRICT");
            // Llave compuesta (MATCH SIMPLE): la provincia debe ser del departamento elegido.
            DB::statement("ALTER TABLE {$prefijo}sol_terrenos ADD CONSTRAINT {$prefijo}sol_terrenos_provincia_fk FOREIGN KEY (departamento_codigo, provincia_codigo) REFERENCES {$prefijo}sol_provincias (departamento_codigo, codigo) ON DELETE RESTRICT");
            DB::statement("ALTER TABLE {$prefijo}sol_terrenos ADD CONSTRAINT {$prefijo}sol_terrenos_provincia_con_departamento_chk CHECK (provincia_codigo IS NULL OR departamento_codigo IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement("ALTER TABLE {$prefijo}sol_terrenos DROP CONSTRAINT IF EXISTS {$prefijo}sol_terrenos_provincia_con_departamento_chk");
            DB::statement("ALTER TABLE {$prefijo}sol_terrenos DROP CONSTRAINT IF EXISTS {$prefijo}sol_terrenos_provincia_fk");
            DB::statement("ALTER TABLE {$prefijo}sol_terrenos DROP CONSTRAINT IF EXISTS {$prefijo}sol_terrenos_departamento_fk");
        }

        Schema::table('sol_terrenos', function (Blueprint $table) {
            $table->dropColumn(['departamento_codigo', 'provincia_codigo', 'municipio', 'comunidad']);
        });
    }
};
