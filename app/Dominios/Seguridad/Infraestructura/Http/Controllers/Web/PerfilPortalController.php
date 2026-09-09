<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ActualizarPerfilPropio;
use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarPerfilPortalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * `GET/PUT /portal/perfil` (tarea 66): mismo mecanismo que
 * {@see PerfilController}, para el guard `cliente` — {@see ActualizarPerfilPropio}
 * es agnóstico de guard (recibe el nombre como parámetro), así que ambos
 * controladores reusan el mismo caso de uso, cada uno resolviendo su propio
 * usuario/cáscara.
 */
final class PerfilPortalController
{
    public function edit(Request $request, AutorizacionPortalCliente $autorizacion): View
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('cliente');

        return view('seguridad::pages.portal-perfil', array_merge(
            $autorizacion->cascara($request),
            ['usuario' => $usuario],
        ));
    }

    public function update(ActualizarPerfilPortalRequest $request, ActualizarPerfilPropio $actualizar): RedirectResponse
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('cliente');
        $datos = $request->validated();

        try {
            $actualizar->ejecutar(
                usuario: $usuario,
                guard: 'cliente',
                idSesionActual: $request->session()->getId(),
                name: (string) $datos['name'],
                email: $this->cadenaONull($datos['email'] ?? null),
                passwordActual: $this->cadenaONull($datos['password_actual'] ?? null),
                passwordNueva: $this->cadenaONull($datos['password'] ?? null),
            );
        } catch (ContrasenaActualIncorrecta $excepcion) {
            throw ValidationException::withMessages(['password_actual' => [$excepcion->getMessage()]]);
        } catch (UsuarioDuplicado $excepcion) {
            return redirect()
                ->route('portal.perfil.edit')
                ->withErrors(['email' => $excepcion->getMessage()])
                ->withInput($request->except(['password', 'password_confirmation', 'password_actual']));
        }

        return redirect()
            ->route('portal.perfil.edit')
            ->with('estado', __('seguridad.perfil.actualizado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
