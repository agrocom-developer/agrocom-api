{{--
    Page: lotes/index (GET /panel/lotes, panel.lotes.index)
    Listado de lotes (tarea 77, HU-54, etapa 2; actualizado ADR 0020): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros →
    tabla → paginación. Antes de esta tarea un lote solo se podía ver entrando
    por su propiedad; esta pantalla es su ficha propia.

    Datos esperados (ver LotesController::index()): la cáscara de
    CascaraPanel, más:
    - $lotes (LengthAwarePaginator<Lote>, con `propiedad.cliente` cargada):
      código ascendente.
    - $propiedadFiltro (Propiedad|null): la propiedad del filtro `propiedad_id`,
      si lo hay — habilita el botón «Volver a la propiedad» (memento).
    - $filtros (array{q: string, cliente_id: ?int, propiedad_id: ?int}): filtros
      aplicados, para dejarlos con el valor tras el submit.
    - $clientesDisponibles (Collection<int, string>), $propiedadesDisponibles
      (Collection<int, Propiedad>): opciones de los selects de filtro (ADR 0020:
      cascade cliente → propiedad).

    Gateada por `comercial.lote.ver`, verificado server-side en el
    controlador. Los botones "Nuevo lote"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    LotesController).

    Estilos en resources/css/pages/lotes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.lotes.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.lotes.titulo')"
    >
        <div class="ag-lotes">
            <x-organisms.page-header
                :title="__('comercial.lotes.titulo')"
                :subtitle="__('comercial.lotes.subtitulo')"
            >
                <x-slot:actions>
                    {{-- Con el filtro de una propiedad se llegó desde su formulario
                         (memento de navegación): se vuelve a ella. Si hay pila, el
                         botón vuelve al escalón anterior y lo dice; si no, cae a la
                         ficha de la propiedad. --}}
                    @if ($propiedadFiltro !== null)
                        @puede('comercial.propiedad.editar')
                            <x-molecules.boton-volver :href="route('panel.propiedades.edit', $propiedadFiltro)" :label="__('comercial.lotes.volver_a_propiedad')" />
                        @endpuede
                    @endif

                    @puede('comercial.lote.crear')
                        <x-atoms.button :href="route('panel.lotes.create', array_filter(['propiedad_id' => $filtros['propiedad_id']]))" variant="primary" icon="add">
                            {{ __('comercial.lotes.nuevo') }}
                        </x-atoms.button>
                    @endpuede
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-lotes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('lote'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-lotes__aviso">
                    {{ $errors->first('lote') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
                    $propiedad->id => $propiedad->nombre,
                ]);
                $filtrosPanelActivos = collect(['cliente_id', 'propiedad_id'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $lotes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.lotes.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="cliente_id"
                            id="filtro-cliente"
                            :label="__('comercial.lotes.filtro_cliente')"
                            :options="$clientesDisponibles"
                            :value="$filtros['cliente_id']"
                            :placeholder="__('comercial.lotes.filtro_todos')"
                        />

                        <x-atoms.select
                            name="propiedad_id"
                            id="filtro-propiedad"
                            :label="__('comercial.lotes.filtro_propiedad')"
                            :options="$propiedadesOptions"
                            :value="$filtros['propiedad_id']"
                            :placeholder="__('comercial.lotes.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.lotes.index')"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.lotes.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($lotes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.lotes.filtro_vacio_titulo')"
                        :detail="__('comercial.lotes.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="grid_view"
                        :title="__('comercial.lotes.vacio_titulo')"
                        :detail="__('comercial.lotes.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem 1fr 2fr 2fr 1fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_codigo') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_propiedad') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_hectareas') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($lotes as $lote)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($lotes->currentPage() - 1) * $lotes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-lotes__codigo">{{ $lote->codigo }}</span>
                            <span role="cell" class="ag-lotes__propiedad">
                                @if ($lote->propiedad->color)
                                    <span class="ag-lotes__color" style="background-color: {{ $lote->propiedad->color }}" aria-hidden="true"></span>
                                @endif
                                {{ $lote->propiedad->nombre }}
                            </span>
                            <span role="cell">{{ $lote->propiedad->cliente->razon_social }}</span>
                            <span role="cell" class="ag-lotes__hectareas">{{ __('comercial.lotes.hectareas_valor', ['cantidad' => number_format((float) $lote->hectareas, 2, ',', '.')]) }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form FUERA de row-actions a propósito: ese organism repite su
                                     slot dos veces (visible/menú) — un <form> con id ahí adentro se
                                     duplicaría con el mismo id, HTML inválido. El botón de
                                     confirm-button lo envía por su atributo `form`. --}}
                                @puede('comercial.lote.eliminar')
                                    <form
                                        id="lote-eliminar-{{ $lote->id }}"
                                        method="POST"
                                        action="{{ route('panel.lotes.destroy', $lote) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('comercial.lote.editar')
                                        <x-atoms.button :href="route('panel.lotes.edit', $lote)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('comercial.lotes.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('comercial.lote.eliminar')
                                        <span class="ag-row-actions__item">
                                            <x-molecules.confirm-button
                                                :form-id="'lote-eliminar-' . $lote->id"
                                                :title="__('comercial.lotes.confirmar_eliminar_titulo')"
                                                :message="__('comercial.lotes.confirmar_baja')"
                                                :confirm-label="__('comercial.lotes.eliminar_accion')"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('comercial.lotes.eliminar_accion') }}
                                            </x-molecules.confirm-button>
                                        </span>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$lotes" :aria-label="__('comercial.lotes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
