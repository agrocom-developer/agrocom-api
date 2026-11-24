<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use Carbon\CarbonImmutable;

/**
 * «Marcar todas como leídas» de la campana: solo los avisos de la cuenta que
 * lo pide. Se recorre fila por fila y no con un `update()` masivo, para que
 * cada cambio pase por la autoría y la bitácora (invariantes 8 y 9): un
 * `update()` de query builder no dispara los eventos de Eloquent.
 */
final class MarcarTodasLeidas
{
    /** @return int cantidad de avisos que estaban sin leer */
    public function ejecutar(int $usuarioId): int
    {
        $marcadas = 0;

        foreach (Notificacion::query()->deUsuario($usuarioId)->sinLeer()->get() as $notificacion) {
            $notificacion->leida_en = CarbonImmutable::now();
            $notificacion->save();
            $marcadas++;
        }

        return $marcadas;
    }
}
