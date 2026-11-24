<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Support\Facades\DB;

/**
 * Toma una solicitud publicada y abre la primera ronda (ADR 0030): la deja
 * `en_asignacion` y ofrece primero a los propios. La llama el oyente de
 * `SolicitudPublicada` y, como red de seguridad, el proceso programado.
 *
 * Idempotente: tomar dos veces la misma solicitud no hace nada la segunda,
 * porque solo una `publicada` pasa a `en_asignacion` y el cambio es atómico
 * con la fila bloqueada. Una solicitud republicada tras `sin_proveedor` abre un
 * ciclo nuevo.
 */
final class IniciarAsignacion
{
    public function __construct(
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly AsignacionDeSolicitud $asignacion,
        private readonly AvanzarAsignacion $avanzar,
    ) {}

    /** @return bool `true` si tomó la solicitud en esta llamada. */
    public function ejecutar(int $solicitudId): bool
    {
        return DB::transaction(function () use ($solicitudId): bool {
            $datos = $this->solicitudes->datosBloqueando($solicitudId);

            if ($datos === null || $datos->estado !== 'publicada' || ! $this->asignacion->iniciar($solicitudId)) {
                return false;
            }

            $ciclo = (int) Ronda::query()->where('solicitud_id', $solicitudId)->max('ciclo') + 1;

            $this->avanzar->ejecutar($this->solicitudes->datos($solicitudId) ?? $datos, $ciclo, 1);

            return true;
        });
    }
}
