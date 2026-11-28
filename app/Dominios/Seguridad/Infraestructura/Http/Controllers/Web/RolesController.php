<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Aplicacion\AsignarPermisosRol;
use App\Dominios\Seguridad\Aplicacion\CatalogoDePermisos;
use App\Dominios\Seguridad\Aplicacion\EliminarRol;
use App\Dominios\Seguridad\Aplicacion\GuardarRol;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Aplicacion\ListarRolesConPermisos;
use App\Dominios\Seguridad\Aplicacion\ListarUsuariosDeRol;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\ColorDeModulo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\ResumenDePermisosDeRol;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\AsignarPermisosRolRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\GuardarRolRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/roles*`: los roles del panel. Listado, ficha
 * («Ver rol»), alta, edición y baja, y la matriz rol↔permiso («Permisos»). El
 * administrador (id 1) es el rol del sistema: ve y hace todo, no se elimina,
 * no se renombra, no se desactiva y su matriz no se edita; cualquier otro rol
 * del panel se crea, edita y elimina, y se le reparten permisos.
 *
 * Adaptador delgado (ADR 0008): las guardas de negocio viven en los casos de
 * uso ({@see GuardarRol}, {@see EliminarRol}, {@see AsignarPermisosRol}), no
 * acá. Este controlador resuelve permisos de PANTALLA contra el rol activo,
 * arma los datos de presentación y traduce las excepciones de dominio a
 * mensajes de vuelta. Que la vista oculte «Eliminar» al rol 1 es solo no
 * ofrecer lo que va a fallar: el `DELETE` a mano lo rechaza el caso de uso.
 *
 * Los permisos de grano fino (`seguridad.rol.ver`/`.crear`/`.editar`/`.eliminar`/
 * `.asignar_permiso`) son reservados al administrador (`PermisosReservados`):
 * quien edita la matriz puede concederse cualquier permiso del panel, así que
 * no es una responsabilidad delegable al operador.
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

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = TextoDeFiltro::de($request, 'estado');
        $estado = in_array($estado, ['activo', 'inactivo'], true) ? $estado : '';

        $roles = $listar->ejecutar($busqueda !== '' ? $busqueda : null, $estado !== '' ? $estado : null);
        $idActivo = $this->rolActivoId($request);

        return view('seguridad::pages.roles.index', [
            ...$this->autorizacion->cascara($request),
            'listadoRoles' => $roles->through(fn (array $fila): array => [
                ...$fila,
                'nombre' => PresentadorRol::nombreLegible($fila['rol']),
                'esRolActivo' => $fila['rol']->id === $idActivo,
                'esDelSistema' => $fila['rol']->esDelSistema(),
            ]),
            'totales' => $listar->totales(),
            'filtros' => ['q' => $busqueda, 'estado' => $estado !== '' ? $estado : null],
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
            'puedeAsignar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR),
        ]);
    }

    /** Ficha del rol: datos, cuentas que lo tienen, resumen de permisos por módulo y últimos movimientos. */
    public function show(
        Request $request,
        SecRole $rol,
        ListarUsuariosDeRol $listarUsuarios,
        ListarBitacora $listarBitacora,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);
        $this->abortarSiNoEsDelPanel($rol);

        $cascara = $this->autorizacion->cascara($request);
        $zona = (string) ($cascara['zonaHoraria'] ?? config('app.timezone'));

        $puedeVerBitacora = $this->autorizacion->tienePermiso($request, 'seguridad.bitacora.ver');
        $movimientos = $puedeVerBitacora
            ? $listarBitacora->ejecutar(zonaQueVe: $zona, tabla: 'sec_role', registroId: (int) $rol->id, porPagina: 10)->items()
            : [];

        $resumen = ResumenDePermisosDeRol::de(
            $this->catalogo->arbolDeConcesiones(),
            $this->catalogo->idsPermisoDeRol($rol->id),
        );

        return view('seguridad::pages.roles.show', [
            ...$cascara,
            'rol' => $rol,
            'nombre' => PresentadorRol::nombreLegible($rol),
            'esRolActivo' => $rol->id === $this->rolActivoId($request),
            'esDelSistema' => $rol->esDelSistema(),
            'usuariosDelRol' => $listarUsuarios->ejecutar($rol->id),
            'resumenPermisos' => $resumen,
            'permisosActivos' => array_sum(array_column($resumen, 'activos')),
            'permisosTotal' => array_sum(array_column($resumen, 'total')),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
            'puedeAsignar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR),
            'puedeVerUsuarios' => $this->autorizacion->tienePermiso($request, 'seguridad.usuario.ver'),
            'puedeVerBitacora' => $puedeVerBitacora,
            'lineaDeTiempo' => array_map(fn ($fila): array => [
                'title' => __('seguridad.bitacora.acciones.'.$fila->accion->value),
                'meta' => implode(' · ', [
                    $fila->instante->format('d/m/Y H:i'),
                    $fila->actorNombre ?? __('seguridad.usuarios.actor_sistema'),
                ]),
                'tone' => 'neutral',
            ], $movimientos),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('seguridad::pages.roles.create', [
            ...$this->autorizacion->cascara($request),
            'rol' => null,
            'esRolActivo' => false,
            'esDelSistema' => false,
            'resumenRelacionado' => [],
        ]);
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
        } catch (PermisoDenegado|RolProtegido $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Un rol nace sin permisos: se aterriza en su matriz (o en su ficha si el actor no puede repartir).
        if ($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR)) {
            return redirect()->route('panel.roles.permisos.edit', $rol)->with('estado', __('seguridad.roles.creado'));
        }

        return redirect()->route('panel.roles.show', $rol)->with('estado', __('seguridad.roles.creado_sin_permisos'));
    }

    public function edit(Request $request, SecRole $rol, ListarUsuariosDeRol $listarUsuarios): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        $this->abortarSiNoEsDelPanel($rol);

        return view('seguridad::pages.roles.edit', [
            ...$this->autorizacion->cascara($request),
            'rol' => $rol,
            'esRolActivo' => $rol->id === $this->rolActivoId($request),
            'esDelSistema' => $rol->esDelSistema(),
            'resumenRelacionado' => $this->resumenRelacionado($rol, $listarUsuarios),
        ]);
    }

    public function update(GuardarRolRequest $request, SecRole $rol, GuardarRol $guardar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        $this->abortarSiNoEsDelPanel($rol);

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
        } catch (PermisoDenegado|RolProtegido $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Aterriza en la ficha del rol, como Usuarios (el cambio se lee ahí, con su Editar a mano).
        return redirect()
            ->route('panel.roles.show', $rol)
            ->with('estado', __('seguridad.roles.actualizado'));
    }

    /**
     * Baja del rol. Las reglas (el administrador no se elimina, ni un rol con
     * cuentas, ni el propio, ni el último con la llave) las impone
     * {@see EliminarRol}: llegue la petición del botón o armada a mano por URL.
     */
    public function destroy(Request $request, SecRole $rol, EliminarRol $eliminar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);
        $this->abortarSiNoEsDelPanel($rol);

        try {
            $eliminar->ejecutar($request->user('interno'), $rol, $this->rolActivoId($request));
        } catch (PermisoDenegado $excepcion) {
            abort(403, $excepcion->getMessage());
        } catch (RolProtegido $excepcion) {
            return ($request->input('desde') === 'ficha'
                ? redirect()->route('panel.roles.show', $rol)
                : redirect()->route('panel.roles.index'))
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
     * - `concedibles`: los que el actor tiene en su rol activo, sin los reservados
     *   al administrador (que no se otorgan a otro rol) ni, en la matriz del
     *   administrador, ninguno (no se edita). El resto se pinta deshabilitado
     *   porque nadie concede lo que no tiene (guarda anti-escalada).
     */
    public function editarPermisos(Request $request, SecRole $rol): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR), 403);
        $this->abortarSiNoEsDelPanel($rol);

        $otorgados = $this->catalogo->idsPermisoDeRol($rol->id);
        $arbol = $this->catalogo->arbolDeConcesiones();

        $claves = array_column($arbol['modulos'], 'clave');

        if ($arbol['sueltos'] !== []) {
            $claves[] = ColorDeModulo::CLAVE_SUELTOS;
        }

        return view('seguridad::pages.roles.permisos', [
            ...$this->autorizacion->cascara($request),
            'rol' => $rol,
            'nombreRol' => PresentadorRol::nombreLegible($rol),
            'esRolActivo' => $rol->id === $this->rolActivoId($request),
            'esDelSistema' => $rol->esDelSistema(),
            'modulos' => $arbol['modulos'],
            'sueltos' => $arbol['sueltos'],
            'tonos' => ColorDeModulo::asignar($claves),
            'otorgados' => $otorgados,
            'bloqueados' => array_values(array_intersect(
                $otorgados,
                $this->catalogo->idsPermisoConPortadorUnico($rol->id),
            )),
            'concedibles' => $this->idsConcediblesPorElActor($request, $rol, $otorgados),
            'puedeVerRol' => $this->autorizacion->tienePermiso($request, self::PERMISO_VER),
        ]);
    }

    public function actualizarPermisos(
        AsignarPermisosRolRequest $request,
        SecRole $rol,
        AsignarPermisosRol $asignar,
    ): RedirectResponse {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR), 403);
        $this->abortarSiNoEsDelPanel($rol);

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
     * Tarjeta del aside del formulario de edición (§6.3.1 de la guía de
     * pantalla): cuentas que tienen el rol y permisos que otorga. Es lectura
     * del propio rol, ya gateada por `seguridad.rol.editar` arriba.
     *
     * @return list<array{titulo: string, icono: string, items: list<array<string, mixed>>}>
     */
    private function resumenRelacionado(SecRole $rol, ListarUsuariosDeRol $listarUsuarios): array
    {
        $resumen = ResumenDePermisosDeRol::de(
            $this->catalogo->arbolDeConcesiones(),
            $this->catalogo->idsPermisoDeRol($rol->id),
        );

        return [[
            'titulo' => __('seguridad.roles.aside_titulo'),
            'icono' => 'shield_person',
            'items' => [
                ['label' => __('seguridad.roles.aside_permisos'), 'value' => array_sum(array_column($resumen, 'activos')).' / '.array_sum(array_column($resumen, 'total')), 'mono' => true],
                ['label' => __('seguridad.roles.aside_usuarios'), 'value' => (string) count($listarUsuarios->ejecutar($rol->id)), 'mono' => true],
            ],
        ]];
    }

    /**
     * Permisos que el actor puede conceder o retirar: los que él mismo tiene
     * en su ROL ACTIVO, menos los reservados al administrador
     * (`PermisosReservados`) en un rol que no es `administrador` — salvo que ya
     * los tenga, para poder quitarlos. La matriz del administrador no se edita:
     * ninguno. Es el reflejo en la vista de las guardas del caso de uso —
     * presentación, no autorización: el servidor revalida.
     *
     * @param  list<int>  $otorgados  los que el rol que se edita ya tiene.
     * @return list<int>
     */
    private function idsConcediblesPorElActor(Request $request, SecRole $rol, array $otorgados): array
    {
        $delActor = $this->catalogo->idsPermisoDeRol($this->rolActivoId($request));

        if ($rol->esDelSistema()) {
            return [];
        }

        $reservadosSinOtorgar = array_diff(
            $this->catalogo->idsPermisoPorCodigo(PermisosReservados::SOLO_ADMINISTRADOR),
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

    /** Los roles de ámbito `app` (transitorios hasta A5) no se administran desde el panel. */
    private function abortarSiNoEsDelPanel(SecRole $rol): void
    {
        abort_unless($rol->esDelPanel(), 404);
    }
}
