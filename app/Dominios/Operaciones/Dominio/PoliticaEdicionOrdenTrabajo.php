<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Regla de edición de una Orden de Trabajo (tarea 127): a diferencia de
 * `PoliticaEdicionOrden` —que mira el ESTADO de la orden de aplicación—, acá
 * no hay un estado propio de la tanda (`OrdenTrabajo` no tiene máquina de
 * estados, ver docblock del modelo): lo que decide si se edita es el estado
 * de TABLERO de sus `Trabajo` (`EstadoTableroTrabajo`, que ya mezcla
 * `EstadoTrabajo` con el estado de las sesiones).
 *
 * - Se corrige la cabecera (indicaciones compartidas) mientras la tanda no
 *   esté enteramente cerrada: en cuanto TODOS sus trabajos llegan a
 *   `validado`, la tanda es historia y queda cerrada a edición. Mientras
 *   quede algún trabajo `abierto` o `cerrado` sin validar, la cabecera se
 *   sigue corrigiendo — cambiarla no sobrescribe ningún `Trabajo` ni
 *   `Sesion` validado (invariante 2 de CLAUDE.md: esos registros no se
 *   tocan).
 * - Si algún trabajo ya no está `abierto` (está `cerrado` o `validado`), la
 *   corrección exige un motivo — mismo criterio que
 *   `PoliticaEdicionOrden::exigeMotivo()` para una orden ya publicada.
 *
 * Reglas puras, sin Eloquent (verificado por `tests/Unit/ArquitecturaModulosTest`).
 * Las lee quien aplica la edición (`Aplicacion/ActualizarOrdenTrabajo`) y
 * quien la ofrece (la ficha, el listado y el propio formulario), para que lo
 * que la pantalla anuncia y lo que el servidor exige nunca se contradigan.
 */
final class PoliticaEdicionOrdenTrabajo
{
    /**
     * ¿La Orden de Trabajo admite corregir su cabecera? Sí, salvo que TODOS
     * sus trabajos ya estén `validado` — ahí la tanda entera es historia.
     *
     * @param  list<EstadoTableroTrabajo>  $estadosTrabajos  de TODOS los trabajos de la tanda.
     */
    public static function admiteEdicion(array $estadosTrabajos): bool
    {
        foreach ($estadosTrabajos as $estado) {
            if ($estado !== EstadoTableroTrabajo::Validado) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿La corrección pide motivo? Sí en cuanto algún trabajo ya no está
     * `abierto` (está `cerrado` o ya `validado`) — mientras todos siguen
     * `abierto`, no.
     *
     * @param  list<EstadoTableroTrabajo>  $estadosTrabajos  de TODOS los trabajos de la tanda.
     */
    public static function exigeMotivo(array $estadosTrabajos): bool
    {
        foreach ($estadosTrabajos as $estado) {
            if ($estado !== EstadoTableroTrabajo::Abierto) {
                return true;
            }
        }

        return false;
    }
}
