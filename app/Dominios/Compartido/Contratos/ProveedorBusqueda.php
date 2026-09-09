<?php

namespace App\Dominios\Compartido\Contratos;

use App\Dominios\Compartido\Dominio\TerminosBusqueda;

/**
 * Lo que un módulo implementa para que sus entidades aparezcan en el buscador
 * global (HU del buscador, 9/9/2026).
 *
 * El agregador ({@see BuscarEnElPanel}) no sabe qué tablas existen: recorre
 * los proveedores que cada `ServiceProvider` de módulo registró con el tag
 * `busqueda.proveedores`. Sumar una entidad al buscador es escribir un
 * proveedor y taggearlo — nunca editar el agregador ni la pantalla.
 *
 * Es también donde se respeta la invariante 10 de CLAUDE.md: `permiso()`
 * declara qué hace falta para VER estos resultados, y el agregador lo evalúa
 * contra el ROL ACTIVO de la sesión, nunca contra la unión de roles. Un rol
 * que no entra a `/panel/clientes` tampoco encuentra clientes acá.
 */
interface ProveedorBusqueda
{
    /** Identificador estable del bloque: "clientes", "lotes", "drones". */
    public function clave(): string;

    /** Permiso que gatea estos resultados, evaluado contra el rol activo. */
    public function permiso(): string;

    /** Orden del bloque en la pantalla; más bajo, más arriba. */
    public function prioridad(): int;

    public function buscar(TerminosBusqueda $terminos, int $limite): BloqueBusqueda;
}
