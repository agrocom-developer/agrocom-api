<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Personal\Contratos\LecturaFichaPersona;
use App\Dominios\Seguridad\Aplicacion\AlternarBloqueoUsuario;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Aplicacion\CrearCuentaPortal;
use App\Dominios\Seguridad\Aplicacion\EliminarUsuario;
use App\Dominios\Seguridad\Aplicacion\ListarBitacora;
use App\Dominios\Seguridad\Aplicacion\ListarDispositivosDeUsuario;
use App\Dominios\Seguridad\Aplicacion\ListarUsuarios;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Dominio\Excepciones\ContratoNoDisponibleParaPortal;
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

    private const PERMISO_PORTAL = 'seguridad.usuario.portal';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

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
            'rolesPorUsuario' => $this->rolesPorUsuario($idsUsuario),
            'etiquetasPersona' => $this->etiquetasPersona($idsPersona),
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
            'puedeCrearPortal' => $this->autorizacion->tienePermiso($request, self::PERMISO_PORTAL),
            'clientesDisponibles' => $this->clientesDisponibles(),
            'contratosVigentesDisponibles' => $this->contratosVigentesDisponibles(),
            'emailPorCliente' => $this->emailPorCliente(),
        ]);
    }

    public function store(CrearUsuarioRequest $request, AsignarRolesUsuario $asignarRoles, CrearCuentaPortal $crearCuentaPortal): RedirectResponse
    {
        $datos = $request->validated();

        if ((string) $datos['type'] === TipoUsuario::Cliente->value) {
            abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);
            abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_PORTAL), 403);

            try {
                $usuario = $crearCuentaPortal->ejecutar(
                    actor: $request->user('interno'),
                    usuarioId: null,
                    username: (string) $datos['username'],
                    password: (string) $datos['password'],
                    name: (string) $datos['name'],
                    contratoId: (int) $datos['contrato_id'],
                    idRolActivo: $this->rolActivoId($request),
                    email: $this->cadenaONull($datos['email'] ?? null),
                );
            } catch (UsuarioDuplicado|PermisoDenegado|ContratoNoDisponibleParaPortal $excepcion) {
                return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
            }

            // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
            return redirect()
                ->route('panel.usuarios.edit', $usuario)
                ->with('estado', __('seguridad.usuarios.creado'));
        }

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
                contratoId: null,
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
        LecturaContrato $lecturaContrato,
        ListarDispositivosDeUsuario $listarDispositivos,
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
            'clientesDisponibles' => $this->clientesDisponibles(),
            'contratosVigentesDisponibles' => $this->contratosVigentesDisponibles(),
            'emailPorCliente' => $this->emailPorCliente(),
            'resumenRelacionado' => $this->resumenRelacionado(
                $usuario,
                $request,
                (string) ($cascara['zonaHoraria'] ?? config('app.timezone')),
                $lecturaPersona,
                $lecturaContrato,
                $listarDispositivos,
                $listarBitacora,
            ),
        ]);
    }

    public function update(ActualizarUsuarioRequest $request, SecUser $usuario, AsignarRolesUsuario $asignarRoles, CrearCuentaPortal $crearCuentaPortal): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        if ($usuario->type === TipoUsuario::Cliente) {
            abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_PORTAL), 403);

            try {
                $crearCuentaPortal->ejecutar(
                    actor: $request->user('interno'),
                    usuarioId: $usuario->id,
                    username: (string) $datos['username'],
                    password: $this->cadenaONull($datos['password'] ?? null),
                    name: (string) $datos['name'],
                    contratoId: (int) $datos['contrato_id'],
                    idRolActivo: $this->rolActivoId($request),
                    email: $this->cadenaONull($datos['email'] ?? null),
                );
            } catch (UsuarioDuplicado|PermisoDenegado|ContratoNoDisponibleParaPortal $excepcion) {
                return redirect()->back()->withErrors(['estado' => $excepcion->getMessage()]);
            }

            // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
            return redirect()
                ->route('panel.usuarios.edit', $usuario)
                ->with('estado', __('seguridad.usuarios.actualizado'));
        }

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
                contratoId: $usuario->contrato_id,
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
     * pantalla): hasta cuatro tarjetas con las relaciones más cercanas de la
     * cuenta, derivadas de sus FK reales — roles (`sec_user_role`), persona
     * (`persona_id`), dispositivos (`sec_token_dispositivo.user_id`) y, en
     * una cuenta de portal, contrato (`contrato_id`); la bitácora de la propia
     * cuenta cierra la lista. Una cuenta de portal no tiene roles, persona ni
     * dispositivos (no autentica en la API de campo), así que solo le quedan
     * contrato y bitácora.
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
        LecturaContrato $lecturaContrato,
        ListarDispositivosDeUsuario $listarDispositivos,
        ListarBitacora $listarBitacora,
    ): array {
        $resumen = [];
        $esInterno = $usuario->type === TipoUsuario::Interno;

        if ($esInterno && $this->autorizacion->tienePermiso($request, 'seguridad.rol.ver')) {
            $resumen[] = $this->tarjetaRoles($usuario);
        }

        if ($esInterno && $this->autorizacion->tienePermiso($request, 'personal.persona.ver')) {
            $resumen[] = $this->tarjetaPersona($usuario, $lecturaPersona);
        }

        if ($esInterno && $this->autorizacion->tienePermiso($request, 'seguridad.dispositivo.ver')) {
            $resumen[] = $this->tarjetaDispositivos($usuario, $listarDispositivos);
        }

        if (! $esInterno && $this->autorizacion->tienePermiso($request, 'comercial.contrato.ver')) {
            $resumen[] = $this->tarjetaContrato($usuario, $lecturaContrato);
        }

        if ($this->autorizacion->tienePermiso($request, 'seguridad.bitacora.ver')) {
            $resumen[] = $this->tarjetaBitacora($usuario, $zonaQueVe, $listarBitacora);
        }

        return $resumen;
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    private function tarjetaRoles(SecUser $usuario): array
    {
        $roles = $this->rolesPorUsuario([(int) $usuario->id])[(int) $usuario->id] ?? [];

        return [
            'titulo' => __('seguridad.usuarios.aside_roles_titulo'),
            'icono' => 'shield_person',
            'tieneDatos' => $roles !== [],
            'items' => [
                ['label' => __('seguridad.usuarios.aside_roles_total'), 'value' => (string) count($roles), 'mono' => true],
                ['label' => __('seguridad.usuarios.aside_roles_nombres'), 'value' => implode(', ', $roles)],
            ],
            'vacioTitulo' => __('seguridad.usuarios.aside_roles_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_roles_vacio_detalle'),
            'acciones' => $roles !== []
                ? [['label' => __('seguridad.usuarios.aside_roles_accion'), 'href' => route('panel.roles.index'), 'icono' => 'list']]
                : [],
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    private function tarjetaPersona(SecUser $usuario, LecturaFichaPersona $lecturaPersona): array
    {
        $ficha = $usuario->persona_id !== null ? $lecturaPersona->dePersona($usuario->persona_id) : null;

        return [
            'titulo' => __('seguridad.usuarios.aside_persona_titulo'),
            'icono' => 'badge',
            'tieneDatos' => $ficha !== null,
            'items' => $ficha === null ? [] : [
                ['label' => __('seguridad.usuarios.aside_persona_nombre'), 'value' => $ficha->nombre],
                ['label' => __('seguridad.usuarios.aside_persona_rol'), 'value' => __('personal.roles.'.$ficha->rol)],
                ['label' => __('seguridad.usuarios.aside_persona_base'), 'value' => $ficha->baseNombre ?? __('seguridad.usuarios.aside_persona_sin_base')],
            ],
            // Sin persona_id, o con una persona dada de baja, la tarjeta dice cuál de las dos es.
            'vacioTitulo' => $usuario->persona_id === null
                ? __('seguridad.usuarios.aside_persona_vacio_titulo')
                : __('seguridad.usuarios.aside_persona_baja_titulo'),
            'vacioDetalle' => $usuario->persona_id === null
                ? __('seguridad.usuarios.aside_persona_vacio_detalle')
                : __('seguridad.usuarios.aside_persona_baja_detalle'),
            'acciones' => $ficha !== null
                ? [['label' => __('seguridad.usuarios.aside_persona_accion'), 'href' => route('panel.personas.index', ['q' => $ficha->nombre]), 'icono' => 'list']]
                : [],
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    private function tarjetaDispositivos(SecUser $usuario, ListarDispositivosDeUsuario $listarDispositivos): array
    {
        $dispositivos = $listarDispositivos->ejecutar($usuario);
        $ultimoUso = $dispositivos->max('last_used_at');

        return [
            'titulo' => __('seguridad.usuarios.aside_dispositivos_titulo'),
            'icono' => 'smartphone',
            'tieneDatos' => $dispositivos->isNotEmpty(),
            'items' => [
                ['label' => __('seguridad.usuarios.aside_dispositivos_total'), 'value' => (string) $dispositivos->count(), 'mono' => true],
                ['label' => __('seguridad.usuarios.aside_dispositivos_ultimo_uso'), 'value' => $ultimoUso !== null ? $ultimoUso->diffForHumans() : __('seguridad.dispositivos.sin_uso')],
            ],
            'vacioTitulo' => __('seguridad.usuarios.aside_dispositivos_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_dispositivos_vacio_detalle'),
            // La lista de dispositivos se acota a esta cuenta con su usuario en el buscador.
            'acciones' => $dispositivos->isNotEmpty()
                ? [['label' => __('seguridad.usuarios.aside_dispositivos_accion'), 'href' => route('panel.dispositivos.index', ['q' => $usuario->username]), 'icono' => 'list']]
                : [],
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    private function tarjetaContrato(SecUser $usuario, LecturaContrato $lecturaContrato): array
    {
        $contrato = $usuario->contrato_id !== null ? $lecturaContrato->obtenerResumen($usuario->contrato_id) : null;

        return [
            'titulo' => __('seguridad.usuarios.aside_contrato_titulo'),
            'icono' => 'description',
            'tieneDatos' => $contrato !== null,
            'items' => $contrato === null ? [] : [
                ['label' => __('seguridad.usuarios.aside_contrato_cliente'), 'value' => $contrato->clienteNombre],
                ['label' => __('seguridad.usuarios.aside_contrato_campania'), 'value' => $contrato->campaniaCodigo ?? __('seguridad.usuarios.aside_contrato_sin_campania'), 'mono' => true],
            ],
            'vacioTitulo' => __('seguridad.usuarios.aside_contrato_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_contrato_vacio_detalle'),
            'acciones' => $contrato !== null
                ? [['label' => __('seguridad.usuarios.aside_contrato_accion'), 'href' => route('panel.contratos.index', ['cliente_id' => $contrato->clienteId]), 'icono' => 'list']]
                : [],
        ];
    }

    /**
     * Últimos movimientos de la propia cuenta en la bitácora: lee con
     * {@see ListarBitacora} —el mismo caso de uso de `/panel/bitacora`, acotado
     * a `sec_user` y a esta cuenta— sin tocar la bitácora.
     *
     * @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}
     */
    private function tarjetaBitacora(SecUser $usuario, string $zonaQueVe, ListarBitacora $listarBitacora): array
    {
        $filas = $listarBitacora->ejecutar(
            zonaQueVe: $zonaQueVe,
            tabla: 'sec_user',
            registroId: (int) $usuario->id,
            porPagina: 3,
        )->items();

        return [
            'titulo' => __('seguridad.usuarios.aside_bitacora_titulo'),
            'icono' => 'history',
            'tieneDatos' => $filas !== [],
            // Acción y fecha: una línea por movimiento. Quién lo hizo y el detalle del cambio están en la bitácora.
            'items' => array_map(fn ($fila): array => [
                'label' => __('seguridad.bitacora.acciones.'.$fila->accion->value),
                'value' => $fila->instante->format('d/m/Y H:i'),
                'mono' => true,
            ], $filas),
            'vacioTitulo' => __('seguridad.usuarios.aside_bitacora_vacio_titulo'),
            'vacioDetalle' => __('seguridad.usuarios.aside_bitacora_vacio_detalle'),
            'acciones' => $filas !== []
                ? [['label' => __('seguridad.usuarios.aside_bitacora_accion'), 'href' => route('panel.bitacora.index', ['tabla' => 'sec_user', 'registro_id' => $usuario->id]), 'icono' => 'history']]
                : [],
        ];
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

    /**
     * Clientes vivos, para el select que solo FILTRA al de contrato (tarea
     * 65): igual criterio que `cliente_id` en `lotes/_formulario.blade.php`
     * — ninguna cuenta guarda esto, se resuelve del lado del cliente vía
     * `usuarios-form.js`. Lectura directa (`Comercial` es otro módulo, ADR
     * 0003 regla 3), mismo criterio que {@see self::personasDisponibles()}.
     *
     * @return Collection<int, string>
     */
    private function clientesDisponibles(): Collection
    {
        return DB::table('com_clientes')
            ->whereNull('deleted_at')
            ->orderBy('razon_social')
            ->pluck('razon_social', 'id');
    }

    /**
     * Contratos vigentes con su cliente, para el select dependiente
     * cliente→contrato del camino portal (tarea 65). Solo `estado =
     * 'vigente'` (valor de
     * `App\Dominios\Comercial\Dominio\EstadoContrato::Vigente`, leído como
     * dato — nunca se importa el enum de otro módulo, ADR 0003 regla 3):
     * `CrearCuentaPortal`/`*UsuarioRequest` exigen lo mismo, así que no
     * tiene sentido ofrecer en el `<select>` un contrato que el submit
     * rechazaría.
     *
     * @return Collection<int, \stdClass> cada fila con `id`, `cliente_id`,
     *                                    `razon_social`, `hectareas_contratadas`.
     */
    private function contratosVigentesDisponibles(): Collection
    {
        return DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNull('c.deleted_at')
            ->where('c.estado', 'vigente')
            ->orderBy('cl.razon_social')
            ->get(['c.id', 'c.cliente_id', 'cl.razon_social', 'c.hectareas_contratadas']);
    }

    /**
     * Correo sugerido por cliente, para precargar el campo `email` del
     * camino portal (tarea 66, ampliación ADR 0004 9/9/2026): el contacto
     * `dueno` si tiene correo, si no el primero con correo. Solo filtra el
     * valor inicial del campo — el administrador siempre puede escribir
     * otro, y el submit no vuelve a leer esta lista. Lectura directa
     * (`Comercial` es otro módulo, ADR 0003 regla 3), mismo criterio que
     * {@see self::clientesDisponibles()}.
     *
     * @return array<int, string> cliente_id => email
     */
    private function emailPorCliente(): array
    {
        return DB::table('com_cliente_contactos')
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->orderByRaw("case when tipo = 'dueno' then 0 else 1 end")
            ->orderBy('id')
            ->get(['cliente_id', 'email'])
            ->groupBy('cliente_id')
            ->map(fn (Collection $contactos) => $contactos->first()->email)
            ->all();
    }
}
