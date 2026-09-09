<?php

namespace App\Dominios\Compartido\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\BloqueBusqueda;
use App\Dominios\Compartido\Contratos\ProveedorBusqueda;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Dominio\TerminosBusqueda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Base de los proveedores que buscan sobre una tabla propia con Eloquent
 * (HU del buscador global, 9/9/2026). Una entidad se suma al buscador
 * declarando cuatro cosas —modelo, columnas, permiso, ruta— y una función que
 * arma la fila; el SQL lo pone esta clase.
 *
 * # Cómo se traduce lo que el usuario escribió
 *
 * {@see TerminosBusqueda} ya partió la consulta en palabras. Acá se arma
 * **un `AND` por término** y, dentro de cada uno, **un `OR` por columna**:
 *
 *     "esperanza sa"  →  (col1 LIKE %esperanza% OR col2 LIKE %esperanza%)
 *                    AND (col1 LIKE %sa%        OR col2 LIKE %sa%)
 *
 * Eso da las tres propiedades que pidió el dueño: encuentra con varias
 * palabras en cualquier orden, cada una puede caer a mitad de una oración
 * ("peranza" → "Esperanza"), y no hace falta escribir la razón social exacta.
 *
 * # Acentos y mayúsculas
 *
 * En PostgreSQL se compara `unaccent(columna) ILIKE unaccent(?)`, así que
 * "agricola" encuentra "Agrícola" y al revés. La extensión la instala una
 * migración propia, solo en `pgsql`.
 *
 * En SQLite —el motor de la suite, ver `phpunit.xml`— no hay `unaccent` ni
 * `ILIKE`, así que se cae a `LIKE` sobre `LOWER(columna)`, que en SQLite ya
 * es insensible a mayúsculas para ASCII. La consecuencia hay que tenerla
 * presente al leer la suite: **los tests cubren multi-palabra e infix en los
 * dos motores, pero lo de los acentos solo corre contra Postgres** y su test
 * se saltea en SQLite en vez de mentir que pasó.
 *
 * # Por qué `LIKE` y no full-text
 *
 * `tsvector` indexa palabras enteras: "peranza" no encontraría "Esperanza",
 * que es justo el caso pedido. Sobre estas tablas —miles de filas, no
 * millones— el `LIKE` con comodín a ambos lados rinde de sobra. Si algún día
 * deja de rendir, el reemplazo es `pg_trgm` con índice GIN, que conserva el
 * infix; el cambio queda contenido en {@see condicionDeTermino()}.
 *
 * @template TModelo of Model
 */
abstract class BusquedaEloquent implements ProveedorBusqueda
{
    /** @return class-string<TModelo> */
    abstract protected function modelo(): string;

    /**
     * Columnas donde se busca, en la tabla del modelo.
     *
     * @return list<string>
     */
    abstract protected function columnas(): array;

    /** Ligadura de Material Symbols del bloque — la misma del ítem de menú. */
    abstract protected function icono(): string;

    /** Clave de `lang/es/busqueda.php` con el rótulo del bloque. */
    protected function claveTitulo(): string
    {
        return 'busqueda.bloques.'.$this->clave();
    }

    /** Ruta del listado del módulo, para "ver todos". `null` si no tiene. */
    abstract protected function rutaListado(): ?string;

    /**
     * Nombre del parámetro de búsqueda del listado, para que "ver todos"
     * llegue con el filtro puesto. Los listados del panel usan `q`.
     */
    protected function parametroBusquedaDelListado(): string
    {
        return 'q';
    }

    /** @param TModelo $modelo */
    abstract protected function fila(Model $modelo): ResultadoBusqueda;

    public function prioridad(): int
    {
        return 50;
    }

    public function buscar(TerminosBusqueda $terminos, int $limite): BloqueBusqueda
    {
        $consulta = $this->consultaBase()->where(
            fn (Builder $query) => $this->aplicarTerminos($query, $terminos),
        );

        // El total se cuenta aparte del `limit`: el bloque muestra un puñado
        // y necesita saber cuántas quedaron afuera para ofrecer "ver todos".
        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderBy($this->columnaDeOrden())
            ->limit($limite)
            ->get()
            ->map(fn (Model $modelo): ResultadoBusqueda => $this->fila($modelo))
            ->all();

        return new BloqueBusqueda(
            clave: $this->clave(),
            titulo: __($this->claveTitulo()),
            icono: $this->icono(),
            resultados: $filas,
            total: $total,
            verTodosHref: $this->verTodosHref($terminos),
        );
    }

    /**
     * Punto de extensión para un proveedor que necesite `with()`, un `join`
     * dentro de su propio módulo o un `where` de alcance.
     *
     * @return Builder<TModelo>
     */
    protected function consultaBase(): Builder
    {
        /** @var Builder<TModelo> $consulta */
        $consulta = $this->modelo()::query();

        return $consulta;
    }

    protected function columnaDeOrden(): string
    {
        return $this->columnas()[0];
    }

    private function verTodosHref(TerminosBusqueda $terminos): ?string
    {
        $ruta = $this->rutaListado();

        if ($ruta === null || ! Route::has($ruta)) {
            return null;
        }

        return route($ruta, [$this->parametroBusquedaDelListado() => (string) $terminos]);
    }

    /** @param Builder<TModelo> $query */
    private function aplicarTerminos(Builder $query, TerminosBusqueda $terminos): void
    {
        foreach ($terminos->terminos as $termino) {
            // AND entre términos: cada palabra tiene que aparecer en ALGUNA
            // de las columnas, no necesariamente en la misma.
            $query->where(function (Builder $porColumna) use ($termino): void {
                foreach ($this->columnas() as $columna) {
                    $porColumna->orWhereRaw(...$this->condicionDeTermino($columna, $termino));
                }
            });
        }
    }

    /**
     * El fragmento SQL de "esta columna contiene este término", por motor.
     *
     * @return array{string, list<string>}
     */
    private function condicionDeTermino(string $columna, string $termino): array
    {
        $columnaCitada = $this->citar($columna);
        $patron = '%'.$this->escaparComodines($termino).'%';

        if (DB::connection()->getDriverName() === 'pgsql') {
            return ["unaccent({$columnaCitada}) ILIKE unaccent(?)", [$patron]];
        }

        // SQLite (la suite): `LIKE` ya es insensible a mayúsculas en ASCII;
        // `LOWER()` cubre el resto de lo que sí sabe bajar.
        return ["LOWER({$columnaCitada}) LIKE ?", [$patron]];
    }

    private function citar(string $columna): string
    {
        return DB::connection()->getQueryGrammar()->wrap(
            str_contains($columna, '.') ? $columna : $this->tabla().'.'.$columna,
        );
    }

    private function tabla(): string
    {
        $modelo = $this->modelo();

        return (new $modelo)->getTable();
    }

    /**
     * `%` y `_` escritos por el usuario son literales, no comodines: sin esto
     * un `%` suelto en el buscador devuelve la tabla entera.
     */
    private function escaparComodines(string $termino): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
    }
}
