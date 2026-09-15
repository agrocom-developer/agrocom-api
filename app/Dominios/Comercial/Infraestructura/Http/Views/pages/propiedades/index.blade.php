{{--
    Page: propiedades/index (GET /panel/propiedades, panel.propiedades.index)
    Listado de propiedades (ADR 0018): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que campos/index.blade.php (tarea 35), sin el
    conteo de lotes/hectáreas (eso sigue siendo de `Campo`).

    Datos esperados (ver PropiedadesController::index()): la cáscara de
    CascaraPanel, más:
    - $propiedades (LengthAwarePaginator<Propiedad>, con `cliente` cargada,
      `campos_count` precargado vía withCount): nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `comercial.propiedad.ver`, verificado server-side en el
    controlador. Los botones "Nueva propiedad"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    PropiedadesController).

    Estilos en resources/css/pages/propiedades.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.propiedades.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.titulo')"
    >
        <div class="ag-propiedades">
            <x-organisms.page-header
                :title="__('comercial.propiedades.titulo')"
                :subtitle="__('comercial.propiedades.subtitulo')"
            >
                @puede('comercial.propiedad.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.propiedades.create') }}" variant="primary" icon="add">
                            {{ __('comercial.propiedades.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-propiedades__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $propiedades->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        action="{{ route('panel.propiedades.index') }}"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.propiedades.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($propiedades->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="map" class="ag-propiedades__aviso">
                        {{ __('comercial.propiedades.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="map"
                        :title="__('comercial.propiedades.vacio_titulo')"
                        :detail="__('comercial.propiedades.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-propiedades__tabla" role="table">
                    <div class="ag-propiedades__head" role="row">
                        <span role="columnheader" class="ag-propiedades__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_nombre') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_campos') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($propiedades as $propiedad)
                        <div class="ag-propiedades__fila" role="row">
                            <span role="cell" class="ag-propiedades__indice">
                                {{ ($propiedades->currentPage() - 1) * $propiedades->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-propiedades__nombre">{{ $propiedad->nombre }}</span>
                            <span role="cell">{{ $propiedad->cliente->razon_social }}</span>
                            <span role="cell">{{ __('comercial.propiedades.campos_cantidad', ['cantidad' => $propiedad->campos_count]) }}</span>

                            <span role="cell" class="ag-propiedades__acciones">
                                @puede('comercial.propiedad.editar')
                                    <x-atoms.button href="{{ route('panel.propiedades.edit', $propiedad) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('comercial.propiedades.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.propiedad.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.propiedades.destroy', $propiedad) }}"
                                        onsubmit="return confirm('{{ __('comercial.propiedades.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('comercial.propiedades.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$propiedades" :aria-label="__('comercial.propiedades.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
