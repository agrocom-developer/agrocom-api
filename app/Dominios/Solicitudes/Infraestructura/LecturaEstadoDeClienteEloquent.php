<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Solicitudes\Contratos\LecturaEstadoDeCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;

/** Implementación Eloquent de {@see LecturaEstadoDeCliente}. */
final class LecturaEstadoDeClienteEloquent implements LecturaEstadoDeCliente
{
    public function deLaCuenta(int $usuarioId): ?EstadoAprobacion
    {
        return Cliente::query()->where('usuario_id', $usuarioId)->value('estado');
    }
}
