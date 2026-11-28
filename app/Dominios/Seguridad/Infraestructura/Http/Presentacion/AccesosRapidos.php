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
 * Cada acceso lleva un tono propio, distinto del de las demás tarjetas mostradas
 * ({@see self::tonosSinRepetir()}), el color del cuadro de su ícono; nunca es la única
 * señal (hay ícono y texto).
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
     * Tonos disponibles (variantes de `molecules/link-row` con relleno sólido y ícono blanco). Cada
     * uno es un COLOR distinto: `distintivo-3` y `primary-2` quedan fuera porque pintan exactamente el
     * mismo azul que `info` (mismo valor en `tokens/semantic/theme-*.css`), así que no cuentan como
     * tonos aparte. Tampoco `danger` (significa error) ni `neutral` (significa «sin estado»). Son siete
     * para seis tarjetas como máximo, así que siempre hay uno libre; la prueba `AccesosRapidosTest`
     * comprueba que ninguno repite el valor de otro.
     *
     * @var list<string>
     */
    public const TONOS = [
        'info',
        'distintivo-1',
        'distintivo-2',
        'warning',
        'success',
        'distintivo-4',
        'distintivo-5',
    ];

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

        $elegidas = array_slice($elegidas, 0, self::MAXIMO);
        $tonos = self::tonosSinRepetir($elegidas);

        return array_map(
            static fn (string $ruta): array => ['ruta' => $ruta, ...$disponibles[$ruta], 'tono' => $tonos[$ruta]],
            $elegidas,
        );
    }

    /**
     * Un tono distinto por tarjeta. Cada ruta tiene un tono de preferencia que sale de la ruta misma
     * (no de su posición ni de su dominio), así que no cambia de una visita a otra; si ese tono ya lo
     * tiene una tarjeta anterior, se desplaza al siguiente libre de {@see self::TONOS}, en orden fijo.
     *
     * @param  list<string>  $rutas  las rutas elegidas, en el orden en que se muestran
     * @return array<string, string> tono por ruta
     */
    private static function tonosSinRepetir(array $rutas): array
    {
        $total = count(self::TONOS);
        $usados = [];
        $tonos = [];

        foreach ($rutas as $ruta) {
            $indice = crc32($ruta) % $total;

            while (isset($usados[$indice])) {
                $indice = ($indice + 1) % $total;
            }

            $usados[$indice] = true;
            $tonos[$ruta] = self::TONOS[$indice];
        }

        return $tonos;
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
