<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaVencida;
use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El proceso programado que vence las ofertas sin respuesta y abre la ronda
 * siguiente (ADR 0030, «hace falta un proceso programado»). Corre cada minuto
 * (`asignacion:procesar`).
 *
 * Idempotente: una oferta ya vencida no se toca, y la ronda siguiente se abre
 * una sola vez aunque el proceso corra dos veces o dos procesos se crucen (el
 * bloqueo de la solicitud y el índice único de rondas lo garantizan). Un fallo
 * en una solicitud se reporta y no detiene a las demás.
 */
final class VencerOfertas
{
    public function __construct(
        private readonly MaquinaEstadosOferta $ofertas,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly ContinuarAsignacion $continuar,
        private readonly EvaluarProveedor $evaluar,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /** @return int ofertas que se vencieron en esta corrida. */
    public function ejecutar(?Carbon $ahora = null): int
    {
        $ahora ??= Carbon::now();
        $vencidas = 0;

        $solicitudes = Oferta::query()
            ->where('estado', EstadoOferta::Ofrecida->value)
            ->where('vence_en', '<=', $ahora)
            ->distinct()
            ->orderBy('solicitud_id')
            ->pluck('solicitud_id');

        foreach ($solicitudes as $solicitudId) {
            try {
                $proveedoresAfectados = [];

                $vencidas += DB::transaction(function () use ($solicitudId, $ahora, &$proveedoresAfectados): int {
                    $solicitud = $this->solicitudes->datosBloqueando((int) $solicitudId);

                    $paraVencer = Oferta::query()
                        ->where('solicitud_id', $solicitudId)
                        ->where('estado', EstadoOferta::Ofrecida->value)
                        ->where('vence_en', '<=', $ahora)
                        ->lockForUpdate()
                        ->get();

                    foreach ($paraVencer as $oferta) {
                        $this->ofertas->transicionar($oferta, EstadoOferta::Vencida);
                        $proveedoresAfectados[] = $oferta->proveedor_id;

                        event(new OfertaVencida(
                            $oferta->id,
                            $oferta->solicitud_id,
                            $oferta->proveedor_id,
                            $this->proveedores->usuariosDeAviso($oferta->proveedor_id),
                        ));
                    }

                    if ($solicitud !== null && $paraVencer->isNotEmpty()) {
                        $this->continuar->tras($solicitud);
                    }

                    return $paraVencer->count();
                });

                foreach (array_unique($proveedoresAfectados) as $proveedorId) {
                    $this->evaluar->ejecutar($proveedorId, $ahora);
                }
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $vencidas;
    }
}
