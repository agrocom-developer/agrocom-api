<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Abrir un aviso desde la campana: lo busca ENTRE LOS DE LA CUENTA que lo abre
 * y lo marca leído. El aviso de otra cuenta no existe para quien lo pide
 * (`ModelNotFoundException`, que el panel devuelve como 404): nunca se
 * distingue «no existe» de «es de otro», para no confirmar ids ajenos.
 *
 * Devuelve el aviso para que quien llama resuelva el destino; qué URL le toca
 * a cada rol no se decide acá (ADR 0025 punto 6).
 *
 * Con la vista «como otro usuario» activa (tarea 140) no se marca leído: esa
 * escritura quedaría firmada por la cuenta observada y el modo de solo lectura
 * la rechazaría con un 403. El aviso se devuelve igual, así el click sigue
 * llevando a su destino.
 */
final class AbrirNotificacion
{
    /**
     * @throws ModelNotFoundException si la cuenta no tiene un aviso con ese id.
     */
    public function ejecutar(int $usuarioId, int $notificacionId): Notificacion
    {
        $notificacion = Notificacion::query()
            ->deUsuario($usuarioId)
            ->findOrFail($notificacionId);

        if ($notificacion->leida_en === null && ! ModoSoloLectura::activo()) {
            $notificacion->leida_en = CarbonImmutable::now();
            $notificacion->save();
        }

        return $notificacion;
    }
}
