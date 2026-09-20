<?php

namespace App\Dominios\Operaciones\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesTrabajo;

/**
 * Los pasos de `molecules/step-arrow` del trabajo (tarea 114), en su ficha de
 * edición: cómo se dibuja su máquina de estados. Mismo papel que
 * `PasosDeOrden` para la orden de aplicación, con una diferencia que ordena
 * toda la clase.
 *
 * **El panel no cambia el estado de un trabajo.** La ruta es de dos pasos
 * —«Abierto → Cerrado»— y esa única transición la registra el piloto desde
 * `agrocom-field` al terminar (el motor de sync la aplica vía
 * `MaquinaEstadosTrabajo`); en el panel no existe ninguna ruta que la dispare.
 * Por eso `puedeCambiar` es `false` fijo: el paso «Cerrado» queda `pending`,
 * visible pero sin modal, y los pasos son informativos —dicen dónde está el
 * trabajo, no ofrecen moverlo—.
 *
 * Eso obliga a corregir dos textos genéricos de `PasosDeEstado`, que dan por
 * sentado que un paso no accionable lo es por falta de permiso: la pista del
 * paso pendiente ({@see self::armar()}) y el cierre del párrafo de ayuda
 * ({@see self::ayuda()}). El motivo acá no es el rol —ningún rol puede cerrar
 * un trabajo desde el panel—, así que decirlo sería mentir.
 *
 * Nada de esto decide una transición: quien la aplica, con sus guardas, es
 * `MaquinaEstadosTrabajo` (invariante 7 de CLAUDE.md); la tabla que consulta lo
 * dibujado es la misma, `TransicionesTrabajo`.
 *
 * @phpstan-type Paso array{key: string, label: string, tone: string, status: 'completed'|'current'|'next'|'pending'|'blocked', modal: string|null, hint: string|null, icon: string|null}
 */
final class PasosDeTrabajo
{
    /**
     * Tono de cada estado, definido UNA vez: son los mismos con los que el
     * maestro de órdenes de trabajo ya pinta el badge del estado de tablero
     * (`ordenes-trabajo/index`), para que los dos hablen con el mismo color.
     * `validado` no está acá porque no es un estado de la máquina, sino el
     * derivado de `Trabajo::estadoTablero()`.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'abierto' => 'neutral',
        'cerrado' => 'info',
    ];

    /**
     * @return list<Paso>
     */
    public static function armar(EstadoTrabajo $actual): array
    {
        $pasos = PasosDeEstado::armar(
            ruta: [EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado],
            actual: $actual,
            permitida: TransicionesTrabajo::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'operaciones.trabajos.estado',
            // Ningún paso llega a `next`, así que ningún modal se nombra; el
            // prefijo queda igual por si el panel gana la transición algún día.
            prefijoModal: 'trabajo-estado-modal',
            puedeCambiar: false,
        );

        return array_map(self::conPistaPropia(...), $pasos);
    }

    /**
     * Párrafo de apoyo bajo los pasos: qué significa el estado actual y qué
     * esperar. Sale de `operaciones.trabajos.estado_ayuda.*`.
     *
     * Los pasos `pending` se le ocultan a propósito: con uno a la vista,
     * `PasosDeEstado::ayuda()` cierra el párrafo con «Con tu rol no puedes
     * cambiar el estado» —el único cierre que tiene para un paso sin acción—, y
     * acá el rol no es el motivo. Sin destino, el párrafo queda solo con el
     * texto del estado, que ya explica quién cierra el trabajo.
     *
     * @param  list<Paso>  $pasos  los que devolvió {@see self::armar()}.
     */
    public static function ayuda(array $pasos): ?string
    {
        $sinPendientes = array_values(array_filter(
            $pasos,
            fn (array $paso): bool => $paso['status'] !== 'pending',
        ));

        return PasosDeEstado::ayuda($sinPendientes, 'operaciones.trabajos.estado_ayuda');
    }

    /**
     * Cambia la pista del paso pendiente por la propia del trabajo: no está sin
     * acción por falta de permiso (lo que dice `ui.pasos.pista_sin_permiso`),
     * sino porque el cierre se registra en campo.
     *
     * @param  Paso  $paso
     * @return Paso
     */
    private static function conPistaPropia(array $paso): array
    {
        if ($paso['status'] === 'pending') {
            $paso['hint'] = Texto::de('operaciones.trabajos.estado_pista_cierre_en_campo');
        }

        return $paso;
    }
}
