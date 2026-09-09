<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Contratos\BloqueBusqueda;
use App\Dominios\Compartido\Contratos\ProveedorBusqueda;
use App\Dominios\Compartido\Dominio\TerminosBusqueda;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * El buscador global del header (HU del buscador, 9/9/2026): reparte lo que
 * el usuario escribió entre todos los módulos y devuelve un bloque por
 * entidad con coincidencias.
 *
 * Vive en Seguridad por la misma razón que {@see ObtenerMenuPorRolActivo}: lo
 * que hace de verdad no es buscar —eso lo hace cada módulo en su propio
 * proveedor— sino decidir QUÉ puede ver quien busca. Un resultado es tan
 * sensible como la pantalla de la que sale, así que cada bloque se gatea con
 * el permiso que declara su proveedor, evaluado contra el **rol activo** de la
 * sesión y nunca contra la unión de roles del usuario (CLAUDE.md invariante
 * 10). Un jefe de campo que además es piloto busca con lo que le da el rol
 * con el que entró, no con la suma de los dos.
 *
 * El agregador no conoce ninguna tabla: recorre los proveedores que cada
 * `ServiceProvider` de módulo registró con el tag `busqueda.proveedores`.
 * Sumar una entidad es escribir un proveedor y taggearlo.
 */
final class BuscarEnElPanel
{
    /** Cuántas filas trae cada bloque antes de ofrecer "ver todos". */
    public const RESULTADOS_POR_BLOQUE = 5;

    /** @var list<ProveedorBusqueda> */
    private readonly array $proveedores;

    /** @param iterable<ProveedorBusqueda> $proveedores */
    public function __construct(iterable $proveedores)
    {
        $ordenados = array_values(is_array($proveedores) ? $proveedores : iterator_to_array($proveedores));

        usort(
            $ordenados,
            static fn (ProveedorBusqueda $a, ProveedorBusqueda $b): int => [$a->prioridad(), $a->clave()] <=> [$b->prioridad(), $b->clave()],
        );

        $this->proveedores = $ordenados;
    }

    /**
     * Bloques con al menos una coincidencia, en orden de prioridad. Un bloque
     * sin resultados no se devuelve: la pantalla no pinta doce encabezados
     * vacíos para decir que no encontró nada.
     *
     * @return list<BloqueBusqueda>
     */
    public function ejecutar(
        SecUser $usuario,
        int $idRolActivo,
        TerminosBusqueda $terminos,
        int $porBloque = self::RESULTADOS_POR_BLOQUE,
    ): array {
        if ($terminos->vacia()) {
            return [];
        }

        $bloques = [];

        foreach ($this->proveedores as $proveedor) {
            if (! $usuario->tienePermisoEnRol($proveedor->permiso(), $idRolActivo)) {
                continue;
            }

            $bloque = $proveedor->buscar($terminos, $porBloque);

            if ($bloque->total > 0) {
                $bloques[] = $bloque;
            }
        }

        return $bloques;
    }

    /**
     * Total de coincidencias sumando todos los bloques — el "N resultados"
     * del encabezado de la pantalla.
     *
     * @param  list<BloqueBusqueda>  $bloques
     */
    public static function totalDe(array $bloques): int
    {
        return array_sum(array_map(static fn (BloqueBusqueda $bloque): int => $bloque->total, $bloques));
    }
}
