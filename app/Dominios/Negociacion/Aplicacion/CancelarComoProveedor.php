<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Cancelacion;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use App\Dominios\Solicitudes\Contratos\SolicitudNoNegociable;
use Illuminate\Support\Facades\DB;

/**
 * El proveedor adjudicado devuelve la solicitud al mercado (`POST
 * /solicitudes/{id}/cancelar-proveedor`, `negociacion-contrato.md` §1): motivo
 * obligatorio, libera proveedor y equipo, queda en `neg_cancelaciones` (alimenta la
 * tasa de cancelación) y ya no puede volver a ofertar en esa solicitud. Las demás ofertas
 * siguen cerradas y el chat queda de solo lectura. Sin penalidad en dinero.
 */
final class CancelarComoProveedor
{
    public function __construct(
        private readonly Participantes $participantes,
        private readonly PasosDeSolicitud $solicitudes,
        private readonly MaquinaEstadosOferta $estados,
        private readonly Avisador $avisos,
    ) {}

    /** @throws NegociacionInvalida */
    public function ejecutar(int $usuarioId, int $solicitudId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw NegociacionInvalida::dato('motivo_requerido', 'motivo');
        }

        DB::transaction(function () use ($usuarioId, $solicitudId, $motivo): void {
            $solicitud = $this->participantes->delEquipo($usuarioId, $solicitudId, true, true);

            if ($solicitud->estado !== 'adjudicada') {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            Oferta::query()
                ->where('solicitud_id', $solicitudId)
                ->where('estado', EstadoOferta::Aceptada->value)
                ->lockForUpdate()
                ->get()
                ->each(fn (Oferta $oferta) => $this->estados->transicionar($oferta, EstadoOferta::Cerrada));

            Cancelacion::query()->create(['solicitud_id' => $solicitudId, 'proveedor_id' => $solicitud->proveedorId, 'parte' => 'proveedor', 'motivo' => $motivo]);

            try {
                $this->solicitudes->devolverAbierta($solicitudId, $usuarioId, $motivo);
            } catch (SolicitudNoNegociable) {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            $this->avisos->avisar('solicitud_reabierta', "solicitud_reabierta:{$solicitudId}:".now()->timestamp, ['solicitud' => $solicitudId], $solicitudId, [$solicitud->clienteUsuarioId]);
        });
    }
}
