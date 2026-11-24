<?php

namespace App\Dominios\Solicitudes\Aplicacion\MaquinaEstados;

use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;

/**
 * Única clase que cambia el `estado` de un cliente (invariante 7 de CLAUDE.md):
 * `activo` ⇄ `suspendido`. Suspender exige motivo y lo guarda; reactivar lo
 * limpia. El cambio queda en la bitácora con quién lo hizo (invariante 9).
 */
final class MaquinaEstadosCliente
{
    /**
     * @throws SolicitudInvalida si ya está en ese estado, o falta el motivo al suspender.
     */
    public function cambiar(Cliente $cliente, EstadoCliente $hacia, ?string $motivo): Cliente
    {
        if ($cliente->estado === $hacia) {
            throw SolicitudInvalida::clienteYaEnEseEstado();
        }

        $motivo = $motivo === null ? null : trim($motivo);

        if ($hacia === EstadoCliente::Suspendido && ($motivo === null || $motivo === '')) {
            throw SolicitudInvalida::motivoRequerido();
        }

        $cliente->forceFill([
            'estado' => $hacia,
            'motivo_estado' => $hacia === EstadoCliente::Suspendido ? $motivo : null,
        ])->save();

        return $cliente;
    }
}
