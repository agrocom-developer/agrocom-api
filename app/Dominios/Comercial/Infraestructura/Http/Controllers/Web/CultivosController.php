<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarCultivo;
use App\Dominios\Comercial\Aplicacion\CrearCultivo;
use App\Dominios\Comercial\Aplicacion\EliminarCultivo;
use App\Dominios\Comercial\Aplicacion\ListarCultivos;
use App\Dominios\Comercial\Aplicacion\ResumirSiembraDeCultivo;
use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\Excepciones\CultivoDuplicado;
use App\Dominios\Comercial\Dominio\TipoCultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarCultivoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearCultivoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/cultivos*` (HU-48, tarea 71): alta y
 * mantenimiento del catálogo de cultivos. Mismo molde que `BasesController`
 * — ABM simple, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`comercial.cultivo.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * Filtros de listado (ampliación 16/9/2026, arquetipo Listado §6.2 de
 * `guia_pantalla_panel.md`): `tipo_cultivo`/`ciclo_vida`/`activo`, todos
 * dentro de `filter-panel`, además del buscador de texto ya existente.
 */
final class CultivosController
{
    private const PERMISO_VER = 'comercial.cultivo.ver';

    private const PERMISO_CREAR = 'comercial.cultivo.crear';

    private const PERMISO_EDITAR = 'comercial.cultivo.editar';

    private const PERMISO_ELIMINAR = 'comercial.cultivo.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCultivos $listarCultivos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $tipoCultivo = $request->string('tipo_cultivo')->toString();
        $cicloVida = $request->string('ciclo_vida')->toString();

