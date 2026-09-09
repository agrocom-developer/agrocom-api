<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * `POST /portal/recuperar` (tarea 66): mismo mecanismo que
 * {@see RecuperarContrasenaController}, para el guard `cliente`.
 */
final class RecuperarContrasenaPortalController
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::broker('cliente')->sendResetLink([
            'email' => $datos['email'],
            'state' => true,
        ]);

        return redirect()
            ->route('portal.login.form')
            ->with('estado', __('seguridad.recuperar.estado_generico'));
    }
}
