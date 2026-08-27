<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Seguridad\Dominio\TipoUsuario;

/**
 * Modelo de autenticación del guard `cliente` (`config/auth.php`): portal
 * del cliente. Ver {@see SecUsuarioInterno} — mismo mecanismo, filtrando
 * `type = 'cliente'`. Sin consumidores todavía (el portal es una HU
 * posterior); se deja resuelto ahora para no dejar dos sistemas de auth en
 * paralelo mientras tanto.
 */
class SecUsuarioCliente extends SecUser
{
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('tipo_cliente', function ($consulta): void {
            $consulta->where('type', TipoUsuario::Cliente->value);
        });
    }
}
