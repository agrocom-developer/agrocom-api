<?php

namespace App\Dominios\Solicitudes\Aplicacion\MaquinaEstados;

use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\TransicionesCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;

/**
 * Única clase que escribe `sol_clientes.estado` (invariante 7 de CLAUDE.md):
 * consulta {@see TransicionesCliente} y, al suspender, guarda el motivo y el
 * momento; al reactivar, los limpia. Pedir el estado en que el cliente ya está
 * no hace nada. La bitácora registra quién y el valor anterior (invariante 9).
 */
final class MaquinaEstadosCliente
{
    public function transicionar(Cliente $cliente, EstadoCliente $destino, ?string $motivo): Cliente
    {
        if ($cliente->estado === $destino || ! TransicionesCliente::permitida($cliente->estado, $destino)) {
            return $cliente;
        }

        $cliente->estado = $destino;
        $cliente->motivo_suspension = $destino === EstadoCliente::Suspendido ? $motivo : null;
        $cliente->suspendido_en = $destino === EstadoCliente::Suspendido ? now() : null;
        $cliente->save();

        return $cliente;
    }
}
