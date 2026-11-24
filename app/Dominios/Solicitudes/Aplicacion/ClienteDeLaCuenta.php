<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;

/**
 * Resuelve el cliente de la cuenta autenticada. Es el ancla del alcance de
 * toda la API del cliente (invariante 5 de CLAUDE.md): cada consulta parte de
 * `cliente_id`, nunca de la tabla global con un `where` agregado después.
 */
final class ClienteDeLaCuenta
{
    /** @throws SolicitudInvalida si la cuenta no tiene ficha de cliente. */
    public function resolver(int $usuarioId): Cliente
    {
        $cliente = Cliente::query()->where('usuario_id', $usuarioId)->first();

        if ($cliente === null) {
            throw SolicitudInvalida::sinCliente();
        }

        return $cliente;
    }

    /**
     * Como {@see resolver()} pero exige que pueda pedir servicios.
     *
     * @throws SolicitudInvalida si la cuenta no tiene ficha o está suspendida.
     */
    public function activo(int $usuarioId): Cliente
    {
        $cliente = $this->resolver($usuarioId);

        if ($cliente->estado === EstadoCliente::Suspendido) {
            throw SolicitudInvalida::clienteSuspendido();
        }

        return $cliente;
    }
}
