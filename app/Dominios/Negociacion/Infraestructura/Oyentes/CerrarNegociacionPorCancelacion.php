<?php

namespace App\Dominios\Negociacion\Infraestructura\Oyentes;

use App\Dominios\Negociacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Negociacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Cancelacion;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use Illuminate\Support\Facades\DB;

/**
 * Al cancelarse una solicitud cierra sus ofertas vigentes, elimina la última ubicación del
 * piloto (privacidad, §6) y, si ya estaba adjudicada y la canceló el cliente, la registra en
 * `neg_cancelaciones` (§3.5). La cancelación del proveedor se registra en
 * {@see CancelarComoProveedor}.
 */
final class CerrarNegociacionPorCancelacion
{
    public function __construct(private readonly MaquinaEstadosOferta $estados) {}

    public function handle(SolicitudCancelada $evento): void
    {
        DB::transaction(function () use ($evento): void {
            Oferta::query()
                ->where('solicitud_id', $evento->solicitudId)
                ->where('estado', EstadoOferta::Vigente->value)
                ->lockForUpdate()
                ->get()
                ->each(fn (Oferta $oferta) => $this->estados->transicionar($oferta, EstadoOferta::Cerrada));

            DB::table('neg_ubicaciones')->where('solicitud_id', $evento->solicitudId)->delete();

            if ($evento->estadoPrevio === 'adjudicada' && $evento->canceladaPor === 'cliente') {
                Cancelacion::query()->create([
                    'solicitud_id' => $evento->solicitudId,
                    'proveedor_id' => $evento->proveedorId,
                    'parte' => 'cliente',
                    'motivo' => $evento->motivo,
                ]);
            }
        });
    }
}
