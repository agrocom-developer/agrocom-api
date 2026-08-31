<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use Illuminate\Support\Collection;

/**
 * Todos los dispositivos con sesión viva, para la pantalla de revocación del
 * panel (HU-03, CA "revocable desde el panel").
 *
 * A diferencia de {@see ListarDispositivosDeUsuario} no está acotado a un
 * usuario: acá el actor es alguien del panel con el permiso
 * `seguridad.dispositivo.ver` mirando la flota entera — quién tiene sesión
 * abierta y desde qué teléfono. Ese permiso lo verifica el controlador contra
 * el rol activo antes de llegar hasta acá.
 *
 * Con `$incluirRevocados` se agregan los borrados lógicos, que es el
 * histórico de auditoría (ADR 0007): qué dispositivo perdió el acceso y
 * cuándo.
 */
final class ListarDispositivosRegistrados
{
    /** @return Collection<int, SecTokenDispositivo> */
    public function ejecutar(bool $incluirRevocados = false): Collection
    {
        return SecTokenDispositivo::query()
            ->when($incluirRevocados, fn ($consulta) => $consulta->withTrashed())
            ->with(['rol', 'tokenable'])
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }
}
