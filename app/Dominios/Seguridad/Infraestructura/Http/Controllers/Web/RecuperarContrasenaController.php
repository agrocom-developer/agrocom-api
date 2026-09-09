<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * `POST /recuperar` (tarea 66; ADR 0004, ampliación 9/9/2026): pide el
 * enlace de restablecimiento para el guard `interno`. Responde SIEMPRE el
 * mismo mensaje, exista o no la cuenta, esté bloqueada o no — nunca revela
 * qué correos existen en el sistema (invariante de negocio de esta tarea).
 *
 * `state: true` en las credenciales del broker: `EloquentUserProvider`
 * agrega un `WHERE` por cada clave que no sea `password`, así que una
 * cuenta bloqueada (`seguridad.usuario.bloquear`) queda fuera de la
 * búsqueda igual que un correo inexistente — mismo mecanismo que ya usa
 * `SesionController` para el login.
 *
 * Rate limit por email: `config('auth.passwords.interno.throttle')` (60 s,
 * lo aplica el propio broker). Por IP: `throttle:6,1` en la ruta.
 */
final class RecuperarContrasenaController
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::broker('interno')->sendResetLink([
            'email' => $datos['email'],
            'state' => true,
        ]);

        return redirect()
            ->route('login.form')
            ->with('estado', __('seguridad.recuperar.estado_generico'));
    }
}
