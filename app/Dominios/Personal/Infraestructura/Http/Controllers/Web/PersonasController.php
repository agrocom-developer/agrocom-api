<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Contratos\LecturaAnticiposPorPersona;
use App\Dominios\Operaciones\Contratos\LecturaSesionesPorPersona;
use App\Dominios\Personal\Aplicacion\ActualizarPersona;
use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\EliminarPersona;
use App\Dominios\Personal\Aplicacion\ListarPersonas;
use App\Dominios\Personal\Aplicacion\ObtenerDesempenioPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarPersonaRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearPersonaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaUsuarioDePersona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/personas*` (HU-26, tarea 37): alta y
 * mantenimiento de personas operativas, con su rol y base.
 * Mismo molde que `BasesController` (misma tarea), pero con un `select`
 * adicional de base (opcional) — mismo criterio que `cliente_id` en
 * `CamposController`.
 *
 * Cuatro permisos de grano fino
 * (`personal.persona.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo. La tarifa ya no se edita
 * acá (ADR 0023): es del trabajo, no de la persona.
 */
final class PersonasController
{
    private const PERMISO_VER = 'personal.persona.ver';

    private const PERMISO_CREAR = 'personal.persona.crear';

    private const PERMISO_EDITAR = 'personal.persona.editar';

    private const PERMISO_ELIMINAR = 'personal.persona.eliminar';

    private const PERMISO_DESEMPENIO = 'personal.persona.desempenio';

