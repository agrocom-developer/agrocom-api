<?php

namespace Database\Seeders\Demo;

use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Utilidades compartidas por la familia demo.
 */
trait SoporteDemo
{
    /**
     * UUID determinista a partir de una clave: dos corridas producen el mismo
     * `uuid_cliente`, así la idempotencia por `UNIQUE (uuid_cliente)`
     * (invariante 1) funciona igual que con un dispositivo real reintentando.
     */
    protected function uuid(string $tipo, string $clave): string
    {
        $hash = md5("agrocom-demo-2026:{$tipo}:{$clave}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    protected function personaPorCi(string $ci): ?PerPersona
    {
        return PerPersona::query()->where('ci', $ci)->first();
    }

    protected function personaIdPorCi(string $ci): int
    {
        return (int) PerPersona::query()->where('ci', $ci)->value('id');
    }

    protected function basePorNombre(string $nombre): ?PerBase
    {
        return PerBase::query()->where('nombre', $nombre)->first();
    }

    protected function usuarioPorUsername(string $username): ?SecUser
    {
        return SecUser::query()->where('username', $username)->first();
    }
}
