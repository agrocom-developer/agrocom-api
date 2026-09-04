<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\AlternarBloqueoUsuario;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\EliminarUsuario;
use App\Dominios\Seguridad\Aplicacion\ListarUsuarios;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarUsuarioRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\CrearUsuarioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/usuarios*` (HU-45, tarea 39): alta y
 * mantenimiento de cuentas internas del panel, con sus roles. Mismo molde
 * que `PersonasController`/`OrdenesController` — el controlador queda
 * delgado y sin reglas de negocio; `store`/`update` invocan
 * {@see AsignarRolesUsuario} (HU-01), que ya cubre alta y edición con la
 * misma guarda de permisos (crear/editar y, cuando corresponde, el rol
 * `dueno`). Nunca reimplementa esa lógica.
 *
 * Seis permisos de grano fino
 * (`seguridad.usuario.ver`/`.crear`/`.editar`/`.eliminar`/`.bloquear`/
 * `.asignar_rol_dueno`), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb} — mismo criterio que el resto del
 * panel. `asignar_rol_dueno` no tiene una acción HTTP propia, pero sí gatea
 * `store`/`update` cuando el payload toca un rol que a su vez tiene ese
 * permiso otorgado (tarea 62: antes solo filtraba el `<select>`, cosmético —
 * un `PUT` armado a mano con ese id en `roles[]` lo aceptaba igual si el
 * actor lo tenía en CUALQUIER rol asignado, no en el activo).
 *
 * `type` nunca se expone al formulario: se fija a `TipoUsuario::Interno` acá
 * mismo. `Cliente` es del portal (HU-41), otro flujo de alta.
 */
final class UsuariosController
{
    private const PERMISO_VER = 'seguridad.usuario.ver';

    private const PERMISO_CREAR = 'seguridad.usuario.crear';

    private const PERMISO_EDITAR = 'seguridad.usuario.editar';

    private const PERMISO_ELIMINAR = 'seguridad.usuario.eliminar';

    private const PERMISO_BLOQUEAR = 'seguridad.usuario.bloquear';

    private const PERMISO_ROL_DUENO = 'seguridad.usuario.asignar_rol_dueno';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarUsuarios $listarUsuarios): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $usuarios = $listarUsuarios->ejecutar($busqueda !== '' ? $busqueda : null);

        $idsUsuario = $usuarios->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsPersona = $usuarios->pluck('persona_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        return view('seguridad::pages.usuarios.index', [
            ...$this->autorizacion->cascara($request),
            'usuarios' => $usuarios,
            'rolesPorUsuario' => $this->rolesPorUsuario($idsUsuario),
            'etiquetasPersona' => $this->etiquetasPersona($idsPersona),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('seguridad::pages.usuarios.create', [
            ...$this->autorizacion->cascara($request),
            'rolesDisponibles' => $this->rolesDisponibles($request),
            'personasDisponibles' => $this->personasDisponibles(null),
        ]);
    }

    public function store(CrearUsuarioRequest $request, AsignarRolesUsuario $asignarRoles): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();
        $rolesDeseados = array_map('intval', $datos['roles'] ?? []);

        $this->abortarSiFaltaPermisoRolDueno($request, [], $rolesDeseados);

        try {
            $asignarRoles->ejecutar(
                actor: $request->user('interno'),
                usuarioId: null,
                username: (string) $datos['username'],
                password: (string) $datos['password'],
                name: (string) $datos['name'],
                type: TipoUsuario::Interno,
                personaId: $this->enteroONull($datos['persona_id'] ?? null),
                contratoId: null,
                roleIds: $rolesDeseados,
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (UsuarioDuplicado|PermisoDenegado $excepcion) {
            return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.usuarios.index')
            ->with('estado', __('seguridad.usuarios.creado'));
    }

    public function edit(Request $request, SecUser $usuario): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('seguridad::pages.usuarios.edit', [
            ...$this->autorizacion->cascara($request),
            'usuario' => $usuario,
            'rolesAsignados' => $usuario->idsDeRoles(),
            'rolesDisponibles' => $this->rolesDisponibles($request),
            'personasDisponibles' => $this->personasDisponibles($usuario->id),
        ]);
    }

    public function update(ActualizarUsuarioRequest $request, SecUser $usuario, AsignarRolesUsuario $asignarRoles): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();
        $rolesDeseados = array_map('intval', $datos['roles'] ?? []);

        $this->abortarSiFaltaPermisoRolDueno($request, $usuario->idsDeRoles(), $rolesDeseados);

        try {
            $asignarRoles->ejecutar(
                actor: $request->user('interno'),
                usuarioId: $usuario->id,
                username: (string) $datos['username'],
                password: $this->cadenaONull($datos['password'] ?? null),
                name: (string) $datos['name'],
                type: $usuario->type,
                personaId: $this->enteroONull($datos['persona_id'] ?? null),
                contratoId: $usuario->contrato_id,
                roleIds: $rolesDeseados,
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (UsuarioDuplicado|PermisoDenegado $excepcion) {
            return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.usuarios.index')
            ->with('estado', __('seguridad.usuarios.actualizado'));
    }

    public function destroy(Request $request, SecUser $usuario, EliminarUsuario $eliminarUsuario): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarUsuario->ejecutar($request->user('interno'), $usuario);
        } catch (PermisoDenegado $excepcion) {
            return redirect()
                ->route('panel.usuarios.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.usuarios.index')
            ->with('estado', __('seguridad.usuarios.eliminado'));
    }

    public function alternarBloqueo(Request $request, SecUser $usuario, AlternarBloqueoUsuario $alternarBloqueo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_BLOQUEAR), 403);

        try {
            $alternarBloqueo->ejecutar($request->user('interno'), $usuario);
        } catch (PermisoDenegado $excepcion) {
            return redirect()
                ->route('panel.usuarios.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.usuarios.index')
            ->with('estado', __('seguridad.usuarios.bloqueo_actualizado'));
    }

    /**
     * Rol activo de la sesión del actor (`session('sec_rol_activo_id')`), a
     * pasar explícito a {@see AsignarRolesUsuario} — mismo dato que ya lee
     * `AutorizacionPanelWebSesion` para evaluar permisos del panel contra el
     * ROL ACTIVO, nunca la unión de roles del actor (invariante 10 de
     * CLAUDE.md).
     */
    private function rolActivoId(Request $request): int
    {
        return (int) $request->session()->get('sec_rol_activo_id');
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /**
     * Catálogo completo de roles, salvo los que a su vez tienen otorgado
     * `asignar_rol_dueno` en el catálogo cuando el actor autenticado no tiene
     * ese permiso en su rol activo: es solo para no ofrecer en el `<select>`
     * una opción que el submit va a rechazar — la guarda real de la acción
     * HTTP es {@see self::abortarSiFaltaPermisoRolDueno()}. Por permiso del
     * rol, no por el nombre `dueno`: el rol que hoy lo tiene podría
     * renombrarse, o el permiso otorgarse a otro rol nuevo, sin que este
     * filtro deje de aplicar.
     *
     * @return Collection<int, SecRole>
     */
    private function rolesDisponibles(Request $request): Collection
    {
        $roles = SecRole::query()->orderBy('name')->get();

        if (! $this->autorizacion->tienePermiso($request, self::PERMISO_ROL_DUENO)) {
            $idsRolesRestringidos = $this->idsRolesQueExigenPermisoDueno();

            $roles = $roles->reject(fn (SecRole $rol) => in_array($rol->id, $idsRolesRestringidos, true))->values();
        }

        return $roles;
    }

    /**
     * Guarda real de `asignar_rol_dueno` a nivel de acción HTTP (antes de
     * esta tarea, el permiso solo filtraba el `<select>` en
     * {@see self::rolesDisponibles()} — cosmético, no bloqueaba nada: un
     * `PUT` armado a mano con el id de un rol restringido en `roles[]`
     * pasaba igual). Se evalúa ANTES de invocar `AsignarRolesUsuario`, con el
     * mismo criterio 403 que el resto de los permisos de este controlador,
     * para que la respuesta HTTP sea consistente entre los seis permisos de
     * grano fino — la guarda interna de `AsignarRolesUsuario` (evaluada
     * contra el mismo rol activo) queda como defensa en profundidad para
     * quien invoque el caso de uso sin pasar por acá.
     *
     * Por permiso del rol afectado, no por el nombre `dueno`: solo aborta si
     * el conjunto de roles que cambia de estado (se asigna o se quita)
     * incluye alguno que tenga otorgado `asignar_rol_dueno`.
     *
     * @param  list<int>  $rolesActuales
     * @param  list<int>  $rolesDeseados
     */
    private function abortarSiFaltaPermisoRolDueno(Request $request, array $rolesActuales, array $rolesDeseados): void
    {
        $rolesAfectados = array_merge(
            array_diff($rolesDeseados, $rolesActuales),
            array_diff($rolesActuales, $rolesDeseados),
        );

        if (array_intersect($rolesAfectados, $this->idsRolesQueExigenPermisoDueno()) === []) {
            return;
        }

        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ROL_DUENO), 403);
    }

    /**
     * @return list<int> IDs de rol con `asignar_rol_dueno` otorgado y vivo en
     *                   el catálogo (`sec_role_permission`/`sec_permission`).
     */
    private function idsRolesQueExigenPermisoDueno(): array
    {
        return DB::table('sec_role_permission')
            ->join('sec_permission', 'sec_permission.id', '=', 'sec_role_permission.id_permission')
            ->whereNull('sec_role_permission.deleted_at')
            ->whereNull('sec_permission.deleted_at')
            ->where('sec_permission.code', self::PERMISO_ROL_DUENO)
            ->where('sec_permission.state', true)
            ->pluck('sec_role_permission.id_role')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Personas vivas de `per_personas` sin cuenta asignada todavía, más la
     * propia persona del usuario en edición (si no se excluyera del filtro
     * de "ya asignadas", el propio registro se autoexcluiría de su lista al
     * re-guardar). Lectura directa por `DB::table` — `Personal` es otro
     * módulo, ADR 0003 regla 3, mismo criterio que los selects de
     * `OrdenesController`.
     *
     * @return Collection<int, string>
     */
    private function personasDisponibles(?int $usuarioId): Collection
    {
        $idsAsignados = SecUser::query()
            ->whereNotNull('persona_id')
            ->when($usuarioId !== null, fn ($consulta) => $consulta->whereKeyNot($usuarioId))
            ->pluck('persona_id');

        return DB::table('per_personas')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $idsAsignados)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
    }

    /**
     * Etiquetas legibles de persona para la columna del listado, mismo
     * criterio de lectura directa que `personasDisponibles()`.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasPersona(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_personas')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * Nombres legibles de los roles vivos de cada usuario, para la columna
     * "Roles" del listado. Consulta directa (`sec_user_role`/`sec_role`) en
     * vez de `idsDeRoles()` por usuario: evita N+1 en la vista.
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, list<string>>
     */
    private function rolesPorUsuario(array $idsUsuario): array
    {
        if ($idsUsuario === []) {
            return [];
        }

        return DB::table('sec_user_role as ur')
            ->join('sec_role as r', 'r.id', '=', 'ur.id_role')
            ->whereIn('ur.id_user', $idsUsuario)
            ->whereNull('ur.deleted_at')
            ->orderBy('r.name')
            ->get(['ur.id_user', 'r.name'])
            ->groupBy(fn (object $fila): int => (int) $fila->id_user)
            ->map(fn (Collection $filas) => $filas->map(fn (object $fila) => $this->nombreLegibleRol((string) $fila->name))->all())
            ->all();
    }

    private function nombreLegibleRol(string $slug): string
    {
        $clave = "seguridad.rol.meta.{$slug}.nombre";

        return Lang::has($clave) ? __($clave) : $slug;
    }
}
