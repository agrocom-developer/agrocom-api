<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\CerrarOtrasSesiones;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * `GET/POST /portal/restablecer` (tarea 66): mismo mecanismo que
 * {@see RestablecerContrasenaController}, para el guard `cliente` — un
 * token emitido por `Password::broker('interno')` no sirve acá: el broker
 * `cliente` resuelve el usuario vía el provider `usuarios_cliente`
 * (`SecUsuarioCliente`, `type = 'cliente'`), así que el email de una cuenta
 * interna simplemente no matchea ningún usuario de este broker.
 */
final class RestablecerContrasenaPortalController
{
    public function create(Request $request, string $token): View
    {
        return view('seguridad::pages.restablecer', [
            'accion' => route('portal.restablecer.store'),
            'volverA' => route('portal.login.form'),
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

        $estado = Password::broker('cliente')->reset(
            [...$datos, 'state' => true],
            function (SecUser $usuario, string $password) use ($cerrarOtrasSesiones, $request): void {
                $usuario->password = $password;
                $usuario->updated_by = $usuario->id;
                $usuario->save();

                $cerrarOtrasSesiones->ejecutar($usuario, 'cliente', $request->session()->getId(), $password);
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return redirect()
                ->route('portal.login.form')
                ->withErrors(['email' => __('seguridad.restablecer.token_invalido')]);
        }

        return redirect()
            ->route('portal.login.form')
            ->with('estado', __('seguridad.restablecer.actualizada'));
    }
}
