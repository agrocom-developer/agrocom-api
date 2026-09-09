<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\CerrarOtrasSesiones;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * `GET/POST /restablecer` (tarea 66): fija la contraseña nueva desde el
 * enlace del correo, para el guard `interno`. `state: true` en las
 * credenciales del broker (ver {@see RecuperarContrasenaController}):
 * una cuenta bloqueada A MITAD del período de validez del token (60 min)
 * tampoco puede restablecer, aunque el token en sí siga siendo válido.
 */
final class RestablecerContrasenaController
{
    public function create(Request $request, string $token): View
    {
        return view('seguridad::pages.restablecer', [
            'accion' => route('restablecer.store'),
            'volverA' => route('login.form'),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, CerrarOtrasSesiones $cerrarOtrasSesiones): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $estado = Password::broker('interno')->reset(
            [...$datos, 'state' => true],
            function (SecUser $usuario, string $password) use ($cerrarOtrasSesiones, $request): void {
                $usuario->password = $password;
                $usuario->updated_by = $usuario->id;
                $usuario->save();

                $cerrarOtrasSesiones->ejecutar($usuario, 'interno', $request->session()->getId(), $password);
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return redirect()
                ->route('login.form')
                ->withErrors(['email' => __('seguridad.restablecer.token_invalido')]);
        }

        return redirect()
            ->route('login.form')
            ->with('estado', __('seguridad.restablecer.actualizada'));
    }
}
