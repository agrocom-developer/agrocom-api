<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

/**
 * Color identificativo de cada MÓDULO del menú en las pantallas de roles: la
 * matriz de permisos, la ficha del rol y la lista de permisos pintan el mismo
 * módulo con el mismo tono, para saber de un vistazo qué está moviendo cada
 * interruptor.
 *
 * El tono es el NOMBRE de un token de color del panel (`success`, `info`,
 * `distintivo-1`…), nunca un valor: la clase `ag-modulo-tono--<tono>` de
 * `components/modulo-tono.css` lo traduce a los tokens `--ag-color-<tono>-*`, que
 * ya cambian con el tema (CLAUDE.md invariante 11). El color nunca va solo: cada
 * módulo lleva además su nombre y su ícono.
 *
 * Reglas:
 * - Estable: los módulos del menú actual tienen su tono fijo en {@see self::POR_MODULO};
 *   no depende del orden del menú ni de qué rol se mira.
 * - Único: dos módulos no comparten tono. Un módulo nuevo toma el primero libre de
 *   {@see self::RESERVA} (los dos tonos propios del bloque `modulo` de los temas),
 *   en el orden de su clave, y si se agotan sin tono cae en `neutral`.
 * - Sin `danger` ni `alert` (significan error) y sin `primary-2` ni `distintivo-3`
 *   (son el mismo azul que `info`: no se distinguirían).
 */
final class ColorDeModulo
{
    /** Las acciones sin pantalla del panel no son un módulo del menú: van aparte. */
    public const CLAVE_SUELTOS = 'sueltos';

    /** @var array<string, string> clave de etiqueta del módulo (`sec_menu.label`) → tono. */
    public const POR_MODULO = [
        'menu.solicitudes.label' => 'success',
        'menu.proveedores.label' => 'info',
        'menu.atencion.label' => 'distintivo-2',
        'menu.seguridad.label' => 'distintivo-1',
        'menu.sistema.label' => 'warning',
        self::CLAVE_SUELTOS => 'neutral',
    ];

    /** @var list<string> Tonos para un módulo que todavía no tiene el suyo. */
    public const RESERVA = ['distintivo-4', 'distintivo-5'];

    /**
     * Tono de cada módulo dado, sin repetir ninguno.
     *
     * @param  list<string>  $clavesDeModulo
     * @return array<string, string> clave → tono
     */
    public static function asignar(array $clavesDeModulo): array
    {
        $claves = array_values(array_unique($clavesDeModulo));
        sort($claves);

        $tonos = [];
        $usados = [];

        foreach ($claves as $clave) {
            if (isset(self::POR_MODULO[$clave])) {
                $tonos[$clave] = self::POR_MODULO[$clave];
                $usados[] = self::POR_MODULO[$clave];
            }
        }

        $libres = array_values(array_diff(self::RESERVA, $usados));

        foreach ($claves as $clave) {
            if (! isset($tonos[$clave])) {
                $tonos[$clave] = array_shift($libres) ?? 'neutral';
            }
        }

        return $tonos;
    }
}
