<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lo que el reloj hace por la negociación (`negociacion:procesar`, cada minuto;
 * `negociacion-contrato.md` §1): vence las ofertas cuya vigencia pasó, reevalúa las
 * solicitudes que se quedaron sin ofertas vigentes y expira las solicitudes cuya
 * vigencia terminó (cerrando sus ofertas). Idempotente: correrlo dos veces seguidas no
 * repite nada, y un fallo en una solicitud se reporta sin detener a las demás.
 */
final class ProcesarNegociacion
{
    public function __construct(
        private readonly PasosDeSolicitud $solicitudes,
        private readonly MaquinaEstadosOferta $estados,
        private readonly Negociar $negociar,
        private readonly Participantes $participantes,
        private readonly Avisador $avisos,
    ) {}

    /** @return array{ofertas_vencidas: int, solicitudes_expiradas: int} */
    public function ejecutar(?Carbon $ahora = null): array
    {
        $ahora ??= Carbon::now();
        $vencidas = 0;
        $expiradas = 0;

        $solicitudesAfectadas = [];

        // PostgreSQL vence todo el lote en una sola sentencia (`neg_vencer_ofertas`, auditoría L3);
        // las pruebas unitarias corren en SQLite, que conserva el recorrido fila por fila.
        if (DB::getDriverName() === 'pgsql') {
            foreach (DB::select('SELECT oferta_id, solicitud_id FROM '.DB::getTablePrefix().'neg_vencer_ofertas(?)', [$ahora->toDateTimeString()]) as $fila) {
                $vencidas++;
                $solicitudesAfectadas[(int) $fila->solicitud_id] = true;
            }
        } else {
            $this->vencerOfertasUnaPorUna($ahora, $vencidas, $solicitudesAfectadas);
        }

        foreach (array_keys($solicitudesAfectadas) as $solicitudId) {
            try {
                DB::transaction(function () use ($solicitudId): void {
                    $this->solicitudes->datos($solicitudId, true);
                    $this->negociar->reabrirSiNoQuedan($solicitudId);
                });
            } catch (Throwable $error) {
                report($error);
            }
        }

        $vencen = DB::table('sol_solicitudes_servicio')
            ->whereIn('estado', ['abierta', 'con_ofertas'])
            ->where('vence_en', '<=', $ahora)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($vencen as $solicitudId) {
            try {
                DB::transaction(function () use ($solicitudId, &$expiradas): void {
                    $solicitud = $this->solicitudes->datos((int) $solicitudId, true);

                    if ($solicitud === null || ! $solicitud->enMercado() || ! $this->solicitudes->expirar((int) $solicitudId)) {
                        return;
                    }

                    Oferta::query()->where('solicitud_id', $solicitudId)->where('estado', EstadoOferta::Vigente->value)->lockForUpdate()->get()
                        ->each(fn (Oferta $oferta) => $this->estados->transicionar($oferta, EstadoOferta::Cerrada));

                    $this->avisos->avisar('solicitud_expirada', "solicitud_expirada:{$solicitudId}:".now()->timestamp, ['solicitud' => (int) $solicitudId], (int) $solicitudId, [$solicitud->clienteUsuarioId]);
                    $expiradas++;
                });
            } catch (Throwable $error) {
                report($error);
            }
        }

        return ['ofertas_vencidas' => $vencidas, 'solicitudes_expiradas' => $expiradas];
    }

    /** @param  array<int, bool>  $solicitudesAfectadas */
    private function vencerOfertasUnaPorUna(Carbon $ahora, int &$vencidas, array &$solicitudesAfectadas): void
    {
        Oferta::query()->where('estado', EstadoOferta::Vigente->value)->where('vence_en', '<=', $ahora)->orderBy('id')->each(function (Oferta $oferta) use (&$vencidas, &$solicitudesAfectadas): void {
            try {
                DB::transaction(function () use ($oferta, &$vencidas, &$solicitudesAfectadas): void {
                    $this->solicitudes->datos($oferta->solicitud_id, true);
                    $actual = Oferta::query()->lockForUpdate()->find($oferta->id);

                    if ($actual !== null && $actual->estado === EstadoOferta::Vigente) {
                        $this->estados->transicionar($actual, EstadoOferta::Vencida);
                        $vencidas++;
                        $solicitudesAfectadas[$oferta->solicitud_id] = true;
                    }
                });
            } catch (Throwable $error) {
                report($error);
            }
        });
    }

    /**
     * `seguimiento:avisar-inactividad`: avisa a las partes si una solicitud `en_ejecucion` no tiene avance en `inactividad_horas`.
     *
     * @return int cuántas solicitudes se avisaron.
     */
    public function avisarInactividad(ParametrosDeNegociacion $parametros, ?Carbon $ahora = null): int
    {
        $ahora ??= Carbon::now();
        $limite = $ahora->copy()->subHours($parametros->inactividadHoras());
        $avisadas = 0;

        $ids = DB::table('sol_solicitudes_servicio')
            ->where('estado', 'en_ejecucion')
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(ultimo_avance_en, iniciada_en, adjudicada_en) <= ?', [$limite])
            ->pluck('id');

        foreach ($ids as $id) {
            $solicitud = $this->solicitudes->datos((int) $id);
            if ($solicitud === null) {
                continue;
            }

            $this->avisos->avisar('avance_registrado', "inactividad:{$id}:".$ahora->format('Ymd'), ['solicitud' => (int) $id, 'porcentaje' => $solicitud->porcentajeAvance], (int) $id, [$solicitud->clienteUsuarioId, ...$this->participantes->cuentasDelProveedor($solicitud)]);
            $avisadas++;
        }

        return $avisadas;
    }
}
