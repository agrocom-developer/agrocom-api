<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/usuarios` (`panel.usuarios.index`): placeholder mínimo del
 * ítem de menú "Usuarios" ya sembrado en `sec_menu` ({@see SecMenuSeeder}) —
 * la pantalla real de gestión de usuarios (CRUD) es alcance de una HU
 * futura, NO de HU-02. Este controlador existe únicamente para que ese ítem
 * de menú no sea un link roto mientras esa HU no se implemente.
 *
 * Autorización por el permiso `seguridad.usuario.ver` del ROL ACTIVO (nunca
 * la unión de todos los roles del usuario, invariante 10 de `CLAUDE.md`):
 * es el mismo permiso que ya gatea la visibilidad del ítem de menú en
 * {@see ObtenerMenuPorRolActivo}, así que esta verificación es la última
 * línea de defensa server-side ante quien navegue a la URL a mano sin ver el
 * ítem de menú (el filtrado del menú es solo presentación, no autorización).
 */
final class UsuariosController
{
    public function index(Request $request, CascaraPanel $cascara): View
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        abort_unless($usuario->tienePermisoEnRol('seguridad.usuario.ver', $idRolActivo), 403);

        return view('seguridad::pages.usuarios.index', $cascara->para($usuario, $idRolActivo));
    }
}