        return view('comercial::pages.cultivos.index', [
            ...$this->autorizacion->cascara($request),
            'cultivos' => $listarCultivos->ejecutar(
                busqueda: $busqueda !== '' ? $busqueda : null,
                tipoCultivo: $tipoCultivo !== '' ? $tipoCultivo : null,
                cicloVida: $cicloVida !== '' ? $cicloVida : null,
            ),
            'filtros' => [
                'q' => $busqueda,
                'tipo_cultivo' => $tipoCultivo,
                'ciclo_vida' => $cicloVida,
            ],
            'tiposCultivo' => TipoCultivo::cases(),
            'ciclosVida' => CicloVidaCultivo::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.cultivos.create', [
            ...$this->autorizacion->cascara($request),
            'tiposCultivo' => TipoCultivo::cases(),
            'ciclosVida' => CicloVidaCultivo::cases(),
        ]);
    }

    public function store(CrearCultivoRequest $request, CrearCultivo $crearCultivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $cultivo = $crearCultivo->ejecutar(
                (string) $datos['nombre_comun'],
                isset($datos['nombre_cientifico']) ? (string) $datos['nombre_cientifico'] : null,
                (string) $datos['tipo_cultivo'],
                (string) $datos['ciclo_vida'],
                isset($datos['notas_agronomicas']) ? (string) $datos['notas_agronomicas'] : null,
            );
        } catch (CultivoDuplicado $excepcion) {
            return redirect()
                ->route('panel.cultivos.create')
                ->withInput()
                ->withErrors(['nombre_comun' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.cultivos.edit', $cultivo)
            ->with('estado', __('comercial.cultivos.creado'));
    }

    public function edit(Request $request, Cultivo $cultivo, ResumirSiembraDeCultivo $resumirSiembra): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.cultivos.edit', [
            ...$this->autorizacion->cascara($request),
            'cultivo' => $cultivo,
            'tiposCultivo' => TipoCultivo::cases(),
            'ciclosVida' => CicloVidaCultivo::cases(),
            'resumenCultivo' => $this->resumenRelacionado($cultivo, $request, $resumirSiembra),
        ]);
    }

    public function update(ActualizarCultivoRequest $request, Cultivo $cultivo, ActualizarCultivo $actualizarCultivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarCultivo->ejecutar(
                $cultivo,
                (string) $datos['nombre_comun'],
                isset($datos['nombre_cientifico']) ? (string) $datos['nombre_cientifico'] : null,
                (string) $datos['tipo_cultivo'],
                (string) $datos['ciclo_vida'],
                isset($datos['notas_agronomicas']) ? (string) $datos['notas_agronomicas'] : null,
            );
        } catch (CultivoDuplicado $excepcion) {
            return redirect()
                ->route('panel.cultivos.edit', $cultivo)
                ->withInput()
                ->withErrors(['nombre_comun' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.cultivos.edit', $cultivo)
            ->with('estado', __('comercial.cultivos.actualizado'));
    }

    public function destroy(Request $request, Cultivo $cultivo, EliminarCultivo $eliminarCultivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarCultivo->ejecutar($cultivo);

        return redirect()
            ->route('panel.cultivos.index')
            ->with('estado', __('comercial.cultivos.eliminado'));
    }

    /**
     * Resumen relacionado del aside (solo edición, §6.3.1 de la guía de
     * pantalla): dos tarjetas —lotes sembrados y propiedades— sobre la
     * campaña vigente. Cada una se gatea por el permiso `.ver` del módulo de
     * LO QUE MUESTRA (lote, propiedad), no por el de cultivo, que ya se
     * verificó arriba; una tarjeta sin su `.ver` se omite del todo. Los datos
     * los resuelve {@see ResumirSiembraDeCultivo}, nunca esta vista.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(Cultivo $cultivo, Request $request, ResumirSiembraDeCultivo $resumirSiembra): array
    {
        $puedeVerLotes = $this->autorizacion->tienePermiso($request, 'comercial.lote.ver');
        $puedeVerPropiedades = $this->autorizacion->tienePermiso($request, 'comercial.propiedad.ver');

        if (! $puedeVerLotes && ! $puedeVerPropiedades) {
            return [];
        }

        $siembra = $resumirSiembra->ejecutar($cultivo);
        $resumen = [];

        if ($puedeVerLotes) {
            // La siembra se carga desde la ficha de la propiedad (permiso de
            // editarla, y verla para llegar): es el único acceso directo de "alta".
            $puedeCargarSiembra = $puedeVerPropiedades && $this->autorizacion->tienePermiso($request, 'comercial.propiedad.editar');

            $resumen[] = [
                'titulo' => __('comercial.cultivos.aside_lotes_titulo'),
                'icono' => 'grid_view',
                'tieneDatos' => $siembra->tieneSiembra(),
                'items' => [
                    ['label' => __('comercial.cultivos.aside_campania'), 'value' => implode(', ', $siembra->campanias), 'mono' => true],
                    ['label' => __('comercial.cultivos.aside_lotes_total'), 'value' => (string) $siembra->lotesSembrados, 'mono' => true],
                    ['label' => __('comercial.cultivos.aside_lotes_hectareas'), 'value' => number_format((float) $siembra->hectareasSembradas, 2, ',', '.'), 'mono' => true],
                ],
                'vacioTitulo' => $siembra->hayCampania()
                    ? __('comercial.cultivos.aside_lotes_vacio_titulo')
                    : __('comercial.cultivos.aside_sin_campania_titulo'),
                'vacioDetalle' => $siembra->hayCampania()
                    ? __('comercial.cultivos.aside_lotes_vacio_detalle')
                    : __('comercial.cultivos.aside_sin_campania_detalle'),
                // «Sembrar este cultivo» (21/9/2026): a la pantalla de siembra sin
                // propiedad elegida —cliente y propiedad se eligen ahí— y con este
                // cultivo ya puesto en el sector. Antes mandaba al listado de
                // propiedades y había que encontrar el camino a mano.
                'acciones' => [
                    ...($siembra->tieneSiembra()
                        ? [['label' => __('comercial.cultivos.aside_lotes_accion'), 'href' => route('panel.lotes.index'), 'icono' => 'list']]
                        : []),
                    ...($puedeCargarSiembra && $siembra->hayCampania()
                        ? [[
                            'label' => __('comercial.cultivos.aside_lotes_accion_siembra'),
                            'href' => route('panel.siembra', [
                                'cultivo_id' => $cultivo->id,
                                'volver_a' => route('panel.cultivos.edit', $cultivo),
                                'volver_texto' => $cultivo->nombre_comun,
                            ]),
                            'icono' => 'eco',
                        ]]
                        : []),
                ],
            ];
        }

        if ($puedeVerPropiedades) {
            $resumen[] = [
                'titulo' => __('comercial.cultivos.aside_propiedades_titulo'),
                'icono' => 'landscape',
                'tieneDatos' => $siembra->tieneSiembra(),
                'items' => [
                    ['label' => __('comercial.cultivos.aside_propiedades_total'), 'value' => (string) count($siembra->propiedades), 'mono' => true],
                    // Las tres con más hectáreas: alcanza para saber dónde está; la lista completa es la de propiedades.
                    ...array_map(
                        fn (array $propiedad): array => [
                            'label' => $propiedad['nombre'],
                            'value' => __('comercial.cultivos.aside_propiedades_hectareas_valor', ['cantidad' => number_format((float) $propiedad['hectareas'], 2, ',', '.')]),
                            'mono' => true,
                        ],
                        array_slice($siembra->propiedades, 0, 3),
                    ),
                ],
                'vacioTitulo' => $siembra->hayCampania()
                    ? __('comercial.cultivos.aside_propiedades_vacio_titulo')
                    : __('comercial.cultivos.aside_sin_campania_titulo'),
                'vacioDetalle' => $siembra->hayCampania()
                    ? __('comercial.cultivos.aside_propiedades_vacio_detalle')
                    : __('comercial.cultivos.aside_sin_campania_detalle'),
                'acciones' => $siembra->tieneSiembra()
                    ? [['label' => __('comercial.cultivos.aside_propiedades_accion'), 'href' => route('panel.propiedades.index'), 'icono' => 'list']]
                    : [],
            ];
        }

        return $resumen;
    }
}
