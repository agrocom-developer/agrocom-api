<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ActualizarPerfilPropio;
use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarPerfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * `GET/PUT /panel/perfil` (tarea 66): autoservicio del guard `interno` —
 * cualquier usuario, con cualquier rol activo, sin permiso de grano fino:
 * el sujeto de la operación es siempre el propio usuario autenticado, nunca
 * un `{usuario}` de ruta (a diferencia de `UsuariosController`, que
 * administra cuentas AJENAS). Adaptador delgado (ADR 0008) sobre
 * {@see ActualizarPerfilPropio}.
 */
final class PerfilController
{
    public function edit(Request $request, CascaraPanel $cascara): View
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return view('seguridad::pages.perfil.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            ['usuario' => $usuario],
        ));
    }

    public function update(ActualizarPerfilRequest $request, ActualizarPerfilPropio $actualizar): RedirectResponse
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $datos = $request->validated();

        try {
            $actualizar->ejecutar(
                usuario: $usuario,
                guard: 'interno',
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
                ->route('panel.perfil.edit')
                ->withErrors(['email' => $excepcion->getMessage()])
                ->withInput($request->except(['password', 'password_confirmation', 'password_actual']));
        }

        return redirect()
            ->route('panel.perfil.edit')
            ->with('estado', __('seguridad.perfil.actualizado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
