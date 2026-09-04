<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\RecordarRolPreferido;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol;
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
     * Con rol preferido vivo ("Entrar siempre con este rol", quinta vuelta —
     * maqueta 5c) la pantalla también se saltea, SALVO que la visita sea un
     * cambio de rol explícito (`?cambiar=1`, el link "Cambiar de rol" del
     * menú) — ese es exactamente el único momento en que la maqueta dice que
     * debe reaparecer.
     *
     * Con 2+ roles vivos sin preferido (haya o no ya un rol activo válido en
     * sesión) se muestra el selector completo: volver a verla con un rol
     * activo válido no rompe nada, así que no hace falta una guarda extra
     * para ese caso.
     */
    public function create(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        ListarRolesDisponibles $listarRolesDisponibles,
        ElegirRolActivo $elegirRolActivo,
    ): View|RedirectResponse {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $roles = $listarRolesDisponibles->ejecutar($usuario);

        if ($roles->count() === 1) {
            $elegirRolActivo->ejecutar($usuario, $roles->first()->id);

            return redirect()->to($autorizacion->primerDestinoVisible($request));
        }

        $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();
        $idsVivos = $roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        $idPreferido = $preferencia?->rol_preferido_id;
        $preferidoVivo = $idPreferido !== null && in_array((int) $idPreferido, $idsVivos, true);

        $esCambioExplicito = $request->boolean('cambiar');

        if ($preferidoVivo && ! $esCambioExplicito) {
            $elegirRolActivo->ejecutar($usuario, (int) $idPreferido);

            return redirect()->to($autorizacion->primerDestinoVisible($request));
        }

        $idUltimo = $preferencia?->ultimo_rol_id;
        $ultimoVivo = $idUltimo !== null && in_array((int) $idUltimo, $idsVivos, true) ? (int) $idUltimo : null;

        // Preselección: preferido vivo > último usado vivo > rol activo de la
        // sesión > primero de la lista. Solo estado inicial de la UI — la
        // elección real la revalida ElegirRolActivo en el POST.
        $idRolActivo = $request->session()->get('sec_rol_activo_id');
        $preseleccion = $preferidoVivo ? (int) $idPreferido : ($ultimoVivo ?? (
            $idRolActivo !== null && in_array((int) $idRolActivo, $idsVivos, true)
                ? (int) $idRolActivo
                : ($idsVivos[0] ?? null)
        ));

        return view('seguridad::pages.seleccionar-rol', [
            'roles' => $roles->map(fn (SecRole $rol) => PresentadorRol::presentar($rol))->values(),
            'preseleccionId' => $preseleccion,
            'ultimoRolId' => $ultimoVivo,
            'recordarInicial' => $preferidoVivo,
            'usuarioNombre' => $usuario->name,
            'usuarioUsername' => $usuario->username,
            'tema' => ($preferencia->tema ?? TemaPreferencia::Claro)->atributoBootstrap(),
            'accionActualizar' => route('panel.rol-activo.actualizar'),
            // Nombre histórico de la variable de vista (`urlDashboard`, ver
            // `seleccionar-rol.blade.php`): queda como valor de respaldo del
            // atributo `data-url-dashboard` — `role-selection.js` ya no lo usa
            // en el camino feliz (usa `destino` de la respuesta JSON del POST,
            // que sí conoce el rol recién elegido; acá con 2+ roles sin
            // elegir todavía no hay uno que resolver, tarea 62 fuga 2).
            'urlDashboard' => route('panel.dashboard'),
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
    public function update(
        ActualizarRolActivoRequest $request,
        AutorizacionPanelWeb $autorizacion,
        ElegirRolActivo $elegirRolActivo,
        RecordarRolPreferido $recordarRolPreferido,
    ): JsonResponse {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $rol = $elegirRolActivo->ejecutar($usuario, (int) $request->validated('id_role'));

        // Checkbox "Entrar siempre con este rol" (quinta vuelta, maqueta 5c):
        // solo se toca la preferencia si el request la trae explícita —
        // `true` la fija al rol recién activado, `false` la limpia. Un POST
        // sin el campo (p. ej. un cliente de API viejo) no la altera.
        if ($request->has('recordar')) {
            $recordarRolPreferido->ejecutar(
                $usuario,
                $request->boolean('recordar') ? $rol->id : null,
            );
        }

        return response()->json([
            'rol_activo_id' => $rol->id,
            'rol_activo_nombre' => $rol->name,
            // Primer ítem visible del menú de ESTE rol recién activado
            // (tarea 62, fuga 2) — `ElegirRolActivo::ejecutar()` ya fijó
            // `session('sec_rol_activo_id')` arriba, así que se resuelve
            // sobre el rol correcto, no el que estaba activo al entrar a
            // este request. `role-selection.js` navega acá, nunca a un
            // `panel.dashboard` fijo.
            'destino' => $autorizacion->primerDestinoVisible($request),
        ]);
    }
}
