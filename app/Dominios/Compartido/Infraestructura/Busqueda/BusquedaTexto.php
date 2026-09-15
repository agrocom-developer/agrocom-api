<?php

namespace App\Dominios\Compartido\Infraestructura\Busqueda;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mismo criterio de acentos/mayúsculas que {@see BusquedaEloquent} (buscador
 * global, 9/9/2026: `unaccent(columna) ILIKE unaccent(?)` en Postgres,
 * `LOWER(columna) LIKE ?` en SQLite — la extensión `unaccent` ya la instala
 * la migración `create_extension_unaccent`), reusado acá para el filtro `q`
 * de los listados: hasta el 15/9/2026 cada caso de uso repetía su propio
 * `where('columna', 'like', "%{$busqueda}%")`, sensible a mayúsculas y sin
 * manejo de acentos (PostgreSQL `LIKE` es case-sensitive, a diferencia de
 * MySQL).
 *
 * No reemplaza a `BusquedaEloquent`: esa clase arma bloques del buscador
 * global (multi-término, multi-proveedor, resultado tipado). Esto es solo
 * el fragmento SQL de "esta búsqueda aparece en alguna de estas columnas",
 * para que un caso de uso de listado lo aplique con una línea.
 */
final class BusquedaTexto
{
    /**
     * @param Builder<*> $consulta
     * @param  list<string>  $columnas  OR entre columnas — una coincidencia alcanza.
     * @return Builder<*>
     */
    public static function aplicar(Builder $consulta, array $columnas, string $termino): Builder
    {
        $patron = '%'.self::escaparComodines($termino).'%';

        return $consulta->where(function (Builder $query) use ($columnas, $patron): void {
            foreach ($columnas as $columna) {
                [$sql, $bindings] = self::condicion($columna, $patron);
                $query->orWhereRaw($sql, $bindings);
            }
        });
    }

    /** @return array{string, list<string>} */
    private static function condicion(string $columna, string $patron): array
    {
        $columnaCitada = DB::connection()->getQueryGrammar()->wrap($columna);

        if (DB::connection()->getDriverName() === 'pgsql') {
            return ["unaccent({$columnaCitada}) ILIKE unaccent(?)", [$patron]];
        }

        return ["LOWER({$columnaCitada}) LIKE ?", [$patron]];
    }

    /**
     * `%` y `_` escritos por el usuario son literales, no comodines: sin esto
     * un `%` suelto en el buscador devuelve la tabla entera.
     */
    private static function escaparComodines(string $termino): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
    }
}
