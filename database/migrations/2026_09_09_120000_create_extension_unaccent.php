<?php

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensión `unaccent` de PostgreSQL, para el buscador global del panel
 * (9/9/2026).
 *
 * El buscador compara `unaccent(columna) ILIKE unaccent(?)`, que es lo que
 * hace que "agricola" encuentre "Agrícola" y al revés — nadie escribe los
 * acentos al buscar apurado, y la mitad de las razones sociales del padrón
 * los tienen.
 *
 * Solo en `pgsql`, mismo criterio que el resto de las migraciones que
 * dependen del motor: la suite corre en SQLite (`phpunit.xml`), que no tiene
 * extensiones ni `unaccent`. Ahí el buscador cae a `LOWER() LIKE` — ver
 * {@see BusquedaEloquent}.
 *
 * `IF NOT EXISTS` porque la extensión es de base, no de tabla: puede venir ya
 * instalada en un entorno donde otra cosa la pidió antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }
    }

    public function down(): void
    {
        // Sin `DROP EXTENSION`: si otra cosa empezó a usar `unaccent`, un
        // rollback de ESTA migración se la llevaría puesta. Dejarla instalada
        // no cuesta nada y no cambia el comportamiento de ninguna consulta que
        // no la nombre.
    }
};
