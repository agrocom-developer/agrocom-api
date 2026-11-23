<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Mantenimiento\Aplicacion\ActualizarFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\CrearFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\EliminarFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\ListarFichasDron;
use App\Dominios\Mantenimiento\Dominio\Excepciones\FichaDronDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarFichaDronRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearFichaDronRequest;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/fichas-dron*` (HU-82, tarea 97): alta y
 * mantenimiento de la ficha de inventario del dron (serie, chasis, versión
 * de software, región, serie del control, accesorios). Mismo molde que
 * `BateriasController`, sin sub-entidad ni cruce con otro módulo salvo la
 * validación de `identificador_dron` que hace el propio Request.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.ficha_dron.ver`/`.crear`/`.editar`/`.eliminar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 */
final class FichasDronController
{
    private const PERMISO_VER = 'mantenimiento.ficha_dron.ver';

    private const PERMISO_CREAR = 'mantenimiento.ficha_dron.crear';

    private const PERMISO_EDITAR = 'mantenimiento.ficha_dron.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.ficha_dron.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarFichasDron $listarFichasDron): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');

        $fichas = $listarFichasDron->ejecutar(busqueda: $busqueda !== '' ? $busqueda : null);

        return view('mantenimiento::pages.fichas-dron.index', [
            ...$this->autorizacion->cascara($request),
            'fichas' => $fichas,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.fichas-dron.create', [
            ...$this->autorizacion->cascara($request),
            // Atajo «Crear ficha» de la ficha de un dron: llega con su identificador. Solo
            // prellena el campo; el Request sigue validando que exista en `ope_drones`.
            'identificadorSugerido' => $request->string('identificador_dron')->toString(),
        ]);
    }

    public function store(CrearFichaDronRequest $request, CrearFichaDron $crearFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $fichaDron = $crearFichaDron->ejecutar(
                (string) $datos['identificador_dron'],
                $this->cadenaONull($datos['numero_serie'] ?? null),
                $this->cadenaONull($datos['chasis'] ?? null),
                $this->cadenaONull($datos['version_software'] ?? null),
                $this->cadenaONull($datos['region'] ?? null),
                $this->cadenaONull($datos['serie_control'] ?? null),
                (bool) ($datos['tiene_cargador_control'] ?? false),
                (bool) ($datos['tiene_modem'] ?? false),
                (bool) ($datos['tiene_maletin'] ?? false),
            );
        } catch (FichaDronDuplicada $excepcion) {
            return redirect()
                ->route('panel.fichas-dron.create')
                ->withInput()
                ->withErrors(['identificador_dron' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.fichas-dron.edit', $fichaDron)
            ->with('estado', __('mantenimiento.fichas_dron.creado'));
    }

    public function edit(
        Request $request,
        FichaDron $fichaDron,
        LecturaDrones $lecturaDrones,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.fichas-dron.edit', [
            ...$this->autorizacion->cascara($request),
            'ficha' => $fichaDron,
            'resumenRelacionado' => $this->resumenRelacionado($fichaDron, $request, $lecturaDrones),
        ]);
    }

    public function update(ActualizarFichaDronRequest $request, FichaDron $fichaDron, ActualizarFichaDron $actualizarFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarFichaDron->ejecutar(
                $fichaDron,
                (string) $datos['identificador_dron'],
                $this->cadenaONull($datos['numero_serie'] ?? null),
                $this->cadenaONull($datos['chasis'] ?? null),
                $this->cadenaONull($datos['version_software'] ?? null),
                $this->cadenaONull($datos['region'] ?? null),
                $this->cadenaONull($datos['serie_control'] ?? null),
                (bool) ($datos['tiene_cargador_control'] ?? false),
                (bool) ($datos['tiene_modem'] ?? false),
                (bool) ($datos['tiene_maletin'] ?? false),
            );
        } catch (FichaDronDuplicada $excepcion) {
            return redirect()
                ->route('panel.fichas-dron.edit', $fichaDron)
                ->withInput()
                ->withErrors(['identificador_dron' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.fichas-dron.edit', $fichaDron)
            ->with('estado', __('mantenimiento.fichas_dron.actualizado'));
    }

    public function destroy(Request $request, FichaDron $fichaDron, EliminarFichaDron $eliminarFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarFichaDron->ejecutar($fichaDron);

        return redirect()
            ->route('panel.fichas-dron.index')
            ->with('estado', __('mantenimiento.fichas_dron.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): la tarjeta del dron al que pertenece la ficha, gateada
     * por el permiso de LO QUE MUESTRA contra el ROL ACTIVO (invariante 10), no
     * por `mantenimiento.ficha_dron.*`. Con `.crear` pero sin `.ver` se ofrece
     * el atajo sin revelar datos.
     *
     * La relación no es una FK: la ficha se correlaciona con su dron por el
     * TEXTO del identificador (`man_drones.identificador_dron`). El dron se
     * resuelve por el contrato de Operaciones (ADR 0003, regla 2).
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        FichaDron $ficha,
        Request $request,
        LecturaDrones $lecturaDrones,
    ): array {
        $resumen = [];

        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.fichas-dron.edit', $ficha), 'volver_texto' => $ficha->identificador_dron];

        $puedeVerDron = $this->autorizacion->tienePermiso($request, 'operaciones.dron.ver');
        $puedeEditarDron = $this->autorizacion->tienePermiso($request, 'operaciones.dron.editar');
        $puedeCrearDron = $this->autorizacion->tienePermiso($request, 'operaciones.dron.crear');

        $dron = $lecturaDrones->porIdentificador($ficha->identificador_dron);

        // 1) El dron (Operaciones, por contrato). El alta de dron no acepta el
        // identificador precargado: el texto del vacío lo dice. Con `.crear` pero
        // sin `.ver` la tarjeta solo tiene sentido si el dron NO existe (ofrece
        // crearlo): si existe y no se puede ver, «sin dron en el catálogo» sería falso.
        if ($puedeVerDron || ($puedeCrearDron && $dron === null)) {
            $verDron = $puedeVerDron ? $dron : null;
            $sinDato = __('mantenimiento.fichas_dron.aside_dron_sin_dato');
            $acciones = [];

            if ($verDron !== null && $puedeEditarDron) {
                $acciones[] = [
                    'label' => __('mantenimiento.fichas_dron.aside_dron_accion_ver'),
                    'href' => route('panel.drones.edit', $verDron->id),
                    'icono' => 'arrow_forward',
                ];
            }

            if ($dron === null && $puedeCrearDron) {
                $acciones[] = [
                    'label' => __('mantenimiento.fichas_dron.aside_dron_accion_crear'),
                    'href' => route('panel.drones.create', $origenNavegacion),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('mantenimiento.fichas_dron.aside_dron_titulo'),
                'icono' => 'airplanemode_active',
                'tieneDatos' => $verDron !== null,
                'items' => [
                    ['label' => __('mantenimiento.fichas_dron.aside_dron_identificador'), 'value' => $verDron->identificador ?? $sinDato, 'mono' => true],
                    ['label' => __('mantenimiento.fichas_dron.aside_dron_modelo'), 'value' => $verDron->modelo ?? $sinDato],
                ],
                'vacioTitulo' => __('mantenimiento.fichas_dron.aside_dron_vacio_titulo'),
                'vacioDetalle' => __('mantenimiento.fichas_dron.aside_dron_vacio_detalle', ['identificador' => $ficha->identificador_dron]),
                'acciones' => $acciones,
            ];
        }

        return $resumen;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
