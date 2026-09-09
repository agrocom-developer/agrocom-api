<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Notificaciones\RestablecerContrasena;

/**
 * Modelo de autenticación del guard `cliente` (`config/auth.php`): portal
 * del cliente. Ver {@see SecUsuarioInterno} — mismo mecanismo, filtrando
 * `type = 'cliente'`. Consumido desde HU-41 (tarea 55): login/logout del
 * portal y `AutorizacionPortalClienteSesion`.
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

    /**
     * Ver {@see SecUsuarioInterno::sendPasswordResetNotification()} — mismo
     * mecanismo, apuntando a la pantalla de restablecer del PORTAL.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = route('portal.restablecer.form', ['token' => $token, 'email' => $this->email]);

        $this->notify(new RestablecerContrasena($url));
    }
}
