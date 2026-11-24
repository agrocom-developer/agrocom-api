<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Conformidad tácita (especificación §5.1): una solicitud que lleva más del
 * plazo (72 horas por defecto) en `ejecutada`, sin conformidad ni disputa, pasa a
 * `completada`. Idempotente: una ya completada o en disputa no se toca, y un
 * fallo en una solicitud se reporta sin detener a las demás.
 */
final class CompletarSolicitudesVencidas
{
    public function __construct(
        private readonly CompletarServicio $completar,
        private readonly ParametrosDeServicio $parametros,
    ) {}

    /** @return int cuántas solicitudes se completaron. */
    public function ejecutar(?Carbon $ahora = null, int $limite = 200): int
    {
        $ahora ??= Carbon::now();
        $limiteDeEjecucion = $ahora->copy()->subHours($this->parametros->conformidadHoras());

        $ids = HistorialSolicitud::query()
            ->where('estado_nuevo', EstadoSolicitud::Ejecutada->value)
            ->where('created_at', '<=', $limiteDeEjecucion)
            ->whereIn('solicitud_id', SolicitudServicio::query()->where('estado', EstadoSolicitud::Ejecutada->value)->select('id'))
            ->orderBy('id')
            ->limit($limite)
            ->pluck('solicitud_id')
            ->unique()
            ->all();

        $completadas = 0;

        foreach ($ids as $id) {
            try {
                $solicitud = SolicitudServicio::query()->find($id);

                if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::Ejecutada) {
                    continue;
                }

                $this->completar->ejecutar($solicitud, ActorSolicitud::Sistema, null, __('solicitudes.conformidad.tacita'));
                $completadas++;
            } catch (TransicionSolicitudNoPermitida) {
                // Otro actor la movió entre la lectura y el paso (disputa o conformidad): nada que hacer.
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $completadas;
    }
}
