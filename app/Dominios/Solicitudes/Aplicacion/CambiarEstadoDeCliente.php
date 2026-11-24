<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosCliente;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Suspender a un cliente o reactivarlo desde el panel (especificación §4.1: un
 * cliente suspendido no puede solicitar servicios). Suspender exige motivo y lo
 * guarda junto con el momento; reactivar limpia ambos. Quién lo hizo y el valor
 * anterior quedan en la bitácora de auditoría (invariante 9). Las solicitudes ya
 * abiertas del cliente no se tocan: las cancela soporte una por una, con motivo.
 */
final class CambiarEstadoDeCliente
{
    public function __construct(private readonly MaquinaEstadosCliente $estados) {}

    /**
     * @throws SolicitudInvalida si falta el motivo al suspender.
     */
    public function suspender(int $clienteId, string $motivo): Cliente
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw SolicitudInvalida::motivoRequerido();
        }

        return $this->cambiar($clienteId, EstadoCliente::Suspendido, $motivo);
    }

    public function reactivar(int $clienteId): Cliente
    {
        return $this->cambiar($clienteId, EstadoCliente::Activo, null);
    }

    private function cambiar(int $clienteId, EstadoCliente $nuevo, ?string $motivo): Cliente
    {
        return DB::transaction(function () use ($clienteId, $nuevo, $motivo): Cliente {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteId);

            return $this->estados->transicionar($cliente, $nuevo, $motivo);
        });
    }
}
