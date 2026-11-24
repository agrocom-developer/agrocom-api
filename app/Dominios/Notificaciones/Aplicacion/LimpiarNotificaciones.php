<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;

/**
 * «Limpiar» de la campana: vacía la campana de la cuenta que lo pide, leídas y
 * no leídas. Solo de esa cuenta: lo que otra tenga no se toca.
 *
 * Los avisos se dan de baja (soft delete, invariante 8): el índice único de
 * `ntf_notificaciones` no es parcial a propósito, así que un reintento del mismo
 * evento no los resucita (ADR 0025, punto 5). Se recorren TODOS los de la cuenta,
 * no solo los que la campana alcanza a mostrar: «limpiar» no deja avisos
 * escondidos que reaparezcan después. Fila por fila, para que cada baja pase por
 * la autoría y la bitácora (invariante 9).
 */
final class LimpiarNotificaciones
{
    /** @return int cantidad de avisos que se limpiaron */
    public function ejecutar(int $usuarioId): int
    {
        $limpiadas = 0;

        foreach (Notificacion::query()->deUsuario($usuarioId)->get() as $notificacion) {
            $notificacion->delete();
            $limpiadas++;
        }

        return $limpiadas;
    }
}
