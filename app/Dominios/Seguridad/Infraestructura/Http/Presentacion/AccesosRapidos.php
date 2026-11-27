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
 * menú tiene y, si sobran lugares hasta {@see self::MAXIMO}, se completan con las
 * primeras pantallas de su menú. Un rol sin lista propia cae en el menú entero,
 * sin repetir el tablero, que ya está en el riel.
 *
 * Cada acceso lleva un tono por dominio ({@see self::TONO_POR_DOMINIO}), el color
 * del cuadro de su ícono; nunca es la única señal (hay ícono y texto).
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
            'panel.disputas.index',
            'panel.usuarios.index',
        ],
        'operador' => [
            'panel.documentos.index',
            'panel.proveedores.index',
            'panel.solicitudes.index',
            'panel.clientes.index',
            'panel.calificaciones.index',
            'panel.disputas.index',
        ],
    ];

    private const RUTA_TABLERO = 'panel.dashboard';

    /**
     * Tono (variante de `molecules/link-row`) por dominio, que es el segundo segmento de la
     * ruta (`panel.<dominio>.…`): mercado, proveedores, clientes, atención y seguridad.
     *
     * @var array<string, string>
     */
    private const TONO_POR_DOMINIO = [
        'solicitudes' => 'info',
        'metricas' => 'info',
        'proveedores' => 'distintivo-1',
        'pilotos' => 'distintivo-1',
        'documentos' => 'distintivo-1',
        'drones-flota' => 'distintivo-1',
        'clientes' => 'distintivo-2',
        'calificaciones' => 'warning',
        'disputas' => 'warning',
        'avisos' => 'warning',
        'notificaciones' => 'warning',
        'usuarios' => 'distintivo-3',
        'roles' => 'distintivo-3',
        'bitacora' => 'distintivo-3',
        'organizacion' => 'distintivo-3',
        'configuracion' => 'distintivo-3',
    ];

    private const TONO_SIN_DOMINIO = 'neutral';

    /**
     * @param  list<ItemMenu>  $menu  el menú del rol activo (módulos con sus pantallas)
     * @return list<array{ruta: string, icono: string, etiqueta: string, modulo: string, tono: string}> `etiqueta` y
     *                                                                                                  `modulo` son claves de idioma del menú, sin traducir.
     */
    public static function para(?string $rolClave, array $menu): array
    {
        $disponibles = self::pantallasDelMenu($menu);

        $rutas = self::POR_ROL[$rolClave ?? ''] ?? [];
        $elegidas = array_values(array_filter($rutas, static fn (string $ruta): bool => isset($disponibles[$ruta])));

        if (count($elegidas) < self::MAXIMO) {
            $resto = array_filter(
                array_keys($disponibles),
                static fn (string $ruta): bool => $ruta !== self::RUTA_TABLERO && ! in_array($ruta, $elegidas, true),
            );
            $elegidas = [...$elegidas, ...array_values($resto)];
        }

        return array_map(
            static fn (string $ruta): array => ['ruta' => $ruta, ...$disponibles[$ruta], 'tono' => self::tonoDe($ruta)],
            array_slice($elegidas, 0, self::MAXIMO),
        );
    }

    private static function tonoDe(string $ruta): string
    {
        $segmentos = explode('.', $ruta);

        return self::TONO_POR_DOMINIO[$segmentos[1] ?? ''] ?? self::TONO_SIN_DOMINIO;
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
