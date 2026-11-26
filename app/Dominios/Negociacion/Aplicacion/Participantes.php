<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;

/**
 * Quién es quién en una solicitud adjudicada (invariante 5 de CLAUDE.md): el cliente
 * de la solicitud, o la cuenta del proveedor adjudicado (titular o piloto). Cualquier otra cuenta recibe 404: no se revela
 * que la solicitud existe.
 */
final class Participantes
{
    public const string CLIENTE = 'cliente';

    public const string PROVEEDOR = 'proveedor';

    public function __construct(
        private readonly PasosDeSolicitud $solicitudes,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
    ) {}

    /**
     * La solicitud y el lado de la cuenta (`cliente` o `proveedor`).
     *
     * @return array{0: DatosSolicitudNegociable, 1: string}
     *
     * @throws NegociacionInvalida `solicitud_ajena` (404)
     */
    public function lado(int $usuarioId, int $solicitudId, bool $bloquear = false): array
    {
        $solicitud = $this->solicitudes->datos($solicitudId, $bloquear) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');

        if ($solicitud->clienteUsuarioId === $usuarioId) {
            return [$solicitud, self::CLIENTE];
        }

        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);
        if ($proveedor !== null && $solicitud->proveedorId === $proveedor->id) {
            return [$solicitud, self::PROVEEDOR];
        }

        throw NegociacionInvalida::ajeno('solicitud_ajena');
    }

    /**
     * La solicitud, si la cuenta es del equipo adjudicado (titular o piloto del equipo).
     *
     * @throws NegociacionInvalida `solicitud_ajena` (404), `equipo_ajeno` (403)
     */
    public function delEquipo(int $usuarioId, int $solicitudId, bool $soloPiloto, bool $bloquear = false): DatosSolicitudNegociable
    {
        $solicitud = $this->solicitudes->datos($solicitudId, $bloquear) ?? throw NegociacionInvalida::ajeno('solicitud_ajena');

        if ($solicitud->proveedorId === null) {
            throw NegociacionInvalida::ajeno('solicitud_ajena');
        }

        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId);
        if ($proveedor !== null && $proveedor->id === $solicitud->proveedorId) {
            return $solicitud;
        }

        if (! $soloPiloto) {
            if ($solicitud->equipoId !== null && in_array($solicitud->equipoId, $this->equipos->equiposDeCuenta($usuarioId), true)) {
                return $solicitud;
            }
        }

        throw NegociacionInvalida::ajeno('solicitud_ajena');
    }

    /** @return list<int> cuentas del proveedor adjudicado a avisar. */
    public function cuentasDelProveedor(DatosSolicitudNegociable $solicitud): array
    {
        return $solicitud->proveedorId === null ? [] : $this->proveedores->usuariosDeAviso($solicitud->proveedorId);
    }
}
