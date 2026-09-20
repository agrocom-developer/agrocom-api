{{--
    Page: repuestos/index (GET /panel/repuestos, panel.repuestos.index)
    Listado del catálogo de repuestos (HU-36, tarea 52): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado en la tarea 117 con el patrón de Planes de
    mantenimiento: `index-table`, `row-actions`, `confirm-modal` para la baja
    (antes era la confirmación nativa del navegador) y `pagination`.

    Solo buscador, sin `filter-panel`: el catálogo se busca por texto (código o
    descripción) y no tiene otra dimensión por la que filtrar. Un catálogo
    tampoco muestra activo/inactivo (guía §6.2). Sin franja de KPI: la alerta de
    mínimo es de `inv_stock` (por base), no del catálogo, y vive en
    stock/index.blade.php.

    Datos esperados (ver RepuestosController::index()): la cáscara de
    CascaraPanel, más:
    - $repuestos (LengthAwarePaginator<Repuesto>): código ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo con
      su valor tras el submit.

    El costo de la última compra es DECIMAL: la vista solo lo formatea
    (`FormatoCantidad`, sin `float`), nunca calcula con él (invariante 6).

    Gateada por `inventario.repuesto.ver`, verificado server-side en el
    controlador. Los botones "Nuevo repuesto"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    RepuestosController).

    Estilos en resources/css/pages/repuestos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Inventario\Infraestructura\Http\FormatoCantidad')
<x-templates.panel-shell :title="__('inventario.repuestos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('inventario.repuestos.titulo')"
    >
        <div class="ag-repuestos">
            <x-organisms.page-header
                :title="__('inventario.repuestos.titulo')"
                :subtitle="__('inventario.repuestos.subtitulo')"
            >
                @puede('inventario.repuesto.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.repuestos.create')" variant="primary" icon="add">
                            {{ __('inventario.repuestos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayBusqueda = $filtros['q'] !== '';
            @endphp

            @if ($hayBusqueda || $repuestos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.repuestos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('inventario.repuestos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($repuestos->isEmpty())
                @if ($hayBusqueda)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('inventario.repuestos.filtro_vacio_titulo')"
                        :detail="__('inventario.repuestos.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="construction"
                        :title="__('inventario.repuestos.vacio_titulo')"
                        :detail="__('inventario.repuestos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1fr) minmax(0, 2fr) minmax(0, 0.8fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_codigo') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_descripcion') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_unidad') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_costo') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($repuestos as $repuesto)
                        @php
                            $formIdEliminar = "repuesto-eliminar-{$repuesto->id}";
                            $modalIdEliminar = "repuesto-eliminar-modal-{$repuesto->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($repuestos->currentPage() - 1) * $repuestos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-repuestos__codigo">{{ $repuesto->codigo }}</span>
                            <span role="cell">{{ $repuesto->descripcion }}</span>
                            <span role="cell">{{ $repuesto->unidad }}</span>
                            <span role="cell" class="ag-repuestos__costo">
                                @if ($repuesto->costo_unitario !== null)
                                    {{ __('inventario.repuestos.costo_valor', ['monto' => FormatoCantidad::decimal($repuesto->costo_unitario)]) }}
                                @else
                                    {{ __('inventario.repuestos.sin_costo') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock),
                                     así que un <form> o un modal con id ahí adentro se
                                     duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. El disparador sí va adentro (es un
                                     botón sin id propio). Mismo criterio que planes/index. --}}
                                @puede('inventario.repuesto.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.repuestos.destroy', $repuesto) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('inventario.repuestos.confirmar_baja_titulo')"
                                        :message="__('inventario.repuestos.confirmar_baja')"
                                        :confirm-label="__('inventario.repuestos.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('inventario.repuesto.editar')
                                        <x-atoms.button :href="route('panel.repuestos.edit', $repuesto)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('inventario.repuestos.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('inventario.repuesto.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdEliminar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('inventario.repuestos.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$repuestos" :aria-label="__('inventario.repuestos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
