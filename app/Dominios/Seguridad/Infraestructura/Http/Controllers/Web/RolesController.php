<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\AsignarPermisosRol;
use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;
use App\Dominios\Seguridad\Aplicacion\EliminarRol;
use App\Dominios\Seguridad\Aplicacion\GuardarRol;
use App\Dominios\Seguridad\Aplicacion\ListarRolesConPermisos;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolDuplicado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\AsignarPermisosRolRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\GuardarRolRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/roles*`: administración del catálogo de roles y
 * de la matriz rol↔permiso. Hasta ahora esto solo existía como seeder — la
 * única parte del modelo de seguridad que no tenía pantalla.
 *
 * Adaptador delgado (ADR 0008): las cinco guardas de negocio viven en los
 * casos de uso ({@see GuardarRol}, {@see AsignarPermisosRol},
 * {@see EliminarRol}), no acá. Este controlador resuelve permisos de
 * PANTALLA contra el rol activo, arma los datos de presentación y traduce las
 * excepciones de dominio a mensajes de vuelta.
 *
 * Los cinco permisos de grano fino (`seguridad.rol.ver`/`.crear`/`.editar`/
 * `.eliminar`/`.asignar_permiso`) se siembran SOLO para `dueno`: quien puede
 * editar la matriz puede concederse cualquier permiso del sistema, así que no
 * es una responsabilidad delegable al encargado de operaciones (que sí tiene,
 * por ejemplo, el alta de usuarios). Aun así ninguna guarda mira el nombre
 * del rol — todas miran permisos, para que otorgárselos mañana a un rol nuevo
 * funcione sin tocar código (mismo criterio que la guarda de
 * `asignar_rol_dueno` en {@see UsuariosController}).
 *
 * Por qué `PermisoDenegado` es 403 y `RolProtegido` vuelve como mensaje: al
 * actor le falta un permiso vs. el actor tiene todos los permisos y lo que
 * pidió es lo que no puede existir. Lo segundo no es un error de autorización
 * y responder 403 lo haría ver como uno.
 */
final class RolesController
{
    private const PERMISO_VER = 'seguridad.rol.ver';

    private const PERMISO_CREAR = 'seguridad.rol.crear';

    private const PERMISO_EDITAR = 'seguridad.rol.editar';

    private const PERMISO_ELIMINAR = 'seguridad.rol.eliminar';

    private const PERMISO_ASIGNAR = 'seguridad.rol.asignar_permiso';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly CatalogoDePermisos $catalogo,
    ) {}

    public function index(Request $request, ListarRolesConPermisos $listar): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        return view('seguridad::pages.roles.index', [
            ...$this->autorizacion->cascara($request),
            'filas' => $listar->ejecutar()->map(fn (array $fila): array => [
                ...$fila,
                'nombre' => PresentadorRol::nombreLegible($fila['rol']),
                'esRolActivo' => $fila['rol']->id === $this->rolActivoId($request),
            ]),
            'totales' => $listar->totales(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('seguridad::pages.roles.create', $this->autorizacion->cascara($request));
    }

    public function store(GuardarRolRequest $request, GuardarRol $guardar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $rol = $guardar->ejecutar(
                actor: $request->user('interno'),
                rol: null,
                nombre: (string) $datos['name'],
                descripcion: (string) $datos['description'],
                activo: (bool) ($datos['state'] ?? false),
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (RolDuplicado|PermisoDenegado|RolProtegido $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // A permisos, no al listado: un rol sin permisos no le sirve a nadie,
        // y este es el único momento en que se sabe con certeza que todavía
        // no se los dieron.
        return redirect()
            ->route('panel.roles.permisos.edit', $rol)
            ->with('estado', __('seguridad.roles.creado'));
    }

    public function edit(Request $request, SecRole $rol): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('seguridad::pages.roles.edit', [
            ...$this->autorizacion->cascara($request),
            'rol' => $rol,
            'esRolActivo' => $rol->id === $this->rolActivoId($request),
        ]);
    }

    public function update(GuardarRolRequest $request, SecRole $rol, GuardarRol $guardar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $guardar->ejecutar(
                actor: $request->user('interno'),
                rol: $rol,
                nombre: (string) $datos['name'],
                descripcion: (string) $datos['description'],
                activo: (bool) ($datos['state'] ?? false),
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (RolDuplicado|PermisoDenegado|RolProtegido $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::update()).
        return redirect()
            ->route('panel.roles.edit', $rol)
            ->with('estado', __('seguridad.roles.actualizado'));
    }

    public function destroy(Request $request, SecRole $rol, EliminarRol $eliminar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminar->ejecutar($request->user('interno'), $rol, $this->rolActivoId($request));
        } catch (PermisoDenegado|RolProtegido $excepcion) {
            return redirect()
                ->route('panel.roles.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.roles.index')
            ->with('estado', __('seguridad.roles.eliminado'));
    }

    /**
     * La matriz. Además del árbol y de lo que el rol ya tiene, la vista
     * recibe dos conjuntos que deciden qué se puede tocar:
     *
     * - `bloqueados`: permisos que solo este rol sostiene. Se pintan
     *   deshabilitados con su explicación, y viajan igual en el submit por un
     *   `<input type="hidden">` — un checkbox deshabilitado no se envía, así
     *   que sin ese hidden "bloqueado" se leería como "revocado" y el caso de
     *   uso rechazaría el guardado entero.
     * - `concedibles`: los que el actor tiene en su rol activo. El resto se
     *   pinta deshabilitado porque nadie concede lo que no tiene (guarda
     *   anti-escalada). Con `dueno` —el único rol que hoy llega acá— son los
     *   91, así que en la práctica no bloquea nada; existe para el día en que
     *   estos permisos se le den a un rol más acotado.
     */
    public function editarPermisos(Request $request, SecRole $rol): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR), 403);

        $otorgados = $this->catalogo->idsPermisoDeRol($rol->id);
        $arbol = $this->catalogo->arbolDeConcesiones();

        return view('seguridad::pages.roles.permisos', [
            ...$this->autorizacion->cascara($request),
            'rol' => $rol,
            'nombreRol' => PresentadorRol::nombreLegible($rol),
            'esRolActivo' => $rol->id === $this->rolActivoId($request),
            'modulos' => $arbol['modulos'],
            'sueltos' => $arbol['sueltos'],
            'otorgados' => $otorgados,
            'bloqueados' => array_values(array_intersect(
                $otorgados,
                $this->catalogo->idsPermisoConPortadorUnico($rol->id),
            )),
            'concedibles' => $this->idsConcediblesPorElActor($request, $rol, $otorgados),
        ]);
    }

    public function actualizarPermisos(
        AsignarPermisosRolRequest $request,
        SecRole $rol,
        AsignarPermisosRol $asignar,
    ): RedirectResponse {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR), 403);

        $deseados = array_map('intval', $request->validated()['permisos'] ?? []);

        try {
            $asignar->ejecutar(
                actor: $request->user('interno'),
                rol: $rol,
                idsPermisoDeseados: $deseados,
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (PermisoDenegado|RolProtegido $excepcion) {
            return redirect()
                ->route('panel.roles.permisos.edit', $rol)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.roles.permisos.edit', $rol)
            ->with('estado', __('seguridad.roles.permisos_guardados'));
    }

    /**
     * Permisos que el actor puede conceder o retirar: los que él mismo tiene
     * en su ROL ACTIVO, menos los de plataforma (`PermisosReservados`) en un
     * rol que no es `admin_plataforma` — salvo que ya los tenga, para poder
     * quitarlos. Es el reflejo en la vista de las guardas del caso de uso —
     * presentación, no autorización: el servidor revalida.
     *
     * @param  list<int>  $otorgados  los que el rol que se edita ya tiene.
     * @return list<int>
     */
    private function idsConcediblesPorElActor(Request $request, SecRole $rol, array $otorgados): array
    {
        $delActor = $this->catalogo->idsPermisoDeRol($this->rolActivoId($request));

        if (PermisosReservados::admiteElRol((string) $rol->name)) {
            return $delActor;
        }

        $reservadosSinOtorgar = array_diff(
            $this->catalogo->idsPermisoPorCodigo(PermisosReservados::SOLO_ADMIN_PLATAFORMA),
            $otorgados,
        );

        return array_values(array_diff($delActor, $reservadosSinOtorgar));
    }

    /**
     * Rol activo de la sesión (`session('sec_rol_activo_id')`), a pasar
     * explícito a los casos de uso — el mismo dato que `AutorizacionPanelWebSesion`
     * usa para evaluar permisos contra el ROL ACTIVO y nunca la unión de roles
     * del actor (invariante 10 de CLAUDE.md).
     */
    private function rolActivoId(Request $request): int
    {
        return (int) $request->session()->get('sec_rol_activo_id');
    }
}