    /** Del módulo Finanzas (ADR 0003 regla 2): solo para decidir si se ofrece el enlace "sus devengos" del aside de `desempenio()`. */
    private const PERMISO_DEVENGO = 'finanzas.devengo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPersonas $listarPersonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('personal::pages.personas.index', [
            ...$this->autorizacion->cascara($request),
            'personas' => $listarPersonas->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.personas.create', [
            ...$this->autorizacion->cascara($request),
            // `rolesOperativos`, no `roles`: `roles` ya es de la cáscara (los
            // roles del USUARIO, que el panel usa para ofrecer «Cambiar de rol»)
            // y pisarlo con los del enum lo dejaba siempre en «varios roles».
            'rolesOperativos' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
            // Atajo «Nueva persona» de la ficha de una base: llega con
            // `?base_id=` y el formulario la deja elegida.
            'baseIdInicial' => $request->string('base_id')->toString(),
            // Alta rápida desde otro formulario (tarea "cuadrillas-estadias",
            // 19/9/2026 — mismo criterio que `PropiedadesController::create()`):
            // con ?volver_a=, al guardar se ofrece un botón para volver a esa
            // URL con esta persona ya disponible en el select que la pidió.
            'volverA' => $this->origenLocal($request->query('volver_a')),
        ]);
    }

    public function store(CrearPersonaRequest $request, CrearPersona $crearPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $persona = $crearPersona->ejecutar($request->datosPersona());

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        // `volverA` viaja igual que en `PropiedadesController::store()` para
        // el caso de alta rápida desde otro formulario.
        return redirect()
            ->route('panel.personas.edit', $persona)
            ->with('estado', __('personal.personas.creado'))
            ->with('volverA', $this->origenLocal($request->input('volver_a')));
    }

    public function edit(
        Request $request,
        PerPersona $persona,
        LecturaUsuarioDePersona $lecturaUsuario,
        LecturaSesionesPorPersona $lecturaSesiones,
        LecturaAnticiposPorPersona $lecturaAnticipos,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.personas.edit', [
            ...$this->autorizacion->cascara($request),
            'persona' => $persona,
            'rolesOperativos' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
            'volverA' => session('volverA'),
            'resumenRelacionado' => $this->resumenRelacionado($persona, $request, $lecturaUsuario, $lecturaSesiones, $lecturaAnticipos),
        ]);
    }

    public function update(ActualizarPersonaRequest $request, PerPersona $persona, ActualizarPersona $actualizarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $actualizarPersona->ejecutar($persona, $request->datosPersona());

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.personas.edit', $persona)
            ->with('estado', __('personal.personas.actualizado'));
    }

    public function destroy(Request $request, PerPersona $persona, EliminarPersona $eliminarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarPersona->ejecutar($persona);

        return redirect()
            ->route('panel.personas.index')
            ->with('estado', __('personal.personas.eliminado'));
    }

    /**
     * Ficha de desempeño (HU-58, tarea 81; homogeneizada al arquetipo
     * Detalle en la tarea 125): "¿qué hizo esta persona esta campaña?", por
     * sesión y no por equipo de trabajo (ADR 0015 punto 3). Filtros por
     * `GET` con querystring, mismo criterio que `CuadrillasController::show()`
     * — rango de fechas (default los últimos 12 meses) y cliente/campaña,
     * esta última dependiente del cliente elegido (JS, presentación — el
     * caso de uso ya filtra en PHP sin importar lo que el navegador haya
     * mostrado u ocultado).
     */
    public function desempenio(Request $request, PerPersona $persona, ObtenerDesempenioPersona $obtenerDesempenio): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_DESEMPENIO), 403);

        $hastaQuery = $request->string('hasta')->toString();
        $hasta = $hastaQuery !== '' ? $hastaQuery : now()->toDateString();

        $desdeQuery = $request->string('desde')->toString();
        $desde = $desdeQuery !== '' ? $desdeQuery : now()->subMonths(12)->toDateString();

        $clienteQuery = $request->string('cliente_id')->toString();
        $clienteId = $clienteQuery !== '' ? (int) $clienteQuery : null;

        $campaniaQuery = $request->string('campania_id')->toString();
        $campaniaId = $campaniaQuery !== '' ? (int) $campaniaQuery : null;

        $resultado = $obtenerDesempenio->ejecutar($persona->id, $desde, $hasta, $clienteId, $campaniaId);

        return view('personal::pages.personas.desempeno', [
            ...$this->autorizacion->cascara($request),
            'persona' => $persona,
            'resultado' => $resultado,
            'filtros' => ['desde' => $desde, 'hasta' => $hasta, 'cliente_id' => $clienteId, 'campania_id' => $campaniaId],
            'vinculos' => $this->vinculosDeDesempenio($persona, $request),
        ]);
    }

    /**
     * "Relacionado" del aside de `desempenio()` (arquetipo Detalle, tarea
     * 125): la ficha de la persona, las cuadrillas que integra hoy y, solo
     * si `$persona` ES la propia persona del usuario autenticado, sus
     * devengos — `DevengosController::show()` hace 404 ante cualquier otra
     * `persona_id`, sin importar el permiso del actor (identidad, no
     * permiso — ver su docblock). No se inventa un contrato de lectura
     * cruzado para ver los devengos de OTRA persona: `AutorizacionPanelWeb::personaId()`
     * (ya usado en el resto del panel) es suficiente para decidir si
     * corresponde ofrecer el enlace.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDeDesempenio(PerPersona $persona, Request $request): array
    {
        $vinculos = [[
            'href' => route('panel.personas.edit', $persona),
            'icon' => 'badge',
            'title' => __('personal.desempenio.vinculo_persona'),
            'meta' => $persona->nombre,
            'tone' => 'neutral',
        ]];

        if ($this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver')) {
            $hoy = now()->toDateString();
            $vigentes = EquipoIntegrante::query()
                ->where('persona_id', $persona->id)
                ->where('desde', '<=', $hoy)
                ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
                ->whereHas('equipoTrabajo')
                ->with('equipoTrabajo')
                ->get()
                ->unique('equipo_trabajo_id');

            foreach ($vigentes as $integrante) {
                $vinculos[] = [
                    'href' => route('panel.cuadrillas.show', $integrante->equipo_trabajo_id),
                    'icon' => 'groups',
                    'title' => $integrante->equipoTrabajo->codigo,
                    'meta' => __('personal.rol_equipo.'.$integrante->rol_equipo->value),
                    'tone' => 'distintivo-1',
                ];
            }
        }

        $personaIdAutenticada = $this->autorizacion->personaId($request);

        if ($personaIdAutenticada === $persona->id && $this->autorizacion->tienePermiso($request, self::PERMISO_DEVENGO)) {
            $vinculos[] = [
                'href' => route('panel.devengos.show', $persona->id),
                'icon' => 'payments',
                'title' => __('personal.desempenio.vinculo_devengos'),
                'meta' => null,
                'tone' => 'success',
            ];
        }

        return $vinculos;
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): una persona recién creada no puede tener todavía
     * cuadrillas, usuario, sesiones ni anticipos. Cuatro tarjetas, cada una
     * gateada por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
     * (invariante 10), no por `personal.persona.*`: ver los anticipos de una
     * persona es ver anticipos. Una categoría sin `.ver` NI `.crear` se omite
     * del todo; con `.crear` pero sin `.ver` se ofrece el atajo sin revelar
     * cifras. Cuadrillas y sesiones no tienen atajo de alta: una persona entra
     * a una cuadrilla desde la ficha de la cuadrilla y las sesiones llegan por
     * la app de campo.
     *
     * Cuadrillas es del mismo módulo (Eloquent directo). Usuario, sesiones y
     * anticipos son de Seguridad, Operaciones y Finanzas: llegan por sus
     * contratos de lectura (ADR 0003, regla 2), nunca por sus tablas. Los
     * devengos NO se muestran: cada persona ve los suyos
     * (`finanzas.devengo.ver`) y no existe un permiso para verlos desde otra
     * ficha.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        PerPersona $persona,
        Request $request,
        LecturaUsuarioDePersona $lecturaUsuario,
        LecturaSesionesPorPersona $lecturaSesiones,
        LecturaAnticiposPorPersona $lecturaAnticipos,
    ): array {
        $resumen = [];

        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.personas.edit', $persona), 'volver_texto' => $persona->nombre];

        // 1) Cuadrillas que integra (mismo módulo). «Vigente» es lo mismo que
        // en el listado de cuadrillas: `desde` ya pasó y `hasta` no venció. Una
        // cuadrilla dada de baja no cuenta (`whereHas` respeta su soft delete).
        if ($this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver')) {
            $hoy = now()->toDateString();
            $integraciones = EquipoIntegrante::query()->where('persona_id', $persona->id)->whereHas('equipoTrabajo');
            $totalCuadrillas = (clone $integraciones)->distinct()->count('equipo_trabajo_id');
            $vigentes = (clone $integraciones)
                ->where('desde', '<=', $hoy)
                ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
                ->with('equipoTrabajo')
                ->get()
                ->unique('equipo_trabajo_id');

            $items = [
                [
                    'label' => __('personal.personas.aside_cuadrillas_vigentes'),
                    'value' => (string) $vigentes->count(),
                    'mono' => true,
                    'variant' => $vigentes->isNotEmpty() ? 'success' : 'neutral',
                ],
                ['label' => __('personal.personas.aside_cuadrillas_historial'), 'value' => (string) $totalCuadrillas, 'mono' => true],
            ];

            if ($vigentes->isNotEmpty()) {
                $items[] = [
                    'label' => __('personal.personas.aside_cuadrillas_actual'),
                    'value' => $vigentes->map(fn (EquipoIntegrante $integrante): string => $integrante->equipoTrabajo->codigo)->implode(', '),
                    'mono' => true,
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.personas.aside_cuadrillas_titulo'),
                'icono' => 'groups',
                'tieneDatos' => $totalCuadrillas > 0,
                'items' => $items,
                'vacioTitulo' => __('personal.personas.aside_cuadrillas_vacio_titulo'),
                'vacioDetalle' => __('personal.personas.aside_cuadrillas_vacio_detalle'),
                'acciones' => $totalCuadrillas > 0 ? [[
                    'label' => __('personal.personas.aside_cuadrillas_accion_ver'),
                    'href' => route('panel.cuadrillas.index'),
                    'icono' => 'list',
                ]] : [],
            ];
        }

        // 2) Usuario vinculado (Seguridad, por contrato).
        $puedeVerUsuario = $this->autorizacion->tienePermiso($request, 'seguridad.usuario.ver');
        $puedeCrearUsuario = $this->autorizacion->tienePermiso($request, 'seguridad.usuario.crear');

        if ($puedeVerUsuario || $puedeCrearUsuario) {
            $usuario = $puedeVerUsuario ? $lecturaUsuario->dePersona($persona->id) : null;
            $acciones = [];

            if ($usuario !== null && $this->autorizacion->tienePermiso($request, 'seguridad.usuario.editar')) {
                $acciones[] = [
                    'label' => __('personal.personas.aside_usuario_accion_ver'),
                    'href' => route('panel.usuarios.edit', $usuario->id),
                    'icono' => 'arrow_forward',
                ];
            }

            if ($usuario === null && $puedeCrearUsuario) {
                $acciones[] = [
                    'label' => __('personal.personas.aside_usuario_accion_crear'),
                    'href' => route('panel.usuarios.create', $origenNavegacion),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.personas.aside_usuario_titulo'),
                'icono' => 'manage_accounts',
                'tieneDatos' => $usuario !== null,
                'items' => $usuario === null ? [] : [
                    ['label' => __('personal.personas.aside_usuario_usuario'), 'value' => $usuario->username, 'mono' => true],
                    [
                        'label' => __('personal.personas.aside_usuario_estado'),
                        'value' => __($usuario->activo ? 'personal.personas.aside_usuario_activo' : 'personal.personas.aside_usuario_bloqueado'),
                        'badge' => true,
                        'variant' => $usuario->activo ? 'success' : 'danger',
                    ],
                    ['label' => __('personal.personas.aside_usuario_roles'), 'value' => (string) $usuario->roles, 'mono' => true],
                ],
                'vacioTitulo' => __('personal.personas.aside_usuario_vacio_titulo'),
                'vacioDetalle' => __('personal.personas.aside_usuario_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        // 3) Sesiones de vuelo (Operaciones, por contrato). Los permisos de
        // `operaciones.trabajo.*` cubren «trabajos y sesiones»; no hay atajo de
        // alta, pero sí el paso natural a la ficha de desempeño.
        if ($this->autorizacion->tienePermiso($request, 'operaciones.trabajo.ver')) {
            $sesiones = $lecturaSesiones->dePersona($persona->id);

            $resumen[] = [
                'titulo' => __('personal.personas.aside_sesiones_titulo'),
                'icono' => 'flight',
                'tieneDatos' => $sesiones->total > 0,
                'items' => [
                    ['label' => __('personal.personas.aside_sesiones_total'), 'value' => (string) $sesiones->total, 'mono' => true],
                    [
                        'label' => __('personal.personas.aside_sesiones_validadas'),
                        'value' => (string) $sesiones->validadas,
                        'mono' => true,
                        'variant' => $sesiones->validadas > 0 ? 'success' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('personal.personas.aside_sesiones_vacio_titulo'),
                'vacioDetalle' => __('personal.personas.aside_sesiones_vacio_detalle'),
                'acciones' => $sesiones->total > 0 && $this->autorizacion->tienePermiso($request, self::PERMISO_DESEMPENIO) ? [[
                    'label' => __('personal.personas.aside_sesiones_accion_desempenio'),
                    'href' => route('panel.personas.desempenio', $persona),
                    'icono' => 'insights',
                ]] : [],
            ];
        }

        // 4) Anticipos (Finanzas, por contrato).
        $puedeVerAnticipos = $this->autorizacion->tienePermiso($request, 'finanzas.anticipo.ver');
        $puedeCrearAnticipos = $this->autorizacion->tienePermiso($request, 'finanzas.anticipo.crear');

        if ($puedeVerAnticipos || $puedeCrearAnticipos) {
            $anticipos = $puedeVerAnticipos ? $lecturaAnticipos->dePersona($persona->id) : null;
            $cantidad = $anticipos->cantidad ?? 0;
            $acciones = [];

            if ($puedeVerAnticipos && $cantidad > 0) {
                $acciones[] = [
                    'label' => __('personal.personas.aside_anticipos_accion_ver'),
                    'href' => route('panel.anticipos.index', ['persona_id' => $persona->id]),
                    'icono' => 'list',
                ];
            }

            if ($puedeCrearAnticipos) {
                $acciones[] = [
                    'label' => __('personal.personas.aside_anticipos_accion_registrar'),
                    'href' => route('panel.anticipos.create', ['persona_id' => $persona->id, ...$origenNavegacion]),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.personas.aside_anticipos_titulo'),
                'icono' => 'payments',
                'tieneDatos' => $cantidad > 0,
                'items' => [
                    ['label' => __('personal.personas.aside_anticipos_cantidad'), 'value' => (string) $cantidad, 'mono' => true],
                    [
                        'label' => __('personal.personas.aside_anticipos_total'),
                        'value' => __('personal.personas.aside_anticipos_valor', ['monto' => $anticipos->montoTotal ?? '0.00']),
                        'mono' => true,
                    ],
                ],
                'vacioTitulo' => __('personal.personas.aside_anticipos_vacio_titulo'),
                'vacioDetalle' => __('personal.personas.aside_anticipos_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        return $resumen;
    }

    /** @return Collection<int, string> */
    private function basesActivas(): Collection
    {
        return PerBase::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * El `volver_a` de una alta rápida termina como `href` del botón «Volver al
     * formulario de origen»: solo se acepta una ruta de este mismo sitio (relativa,
     * o absoluta bajo la URL de la app) y sin espacios, controles ni barras
     * invertidas — el navegador los descarta o normaliza y `/\ /otro.com` acaba
     * siendo otro dominio. Cualquier otra cosa (`javascript:`, otro host) se ignora.
     */
    private function origenLocal(mixed $url): ?string
    {
        if (! is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        $esRutaLocal = str_starts_with($url, '/') && ! str_starts_with($url, '//');

        return $esRutaLocal || str_starts_with($url, url('/').'/') ? $url : null;
    }
}
