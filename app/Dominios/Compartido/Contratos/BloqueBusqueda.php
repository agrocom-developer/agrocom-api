<?php

namespace App\Dominios\Compartido\Contratos;

/**
 * Los resultados de UNA entidad dentro de la pantalla de resultados: el
 * bloque "Clientes", el bloque "Lotes". Pedido del dueño: *"en la página de
 * resultados nos salga bloques de las otras páginas según las coincidencias"*.
 *
 * `total` puede ser mayor que `count($resultados)`: cada bloque trae un
 * puñado y ofrece "ver todos" contra el listado del módulo, que ya sabe
 * filtrar, paginar y aplicar sus propios permisos. El buscador no reimplementa
 * esas pantallas.
 */
final class BloqueBusqueda
{
    /**
     * @param  string  $clave  Identificador estable del bloque
     *                         ("clientes"), para la clave de
     *                         traducción y el `wire:key`.
     * @param  string  $titulo  Rótulo ya traducido.
     * @param  string  $icono  Ligadura de Material Symbols.
     * @param  list<ResultadoBusqueda>  $resultados  Lo que se pinta, ya recortado.
     * @param  int  $total  Coincidencias totales.
     * @param  string|null  $verTodosHref  Listado del módulo con la
     *                                     búsqueda ya aplicada.
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $icono,
        public readonly array $resultados,
        public readonly int $total,
        public readonly ?string $verTodosHref = null,
    ) {}

    public function hayMasQueNoSeMuestran(): bool
    {
        return $this->total > count($this->resultados);
    }
}
