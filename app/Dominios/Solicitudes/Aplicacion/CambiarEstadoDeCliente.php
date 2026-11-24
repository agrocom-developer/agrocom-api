<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosCliente;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Suspende o reactiva a un cliente desde el panel. Un cliente suspendido no
 * puede publicar solicitudes nuevas; lo que ya tiene en curso sigue su camino y
 * se atiende por solicitud (cancelar o intervenir). No es una sanción
 * automática: la decide una persona, con motivo (invariante 14).
 */
final class CambiarEstadoDeCliente
{
    public function __construct(private readonly MaquinaEstadosCliente $estados) {}

    /** @throws SolicitudInvalida */
    public function suspender(int $clienteId, string $motivo): Cliente
    {
        return $this->cambiar($clienteId, EstadoCliente::Suspendido, $motivo);
    }

    /** @throws SolicitudInvalida */
    public function reactivar(int $clienteId): Cliente
    {
        return $this->cambiar($clienteId, EstadoCliente::Activo, null);
    }

    private function cambiar(int $clienteId, EstadoCliente $hacia, ?string $motivo): Cliente
    {
        return DB::transaction(function () use ($clienteId, $hacia, $motivo): Cliente {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteId);

            return $this->estados->cambiar($cliente, $hacia, $motivo);
        });
    }
}
