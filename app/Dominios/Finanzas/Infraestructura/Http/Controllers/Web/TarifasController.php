<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Finanzas\Aplicacion\ActualizarTarifa;
use App\Dominios\Finanzas\Aplicacion\CrearTarifa;
use App\Dominios\Finanzas\Aplicacion\EliminarTarifa;
use App\Dominios\Finanzas\Aplicacion\ListarTarifas;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Finanzas\Dominio\Excepciones\TarifaDuplicada;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\ActualizarTarifaRequest;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearTarifaRequest;
use App\Dominios\Operaciones\Contratos\LecturaUsoDeTarifa;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/tarifas*` (ADR 0023): alta y
 * mantenimiento del catálogo de tarifas de pago. Mismo molde que
 * `CultivosController` — ABM simple, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`finanzas.tarifa.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 */
final class TarifasController
{
    private const PERMISO_VER = 'finanzas.tarifa.ver';

    private const PERMISO_CREAR = 'finanzas.tarifa.crear';

    private const PERMISO_EDITAR = 'finanzas.tarifa.editar';

    private const PERMISO_ELIMINAR = 'finanzas.tarifa.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarTarifas $listarTarifas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');

        return view('finanzas::pages.tarifas.index', [
            ...$this->autorizacion->cascara($request),
            'tarifas' => $listarTarifas->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => [
                'q' => $busqueda,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('finanzas::pages.tarifas.create', [
            ...$this->autorizacion->cascara($request),
            'modalidades' => ModalidadPago::opciones(),
        ]);
    }

    public function store(CrearTarifaRequest $request, CrearTarifa $crearTarifa): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->datosTarifa();

        try {
            $tarifa = $crearTarifa->ejecutar($datos);
        } catch (TarifaDuplicada $excepcion) {
            return redirect()
                ->route('panel.tarifas.create')
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.tarifas.edit', $tarifa)
            ->with('estado', __('finanzas.tarifas.creado'));
    }

    public function edit(Request $request, Tarifa $tarifa, LecturaUsoDeTarifa $uso): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('finanzas::pages.tarifas.edit', [
            ...$this->autorizacion->cascara($request),
            'tarifa' => $tarifa,
            'modalidades' => ModalidadPago::opciones(),
            'resumenTarifa' => $this->resumenRelacionado($tarifa, $request, $uso),
        ]);
    }

    /**
     * Resumen relacionado del arquetipo Formulario (molde de Cultivos): en
     * cuántas órdenes de trabajo se eligió esta tarifa. Lo lee por el contrato
     * de Operaciones, nunca por su tabla (ADR 0003).
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array{label: string, value: string, mono?: bool}>, acciones: list<array{href: string, label: string, icono?: string}>, vacioTitulo: string, vacioDetalle: string}>
     */
    private function resumenRelacionado(Tarifa $tarifa, Request $request, LecturaUsoDeTarifa $uso): array
    {
        if (! $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.ver')) {
            return [];
        }

        $resumen = $uso->resumenDe($tarifa->id);

        return [[
            'titulo' => __('finanzas.tarifas.aside_uso_titulo'),
            'icono' => 'work_history',
            'tieneDatos' => $resumen['equipos'] > 0,
            'items' => [
                ['label' => __('finanzas.tarifas.aside_uso_ordenes'), 'value' => (string) $resumen['ordenes'], 'mono' => true],
                ['label' => __('finanzas.tarifas.aside_uso_equipos'), 'value' => (string) $resumen['equipos'], 'mono' => true],
                ['label' => __('finanzas.tarifas.aside_uso_negociados'), 'value' => (string) $resumen['negociados'], 'mono' => true],
            ],
            'acciones' => [
                ['href' => route('panel.trabajos.index'), 'label' => __('finanzas.tarifas.aside_uso_accion'), 'icono' => 'arrow_forward'],
            ],
            'vacioTitulo' => __('finanzas.tarifas.aside_uso_vacio_titulo'),
            'vacioDetalle' => __('finanzas.tarifas.aside_uso_vacio_detalle'),
        ]];
    }

    public function update(ActualizarTarifaRequest $request, Tarifa $tarifa, ActualizarTarifa $actualizarTarifa): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->datosTarifa();

        try {
            $actualizarTarifa->ejecutar($tarifa, $datos);
        } catch (TarifaDuplicada $excepcion) {
            return redirect()
                ->route('panel.tarifas.edit', $tarifa)
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.tarifas.edit', $tarifa)
            ->with('estado', __('finanzas.tarifas.actualizado'));
    }

    public function destroy(Request $request, Tarifa $tarifa, EliminarTarifa $eliminarTarifa): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarTarifa->ejecutar($tarifa);

        return redirect()
            ->route('panel.tarifas.index')
            ->with('estado', __('finanzas.tarifas.eliminado'));
    }
}
