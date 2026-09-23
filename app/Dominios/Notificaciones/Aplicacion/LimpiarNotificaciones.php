<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;

/**
 * «Limpiar» de la campana: vacía la campana de la cuenta que lo pide, leídas y
 * no leídas. Solo de esa cuenta: lo que otra tenga no se toca.
 *
 * - Los avisos del motor se dan de baja (soft delete, invariante 8): el índice
 *   único de `ntf_notificaciones` no es parcial a propósito, así que un
 *   reintento del mismo evento no los resucita (ADR 0025, punto 5). Se
 *   recorren TODOS los de la cuenta, no solo los que la campana alcanza a
 *   mostrar: «limpiar» no deja avisos escondidos que reaparezcan después.
 * - Las alertas técnicas no se dan de baja —son de todos y siguen en su
 *   pantalla—: quedan limpiadas para esta cuenta, y solo las que su campana
 *   muestra.
 *
 * Fila por fila, para que cada baja pase por la autoría y la bitácora
 * (invariante 9).
 */
final class LimpiarNotificaciones
{
    public function __construct(
        private readonly EstadoDeAlertaPorCuenta $alertas,
        private readonly LecturaPanelOperaciones $operaciones,
    ) {}

    /**
     * @param  list<int>  $idsAlerta  las alertas que la campana de esta cuenta muestra;
     *                                quien llama ya verificó que puede verlas. Las que no
     *                                existen se ignoran.
     * @return int cantidad de avisos y alertas que se limpiaron
     */
    public function ejecutar(int $usuarioId, array $idsAlerta = []): int
    {
        $limpiadas = 0;

        foreach (Notificacion::query()->deUsuario($usuarioId)->get() as $notificacion) {
            $notificacion->delete();
            $limpiadas++;
        }

        foreach ($this->operaciones->idsAlertasExistentes($idsAlerta) as $alertaId) {
            if ($this->alertas->limpiar($usuarioId, $alertaId)) {
                $limpiadas++;
            }
        }

        return $limpiadas;
    }
}
