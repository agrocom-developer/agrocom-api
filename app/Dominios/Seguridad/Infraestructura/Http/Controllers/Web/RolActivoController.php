<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarRolActivoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Selección y cambio de rol activo (ADR 0004, extensión 27/8/2026, puntos 2
 * y 4). Adaptador delgado en ambas acciones: valida forma/resuelve datos,
 * invoca el caso de uso, responde — la regla de negocio vive en
 * {@see ElegirRolActivo}/{@see ListarRolesDisponibles}, nunca acá.
 */
final class RolActivoController
{
    /**
     * `GET /panel/seleccionar-rol` (`panel.rol-activo.selector`). Vía de
     * escape para fijar el rol activo la primera vez — por eso la ruta lleva
     * solo `auth:interno`, nunca `rol.activo` (ese middleware redirige acá
     * cuando no hay rol activo resoluble; si esta ruta también lo exigiera,
     * sería un loop de redirección).
     *
     * Con exactamente un rol vivo no hay nada que elegir: mismo criterio que
     * ya aplican {@see IniciarSesionPanel} y el middleware
     * {@see ResolverRolActivo} (auto-fijarlo) — mostrar un selector de una
     * sola opción sería una pantalla intermedia inútil, así que se fija solo
     * y se redirige directo al dashboard.
     *
     * Con cero roles vivos no hay redirección posible: el dashboard también
     * exige rol activo, así que reenviar para allá sería un segundo rebote
     * inmediato de vuelta acá. Se renderiza igual esta vista, con la lista
     * vacía — la propia vista resuelve el estado "sin roles asignados" (nada
     * que romper: no hay guarda especial que agregar en el controlador para
     * ese caso, tal como permite la consigna).
     *
     * Con 2+ roles vivos (haya o no ya un rol activo válido en sesión) se
     * muestra el selector completo: volver a verla con un rol activo válido
     * no rompe nada, así que no hace falta una guarda extra para ese caso.
     */
    public function create(
        Request $request,
        ListarRolesDisponibles $listarRolesDisponibles,
        ElegirRolActivo $elegirRolActivo,
    ): View|RedirectResponse {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $roles = $listarRolesDisponibles->ejecutar($usuario);

        if ($roles->count() === 1) {
            $elegirRolActivo->ejecutar($usuario, $roles->first()->id);

            return redirect()->route('panel.dashboard');
        }

        return view('seguridad::pages.seleccionar-rol', [
            'roles' => $roles,
            'accionActualizar' => route('panel.rol-activo.actualizar'),
        ]);
    }

    /**
     * `POST /panel/rol-activo` (`panel.rol-activo.actualizar`): cambio de rol
     * activo sin volver a loguearse. Si el rol no está asignado o ya no está
     * vivo, `ElegirRolActivo` lanza `RolNoAsignado` (una
     * `AuthorizationException`) que el manejador de excepciones del
     * framework traduce a 403 sin mapeo adicional acá — mismo patrón que
     * `PermisoDenegado` en `AsignarRolesUsuario`.
     *
     * No cruza ningún límite de autenticación: sigue siendo el mismo
     * `sec_user.id` ya autenticado por la ruta (`auth:interno`), por eso no
     * regenera sesión ni token CSRF.
     */
    public function update(ActualizarRolActivoRequest $request, ElegirRolActivo $elegirRolActivo): JsonResponse
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $rol = $elegirRolActivo->ejecutar($usuario, (int) $request->validated('id_role'));

        return response()->json([
            'rol_activo_id' => $rol->id,
            'rol_activo_nombre' => $rol->name,
        ]);
    }
}
