<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Personal\Contratos\LecturaFichaPersona;
use App\Dominios\Personal\Contratos\LecturaNombresDePersonas;
use App\Dominios\Seguridad\Aplicacion\AlternarBloqueoUsuario;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\ConsultarRolesDeUsuarios;
use App\Dominios\Seguridad\Aplicacion\EliminarUsuario;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Aplicacion\ListarUsuarios;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\NombreLegibleRol;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\TarjetasDeUsuario;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarUsuarioRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\CrearUsuarioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/usuarios*` (HU-45, tarea 39): alta y
 * mantenimiento de cuentas del panel — internas (con roles) Y de portal
 * (con contrato, tarea 65, HU-41), un solo ABM. Mismo molde que
 * `PersonasController`/`OrdenesController` — el controlador queda delgado y
 * sin reglas de negocio: `store`/`update` bifurcan por `type` ANTES de
 * cualquier guarda de negocio y cada camino invoca su propio caso de uso —
 * {@see AsignarRolesUsuario} (HU-01) para `interno`, {@see CrearCuentaPortal}
 * (tarea 65) para `cliente` — sin que ninguno de los dos reimplemente la
 * guarda del otro (roles/`asignar_rol_dueno` es solo del camino interno;
 * contrato vigente/`seguridad.usuario.portal` es solo del camino portal).
 *
 * Siete permisos de grano fino
 * (`seguridad.usuario.ver`/`.crear`/`.editar`/`.eliminar`/`.bloquear`/
 * `.asignar_rol_dueno`/`.portal`), verificados DENTRO del controlador contra
 * el ROL ACTIVO vía {@see AutorizacionPanelWeb} — mismo criterio que el
 * resto del panel. `asignar_rol_dueno` no tiene una acción HTTP propia, pero
 * sí gatea `store`/`update` cuando el payload toca un rol que a su vez tiene
 * ese permiso otorgado (tarea 62: antes solo filtraba el `<select>`,
 * cosmético — un `PUT` armado a mano con ese id en `roles[]` lo aceptaba
 * igual si el actor lo tenía en CUALQUIER rol asignado, no en el activo).
 * `seguridad.usuario.portal` es análogo para el camino `cliente`: se exige
 * ADEMÁS de `crear`/`editar`, nunca en su lugar.
 *
 * `type` se acepta del formulario SOLO en alta (`CrearUsuarioRequest`); en
 * edición no se acepta ni se lee del payload — una cuenta no muta de interna
 * a cliente ni al revés, es otra cuenta (ver `ActualizarUsuarioRequest`).
 */
final class UsuariosController
{
    /**
     * Estado de acceso de la cuenta → tono. Se define una sola vez y lo
     * comparten el badge del listado, el botón que lleva a ese estado y el
     * modal de confirmación: los tres hablan con el mismo color.
     */
    public const TONO_POR_ESTADO = [
        'activo' => 'success',
        'bloqueado' => 'danger',
    ];

    private const PERMISO_VER = 'seguridad.usuario.ver';

    private const PERMISO_CREAR = 'seguridad.usuario.crear';

    private const PERMISO_EDITAR = 'seguridad.usuario.editar';

    private const PERMISO_ELIMINAR = 'seguridad.usuario.eliminar';

    private const PERMISO_BLOQUEAR = 'seguridad.usuario.bloquear';

    private const PERMISO_ROL_DUENO = 'seguridad.usuario.asignar_rol_dueno';

    private const PERMISO_VER_COMO = 'seguridad.usuario.ver_como';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ConsultarRolesDeUsuarios $roles,
        private readonly TarjetasDeUsuario $tarjetas,
        private readonly LecturaNombresDePersonas $personas,
    ) {}

    public function index(Request $request, ListarUsuarios $listarUsuarios): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $tipoCrudo = $request->query('tipo');
        $tipo = is_string($tipoCrudo) ? TipoUsuario::tryFrom($tipoCrudo) : null;
        $usuarios = $listarUsuarios->ejecutar($busqueda !== '' ? $busqueda : null, $tipo);

        $idsUsuario = $usuarios->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsPersona = $usuarios->pluck('persona_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        return view('seguridad::pages.usuarios.index', [
            ...$this->autorizacion->cascara($request),
            'usuarios' => $usuarios,
            'rolesPorUsuario' => $this->tarjetas->rolesPorUsuario($idsUsuario),
            'rolesVerComoPorUsuario' => $this->autorizacion->tienePermiso($request, self::PERMISO_VER_COMO)
                ? $this->rolesVivosPorUsuario($idsUsuario)
                : [],
            'etiquetasPersona' => $this->personas->nombresDe($idsPersona),
            'filtros' => ['q' => $busqueda, 'tipo' => $tipo->value ?? ''],
            'tonoPorEstado' => self::TONO_POR_ESTADO,
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
        $datos = $request->validated();

        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $rolesDeseados = array_map('intval', $datos['roles'] ?? []);

        $this->abortarSiFaltaPermisoRolDueno($request, [], $rolesDeseados);

        try {
            $usuario = $asignarRoles->ejecutar(
                actor: $request->user('interno'),
                usuarioId: null,
                username: (string) $datos['username'],
                email: $this->cadenaONull($datos['email'] ?? null),
                password: (string) $datos['password'],
                name: (string) $datos['name'],
                type: TipoUsuario::Interno,
                personaId: $this->enteroONull($datos['persona_id'] ?? null),
                roleIds: $rolesDeseados,
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (UsuarioDuplicado|PermisoDenegado $excepcion) {
            return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.usuarios.edit', $usuario)
            ->with('estado', __('seguridad.usuarios.creado'));
    }

    public function edit(
        Request $request,
        SecUser $usuario,
        LecturaFichaPersona $lecturaPersona,
        ListarBitacora $listarBitacora,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $cascara = $this->autorizacion->cascara($request);

        return view('seguridad::pages.usuarios.edit', [
            ...$cascara,
            'usuario' => $usuario,
            'rolesAsignados' => $usuario->idsDeRoles(),
            'rolesDisponibles' => $this->rolesDisponibles($request),
            'personasDisponibles' => $this->personasDisponibles($usuario->id),
            'resumenRelacionado' => $this->resumenRelacionado(
                $usuario,
                $request,
                (string) ($cascara['zonaHoraria'] ?? config('app.timezone')),
                $lecturaPersona,
                $listarBitacora,
            ),
        ]);
    }

    public function update(ActualizarUsuarioRequest $request, SecUser $usuario, AsignarRolesUsuario $asignarRoles): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        // Las cuentas de cliente se registran solas desde la app (ADR 0036): el
        // panel no las edita.
        abort_if($usuario->type === TipoUsuario::Cliente, 404);

        $rolesDeseados = array_map('intval', $datos['roles'] ?? []);

        $this->abortarSiFaltaPermisoRolDueno($request, $usuario->idsDeRoles(), $rolesDeseados);

        try {
            $asignarRoles->ejecutar(
                actor: $request->user('interno'),
                usuarioId: $usuario->id,
                username: (string) $datos['username'],
                email: $this->cadenaONull($datos['email'] ?? null),
                password: $this->cadenaONull($datos['password'] ?? null),
                name: (string) $datos['name'],
                type: $usuario->type,
                personaId: $this->enteroONull($datos['persona_id'] ?? null),
                roleIds: $rolesDeseados,
                idRolActivo: $this->rolActivoId($request),
            );
        } catch (UsuarioDuplicado|PermisoDenegado $excepcion) {
            return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.usuarios.edit', $usuario)
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
     * Resumen relacionado del aside (solo edición, §6.3.1 de la guía de
     * pantalla): hasta tres tarjetas con las relaciones más cercanas de la
     * cuenta, derivadas de sus FK reales — roles (`sec_user_role`) y persona
     * (`persona_id`); la bitácora de la propia cuenta cierra la lista.
     *
     * Cada tarjeta se gatea por el permiso `.ver` del módulo de LO QUE MUESTRA
     * contra el ROL ACTIVO (invariante 10 de CLAUDE.md), no por el de
     * usuarios, que ya se verificó arriba; sin él, la tarjeta se omite del
     * todo. Lo de otro módulo (persona, contrato) llega por su `Contratos/`
     * (ADR 0003, regla 2), nunca con SQL ni modelos ajenos. Los datos los
     * resuelve este método, nunca la vista.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        SecUser $usuario,
        Request $request,
        string $zonaQueVe,
        LecturaFichaPersona $lecturaPersona,
        ListarBitacora $listarBitacora,
    ): array {
        $resumen = [];
        $esInterno = $usuario->type === TipoUsuario::Interno;

        if ($esInterno && $this->autorizacion->tienePermiso($request, 'seguridad.rol.ver')) {
            $resumen[] = $this->tarjetas->tarjetaRoles($usuario);
        }

        if ($esInterno && $this->autorizacion->tienePermiso($request, 'personal.persona.ver')) {
            $resumen[] = $this->tarjetas->tarjetaPersona($usuario, $lecturaPersona);
        }

        if ($this->autorizacion->tienePermiso($request, 'seguridad.bitacora.ver')) {
            $resumen[] = $this->tarjetas->tarjetaBitacora($usuario, $zonaQueVe, $listarBitacora);
        }

        return $resumen;
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
     * Roles vivos y habilitados de cada cuenta con su id, para el selector de
     * «Ver como» (tarea 140), con nombre legible.
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, array<int, string>> `id de usuario => [id de rol => nombre legible]`
     */
    private function rolesVivosPorUsuario(array $idsUsuario): array
    {
        return array_map(
            fn (array $roles): array => array_map(NombreLegibleRol::de(...), $roles),
            $this->roles->slugsVivosPorUsuario($idsUsuario),
        );
    }

    /**
     * Personas vivas sin cuenta asignada todavía, más la propia persona del
     * usuario en edición (si no se excluyera del filtro de «ya asignadas», el
     * propio registro se autoexcluiría de su lista al re-guardar). `Personal`
     * es otro módulo: se lee por su contrato (ADR 0003, regla 2).
     *
     * @return Collection<int, string>
     */
    private function personasDisponibles(?int $usuarioId): Collection
    {
        $idsAsignados = SecUser::query()
            ->whereNotNull('persona_id')
            ->when($usuarioId !== null, fn ($consulta) => $consulta->whereKeyNot($usuarioId))
            ->pluck('persona_id');

        return collect($this->personas->vivasExcepto($idsAsignados));
    }

    /** @return list<int> */
    private function idsRolesQueExigenPermisoDueno(): array
    {
        return $this->roles->idsDeRolesConPermiso(self::PERMISO_ROL_DUENO);
    }
}
