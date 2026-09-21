<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * `POST /recuperar` (tarea 66; ADR 0004, ampliación 9/9/2026): pide el
 * enlace de restablecimiento. Responde SIEMPRE el mismo mensaje, exista o no
 * la cuenta, esté bloqueada o no — nunca revela qué correos existen en el
 * sistema (invariante de negocio de esta tarea).
 *
 * Un solo login para todos (16/9/2026), así que este es también el único
 * "recuperar acceso": prueba el broker `interno` y, si el correo no es de
 * una cuenta interna, el broker `cliente`. `sec_user.email` es único entre
 * cuentas vivas sin importar `type` (ADR 0004), así que a lo sumo un broker
 * encuentra la cuenta, y cada uno manda el enlace a SU pantalla de
 * restablecer (`/restablecer` o `/portal/restablecer`, ver
 * `sendPasswordResetNotification()` en cada subtipo de `SecUser`).
 *
 * `state: true` en las credenciales del broker: `EloquentUserProvider`
 * agrega un `WHERE` por cada clave que no sea `password`, así que una
 * cuenta bloqueada (`seguridad.usuario.bloquear`) queda fuera de la
 * búsqueda igual que un correo inexistente — mismo mecanismo que ya usa
 * `SesionController` para el login.
 *
 * Rate limit por email: `config('auth.passwords.*.throttle')` (60 s, lo
 * aplica el propio broker). Por IP: `throttle:6,1` en la ruta.
 */
final class RecuperarContrasenaController
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        foreach (['interno', 'cliente'] as $broker) {
            $estado = Password::broker($broker)->sendResetLink([
                'email' => $datos['email'],
                'state' => true,
            ]);

            if ($estado !== Password::INVALID_USER) {
                break;
            }
        }

        return redirect()
            ->route('login.form')
            ->with('estado', __('seguridad.recuperar.estado_generico'));
    }
}
