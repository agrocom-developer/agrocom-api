<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\TerminosBusqueda;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Aplicacion\BuscarEnElPanel;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/buscar?q=…` (9/9/2026): la pantalla del buscador del header,
 * que hasta ahora era un input de maqueta sin backend.
 *
 * Sin permiso propio a propósito. Buscar no es una capacidad que se conceda:
 * lo que se concede es VER cada cosa, y eso ya lo decide
 * {@see BuscarEnElPanel} bloque por bloque contra el rol activo. Un permiso
 * "buscar" extra solo agregaría una forma de tener el buscador prendido y
 * vacío, o —peor— de creer que gatea algo que en realidad gatean los
 * proveedores. Cualquiera que entró al panel puede escribir en el buscador;
 * lo que ve es exactamente lo que vería navegando el menú con ese rol.
 *
 * Una consulta sin términos utilizables (vacía, o todo palabras de una letra)
 * no consulta ninguna tabla: devuelve la pantalla en su estado inicial.
 */
final class BusquedaController
{
    public function index(Request $request, CascaraPanel $cascara, BuscarEnElPanel $buscar): View
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $terminos = TerminosBusqueda::desde(TextoDeFiltro::de($request, 'q'));
        $bloques = $buscar->ejecutar($usuario, $idRolActivo, $terminos);

        return view('seguridad::pages.busqueda.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            [
                'terminos' => $terminos,
                'bloques' => $bloques,
                'total' => BuscarEnElPanel::totalDe($bloques),
            ],
        ));
    }
}
