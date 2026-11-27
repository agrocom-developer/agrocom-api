<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Seguridad\Aplicacion\ItemMenu;

/**
 * Los accesos rápidos de «Mi perfil»: las pantallas a las que más va cada rol,
 * a un clic. Pura: sin base de datos ni sesión.
 *
 * NO decide qué puede ver nadie. Elige entre las pantallas del MENÚ que ya
 * resolvió `ObtenerMenuPorRolActivo` para el rol activo (permiso, bandera de
 * persona y estado del rol incluidos), así un acceso nunca aparece si el menú
 * no lo mostraría — y no hay una segunda lista de permisos que mantener. Ocultar
 * un acceso tampoco es autorizar: cada pantalla revalida en su controlador.
 *
 * Cada rol tiene un orden de preferencia (rutas del panel); se toman las que el
 * menú tiene, hasta {@see self::MAXIMO}. Un rol sin lista propia cae en las primeras pantallas de su menú, sin
 * repetir el tablero, que ya está en el riel.
 */
final class AccesosRapidos
{
    public const MAXIMO = 6;

    /** @var array<string, list<string>> clave del rol → rutas, en orden de preferencia */
    private const POR_ROL = [
        'administrador' => [
            'panel.solicitudes.index',
            'panel.proveedores.index',
            'panel.documentos.index',
            'panel.drones-flota.index',
            'panel.reclamos.index',
            'panel.usuarios.index',
        ],
        'operador' => [
            'panel.documentos.index',
            'panel.proveedores.index',
            'panel.solicitudes.index',
            'panel.clientes.index',
            'panel.calificaciones.index',
            'panel.reclamos.index',
        ],
    ];

    private const RUTA_TABLERO = 'panel.dashboard';

    /**
     * @param  list<ItemMenu>  $menu  el menú del rol activo (módulos con sus pantallas)
     * @return list<array{ruta: string, icono: string, etiqueta: string, modulo: string}> `etiqueta` y
     *                                                                                    `modulo` son claves de idioma del menú, sin traducir.
     */
    public static function para(?string $rolClave, array $menu): array
    {
        $disponibles = self::pantallasDelMenu($menu);

        $rutas = self::POR_ROL[$rolClave ?? ''] ?? [];
        $elegidas = array_values(array_filter($rutas, static fn (string $ruta): bool => isset($disponibles[$ruta])));

        if ($elegidas === []) {
            $elegidas = array_values(array_filter(
                array_keys($disponibles),
                static fn (string $ruta): bool => $ruta !== self::RUTA_TABLERO,
            ));
        }

        return array_map(
            static fn (string $ruta): array => ['ruta' => $ruta, ...$disponibles[$ruta]],
            array_slice($elegidas, 0, self::MAXIMO),
        );
    }

    /**
     * Las pantallas con ruta del menú, por ruta y en el orden del menú. Si una
     * ruta aparece dos veces se queda la primera.
     *
     * @param  list<ItemMenu>  $menu
     * @return array<string, array{icono: string, etiqueta: string, modulo: string}>
     */
    private static function pantallasDelMenu(array $menu): array
    {
        $pantallas = [];

        foreach ($menu as $modulo) {
            foreach (self::hojas($modulo) as $hoja) {
                if ($hoja->ruta !== null && ! isset($pantallas[$hoja->ruta])) {
                    $pantallas[$hoja->ruta] = [
                        'icono' => $hoja->icono,
                        'etiqueta' => $hoja->label,
                        'modulo' => $modulo->label,
                    ];
                }
            }
        }

        return $pantallas;
    }

    /**
     * Las pantallas de un módulo: sus hijos con ruta, a cualquier profundidad. Un módulo sin
     * hijos (una pantalla suelta en la raíz del menú) es su propia pantalla.
     *
     * @return list<ItemMenu>
     */
    private static function hojas(ItemMenu $nodo): array
    {
        if ($nodo->hijos === []) {
            return $nodo->ruta !== null ? [$nodo] : [];
        }

        $hojas = [];

        foreach ($nodo->hijos as $hijo) {
            array_push($hojas, ...self::hojas($hijo));
        }

        return $hojas;
    }
}
