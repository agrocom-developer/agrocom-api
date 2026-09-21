<?php

namespace App\Dominios\Comercial\Aplicacion\Lote;

/**
 * Cómo están hoy los lotes de una propiedad, para precargar la pantalla de
 * edición en bloque. Un atributo con el mismo valor en todos los lotes llega
 * con ese valor; uno que varía llega `null` y figura en `$variables` — así la
 * pantalla avisa que al guardar se igualan, en vez de mostrar el valor de un
 * lote cualquiera como si fuera el de todos.
 */
final readonly class ResumenLotesEnBloque
{
    /**
     * @param  list<string>  $codigos  en orden de alta: los últimos son los que se quitan al bajar la cantidad.
     * @param  list<string>  $variables  atributos (`hectareas`, `desnivel`, `limpieza`, `restricciones`) que no son iguales en todos los lotes.
     */
    public function __construct(
        public string $prefijo,
        public array $codigos,
        public ?string $hectareas,
        public ?string $desnivel,
        public ?string $limpieza,
        public ?string $restricciones,
        public array $variables,
    ) {}

    public function total(): int
    {
        return count($this->codigos);
    }
}
