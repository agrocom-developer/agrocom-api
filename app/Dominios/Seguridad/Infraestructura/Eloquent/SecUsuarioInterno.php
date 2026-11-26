<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Infraestructura\Notificaciones\RestablecerContrasena;

/**
 * Modelo de autenticación del guard `interno` (`config/auth.php`): el panel, con
 * cuentas de personal de Agrocom. `sec_user` es una tabla física compartida entre
 * cuentas de panel y de app (columna `tipo_cuenta`, ADR 0046); este subtipo aplica
 * un global scope `tipo_cuenta = 'panel'` para que el guard jamás autentique, ni
 * por error, una cuenta de app — la separación de guards no puede depender de que
 * cada llamador se acuerde de filtrar. Una cuenta de app que prueba entrar al panel
 * recibe el mismo rechazo que una credencial inválida.
 *
 * No agrega columnas ni tabla propia: es el mismo `sec_user` con un scope,
 * por eso no lleva su propio archivo de migración.
 */
class SecUsuarioInterno extends SecUser
{
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('tipo_panel', function ($consulta): void {
            $consulta->where('tipo_cuenta', TipoCuenta::Panel->value);
        });
    }

    /**
     * `Password::broker('interno')` (provider `usuarios_internos`, que
     * instancia ESTA clase) resuelve el usuario y llama a este método —
     * override de {@see SecUser} (que usa `CanResetPassword` tal cual) para que
     * el link apunte a la pantalla de restablecer del PANEL.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = route('restablecer.form', ['token' => $token, 'email' => $this->email]);

        $this->notify(new RestablecerContrasena($url));
    }
}
