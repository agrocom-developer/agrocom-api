<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use Carbon\CarbonImmutable;

/**
 * «Marcar todas como leídas» de la campana: solo los avisos de la cuenta que
 * lo pide, y las alertas técnicas que su campana muestra. Se recorre fila por
 * fila y no con un `update()` masivo, para que cada cambio pase por la autoría
 * y la bitácora (invariantes 8 y 9): un `update()` de query builder no
 * dispara los eventos de Eloquent.
 */
final class MarcarTodasLeidas
{
    public function __construct(
        private readonly EstadoDeAlertaPorCuenta $alertas,
        private readonly LecturaPanelOperaciones $operaciones,
    ) {}

    /**
     * @param  list<int>  $idsAlerta  las alertas que la campana de esta cuenta muestra;
     *                                quien llama ya verificó que puede verlas. Las que no
     *                                existen se ignoran.
     * @return int cantidad de avisos y alertas que estaban sin leer
     */
    public function ejecutar(int $usuarioId, array $idsAlerta = []): int
    {
        $marcadas = 0;

        foreach (Notificacion::query()->deUsuario($usuarioId)->sinLeer()->get() as $notificacion) {
            $notificacion->leida_en = CarbonImmutable::now();
            $notificacion->save();
            $marcadas++;
        }

        foreach ($this->operaciones->idsAlertasExistentes($idsAlerta) as $alertaId) {
            if ($this->alertas->marcarLeida($usuarioId, $alertaId)) {
                $marcadas++;
            }
        }

        return $marcadas;
    }
}
