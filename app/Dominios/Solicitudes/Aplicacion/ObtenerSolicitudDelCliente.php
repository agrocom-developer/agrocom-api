<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * Una solicitud del cliente de la cuenta. Parte siempre de `cliente_id`
 * (invariante 5 de CLAUDE.md): el id de una solicitud ajena responde como si no
 * existiera.
 */
final class ObtenerSolicitudDelCliente
{
    public function __construct(private readonly ClienteDeLaCuenta $clientes) {}

    /** @throws SolicitudInvalida `solicitudAjena` (404) si no es del cliente de la cuenta. */
    public function ejecutar(int $usuarioId, int $solicitudId): SolicitudServicio
    {
        $cliente = $this->clientes->resolver($usuarioId);

        $solicitud = SolicitudServicio::query()
            ->where('cliente_id', $cliente->id)
            ->with(['terreno', 'historial'])
            ->find($solicitudId);

        if ($solicitud === null) {
            throw SolicitudInvalida::solicitudAjena();
        }

        return $solicitud;
    }
}
