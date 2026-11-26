<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Aplicacion\ExigirCuentaAprobada;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\CuentaNoAprobada;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;

/**
 * Resuelve el cliente de la cuenta autenticada. Es el ancla del alcance de
 * toda la API del cliente (invariante 5 de CLAUDE.md): cada consulta parte de
 * `cliente_id`, nunca de la tabla global con un `where` agregado después.
 */
final class ClienteDeLaCuenta
{
    public function __construct(private readonly ExigirCuentaAprobada $compuerta) {}

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
     * Como {@see resolver()} pero exige que la cuenta pueda gestionar lo suyo: sus terrenos, sus
     * contactos y sus borradores. Un cliente `pendiente` o `rechazado` puede (ADR 0046, punto 12); uno
     * `suspendido` o dado de baja solo ve lo propio y su historial.
     *
     * @throws SolicitudInvalida si la cuenta no tiene ficha o está suspendida o dada de baja.
     */
    public function activo(int $usuarioId): Cliente
    {
        $cliente = $this->resolver($usuarioId);

        if (in_array($cliente->estado, [EstadoAprobacion::Suspendido, EstadoAprobacion::Baja], true)) {
            throw SolicitudInvalida::clienteSuspendido();
        }

        return $cliente;
    }

    /**
     * Como {@see resolver()} pero exige que la cuenta esté `aprobada`: publicar una solicitud, y aceptar o
     * contraofertar, son del cliente aprobado (ADR 0046, punto 12).
     *
     * @throws SolicitudInvalida si la cuenta no tiene ficha.
     * @throws CuentaNoAprobada si no está aprobada: `403 cuenta_no_aprobada` con su estado.
     */
    public function aprobado(int $usuarioId): Cliente
    {
        $cliente = $this->resolver($usuarioId);

        $this->compuerta->exigir($cliente->estado);

        return $cliente;
    }
}
