<?php

use App\Dominios\Comercial\Dominio\ColorPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial — cierra el catálogo geográfico de `com_propiedades`
 * (adenda 16/9/2026 a ADR 0018 punto 1): `departamento`/`municipio` de texto
 * libre (HU-76) se reemplazan por FK al catálogo cerrado de Bolivia
 * (`com_departamentos`/`com_provincias`/`com_municipios`, sembrado por
 * `GeografiaBoliviaSeeder`). `localidad` NO gana catálogo — sigue siendo
 * texto libre, dentro del municipio elegido, porque no hay datos de esa
 * granularidad. `ubicacion` (texto libre histórico, ADR 0018 original) se
 * elimina sin reemplazo: quedó completamente cubierta por la ubicación
 * estructurada.
 *
 * Las 3 FK son nullable: el texto libre existente no se puede mapear
 * automáticamente al catálogo nuevo — las propiedades demo actuales quedan
 * sin departamento/provincia/municipio hasta reasignarlas a mano desde el
 * formulario.
 *
 * `color`: paleta curada (ver `App\Dominios\Comercial\Dominio\ColorPropiedad`),
 * dato de negocio elegido por el usuario para distinguir propiedades de
 * distintos clientes en listados y mapas — sus lotes lo heredan visualmente
 * leyendo `propiedad->color`, nunca duplicado en `com_lotes`. El CHECK
 * (pgsql-only) restringe a los valores de esa paleta, mismo patrón que el
 * CHECK de rango de `latitud`/`longitud` que ya tiene esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable()->after('nombre')
                ->constrained('com_departamentos')->restrictOnDelete();
            $table->foreignId('provincia_id')->nullable()->after('departamento_id')
                ->constrained('com_provincias')->restrictOnDelete();
            $table->foreignId('municipio_id')->nullable()->after('provincia_id')
                ->constrained('com_municipios')->restrictOnDelete();
            $table->string('color', 7)->nullable()->after('municipio_id');

            $table->dropColumn(['ubicacion', 'departamento', 'municipio']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();
            $listaColores = collect(ColorPropiedad::cases())
                ->map(fn ($color) => "'{$color->value}'")
                ->implode(', ');

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_propiedades
                ADD CONSTRAINT {$prefijo}com_propiedades_color_chk
                    CHECK (color IS NULL OR color IN ({$listaColores}))
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $prefijo = DB::getTablePrefix();

            DB::statement(<<<SQL
                ALTER TABLE {$prefijo}com_propiedades
                DROP CONSTRAINT {$prefijo}com_propiedades_color_chk
            SQL);
        }

        Schema::table('com_propiedades', function (Blueprint $table) {
            $table->string('ubicacion', 255)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('municipio', 100)->nullable();

            $table->dropConstrainedForeignId('municipio_id');
            $table->dropConstrainedForeignId('provincia_id');
            $table->dropConstrainedForeignId('departamento_id');
            $table->dropColumn('color');
        });
    }
};
