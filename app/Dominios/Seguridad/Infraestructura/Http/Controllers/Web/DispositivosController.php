<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Aplicacion\ListarDispositivosRegistrados;
use App\Dominios\Seguridad\Aplicacion\RevocarTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET /panel/dispositivos` y `DELETE /panel/dispositivos/{dispositivo}`:
 * la pantalla desde la que se revoca el acceso de un dispositivo de campo
 * (HU-03, CA "revocable desde el panel"). Es lo que se hace cuando un
 * teléfono se pierde o alguien deja la empresa.
 *
 * Adaptador delgado (ADR 0008): el efecto de revocar vive en
 * {@see RevocarTokenDispositivo}, compartido con el endpoint de la app que
 * cierra su propia sesión. Lo que resuelve este controlador —y no el caso de
 * uso— es la guarda de autorización, porque no es la misma para las dos
 * superficies: acá es un permiso del ROL ACTIVO de la sesión de panel, allá
 * es "el token es el mío".
 *
 * El permiso se evalúa contra el rol activo, nunca contra la unión de roles
 * (invariante 10 de CLAUDE.md), y se verifica en el controlador igual que en
 * {@see UsuariosController}: que el ítem de menú no se vea sin permiso es
 * presentación, no autorización — quien escriba la URL a mano tiene que
 * chocar contra esta verificación.
 *
 * **No cruza ninguna frontera de módulo**: `sec_token_dispositivo` es del
 * módulo `Seguridad` y esta pantalla también, así que el panel y la API de
 * campo son dos adaptadores del mismo módulo sobre el mismo caso de uso.
 */
final class DispositivosController
{
    private const PERMISO_VER = 'seguridad.dispositivo.ver';

    private const PERMISO_REVOCAR = 'seguridad.dispositivo.revocar';

    public function index(
        Request $request,
        CascaraPanel $cascara,
        ListarDispositivosRegistrados $listarDispositivos,
    ): View {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        abort_unless($usuario->tienePermisoEnRol(self::PERMISO_VER, $idRolActivo), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $rolId = $request->integer('rol_id') ?: null;

        return view('seguridad::pages.dispositivos.index', [
            ...$cascara->para($usuario, $idRolActivo),
            'dispositivos' => $listarDispositivos->ejecutar($busqueda !== '' ? $busqueda : null, $rolId),
            'rolesDisponibles' => $this->rolesConDispositivos(),
            'filtros' => ['q' => $busqueda, 'rol_id' => $rolId],
        ]);
    }

    /**
     * Revoca el acceso de un dispositivo. Permiso propio, separado del de
     * ver: mirar quién tiene sesión abierta y dejar a alguien afuera en medio
     * de una jornada de vuelo no son la misma responsabilidad.
     */
    public function destroy(
        Request $request,
        SecTokenDispositivo $dispositivo,
        RevocarTokenDispositivo $revocarToken,
    ): RedirectResponse {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        abort_unless($usuario->tienePermisoEnRol(self::PERMISO_REVOCAR, $idRolActivo), 403);

        $revocarToken->ejecutar($dispositivo, $usuario);

        return redirect()
            ->route('panel.dispositivos.index')
            ->with('estado', __('seguridad.dispositivos.revocado'));
    }

    /**
     * Solo los roles con los que opera algún dispositivo con sesión: el filtro
     * no ofrece un rol que no traería nada.
     *
     * @return Collection<int, string> id => nombre legible del rol
     */
    private function rolesConDispositivos(): Collection
    {
        return SecRole::query()
            ->whereIn('id', SecTokenDispositivo::query()->select('role_id'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (SecRole $rol): array => [$rol->id => PresentadorRol::nombreLegible($rol)]);
    }
}
