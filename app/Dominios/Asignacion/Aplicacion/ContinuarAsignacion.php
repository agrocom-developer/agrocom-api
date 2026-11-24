<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;
use Illuminate\Support\Carbon;

/**
 * Qué hacer después de que una oferta se resolvió sin aceptación (rechazada o
 * vencida): si ya no queda ninguna viva en el ciclo, la ronda se cierra y se
 * abre la siguiente (ADR 0030). Mientras quede una viva, espera: la ronda
 * termina cuando responde o vence la última.
 *
 * Solo actúa sobre una solicitud `en_asignacion`; sobre cualquier otra no hace
 * nada, así que llamarla de más es inofensivo (idempotencia).
 */
final class ContinuarAsignacion
{
    public function __construct(private readonly AvanzarAsignacion $avanzar) {}

    /** @param  DatosSolicitudParaAsignar  $solicitud  ya bloqueada por quien llama. */
    public function tras(DatosSolicitudParaAsignar $solicitud): void
    {
        if ($solicitud->estado !== 'en_asignacion') {
            return;
        }

        $ciclo = (int) Ronda::query()->where('solicitud_id', $solicitud->id)->max('ciclo');

        if ($ciclo === 0) {
            return;
        }

        $quedanVivas = Oferta::query()
            ->where('solicitud_id', $solicitud->id)
            ->where('ciclo', $ciclo)
            ->where('estado', 'ofrecida')
            ->exists();

        if ($quedanVivas) {
            return;
        }

        $ultima = (int) Ronda::query()->where('solicitud_id', $solicitud->id)->where('ciclo', $ciclo)->max('ronda');

        Ronda::query()
            ->where('solicitud_id', $solicitud->id)
            ->where('ciclo', $ciclo)
            ->whereNull('cerrada_en')
            ->get()
            ->each(fn (Ronda $ronda) => $ronda->forceFill(['cerrada_en' => Carbon::now()])->save());

        $this->avanzar->ejecutar($solicitud, $ciclo, $ultima + 1);
    }
}
