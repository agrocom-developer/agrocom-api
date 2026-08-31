<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Collection;

/**
 * Dispositivos con sesión viva del usuario autenticado (HU-03), para que la
 * app de campo pueda mostrar "dónde tenés sesión abierta".
 *
 * Consulta **desde `$usuario->tokens()`**, nunca desde
 * `SecTokenDispositivo::query()` con un `where('user_id', ...)` agregado
 * después: es la misma regla que la invariante 5 de CLAUDE.md fija para el
 * portal del cliente. La diferencia no es estética — si mañana alguien agrega
 * un filtro más y se olvida del `where`, la versión "desde la relación" sigue
 * acotada al dueño y la otra filtra la tabla entera.
 */
final class ListarDispositivosDeUsuario
{
    /** @return Collection<int, SecTokenDispositivo> */
    public function ejecutar(SecUser $usuario): Collection
    {
        return $usuario->tokens()
            ->with('rol')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }
}
