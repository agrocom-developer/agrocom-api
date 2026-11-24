<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use Illuminate\Support\Facades\DB;

/**
 * Intervención del administrador en una negociación (`marketplace.md` §10.4):
 * cierra, con motivo, la oferta vigente de UN proveedor sobre una solicitud que
 * sigue abierta al mercado. Sirve para frenar una negociación abusiva o de un
 * proveedor en revisión sin tocar el resto: las ofertas de los demás siguen y,
 * si era la única, la solicitud vuelve a `abierta`.
 *
 * Sigue las mismas reglas del motor: bloquea primero la fila de la solicitud y
 * después la oferta, el cambio de estado pasa por {@see MaquinaEstadosOferta} y
 * avisa al cliente y a las cuentas del proveedor. La oferta queda `cerrada` con
 * su motivo y el autor en la bitácora; no es una penalidad (invariante 14): no
 * restringe cuentas.
 */
final class IntervenirNegociacion
{
    public function __construct(
        private readonly PasosDeSolicitud $solicitudes,
        private readonly LecturaProveedores $proveedores,
        private readonly MaquinaEstadosOferta $estados,
        private readonly Negociar $negociar,
        private readonly Avisador $avisos,
    ) {}

    /**
     * @throws NegociacionInvalida `solicitud_ajena` (404), `solicitud_no_abierta` o `oferta_no_vigente` (409).
     */
    public function cerrar(int $solicitudId, int $proveedorId, string $motivo): Oferta
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw NegociacionInvalida::dato('motivo_requerido', 'motivo');
        }

        return DB::transaction(function () use ($solicitudId, $proveedorId, $motivo): Oferta {
            $solicitud = $this->solicitudes->datos($solicitudId, true) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');

            if (! $solicitud->enMercado()) {
                throw NegociacionInvalida::conflicto('solicitud_no_abierta');
            }

            $oferta = Oferta::query()
                ->where('solicitud_id', $solicitudId)
                ->where('proveedor_id', $proveedorId)
                ->where('estado', EstadoOferta::Vigente->value)
                ->lockForUpdate()
                ->first() ?? throw NegociacionInvalida::conflicto('oferta_no_vigente');

            $this->estados->transicionar($oferta, EstadoOferta::Cerrada, $motivo);
            $this->negociar->reabrirSiNoQuedan($solicitudId);

            $this->avisos->avisar(
                'oferta_retirada',
                "oferta_cerrada_por_administrador:{$oferta->id}",
                ['solicitud' => $solicitudId, 'motivo' => $motivo],
                $solicitudId,
                [$solicitud->clienteUsuarioId, ...$this->proveedores->usuariosDeAviso($proveedorId)],
            );

            return $oferta;
        });
    }
}
