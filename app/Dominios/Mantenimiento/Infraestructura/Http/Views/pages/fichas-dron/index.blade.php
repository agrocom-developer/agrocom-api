{{--
    Page: fichas-dron/index (GET /panel/fichas-dron, panel.fichas-dron.index)
    Listado de fichas de inventario de dron (HU-82, tarea 97): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros
    → tabla → paginación. Mismo molde que baterias/index.blade.php (tarea
    51), sin columna de estado ni alerta: es un ABM plano.

    Datos esperados (ver FichasDronController::index()): la cáscara de
    CascaraPanel, más:
    - $fichas (LengthAwarePaginator<FichaDron>): identificador_dron
      ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con su valor tras el submit.

    Gateada por `mantenimiento.ficha_dron.ver`, verificado server-side en el
    controlador. Los botones "Nueva ficha"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    FichasDronController).

    Estilos en resources/css/pages/fichas-dron.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('mantenimiento.fichas_dron.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.fichas_dron.titulo')"
    >
        <div class="ag-fichas-dron">
            <x-organisms.page-header
                :title="__('mantenimiento.fichas_dron.titulo')"
                :subtitle="__('mantenimiento.fichas_dron.subtitulo')"
            >
                @puede('mantenimiento.ficha_dron.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.fichas-dron.create') }}" variant="primary" icon="add">
                            {{ __('mantenimiento.fichas_dron.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-fichas-dron__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $fichas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        action="{{ route('panel.fichas-dron.index') }}"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.fichas_dron.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($fichas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="memory" class="ag-fichas-dron__aviso">
                        {{ __('mantenimiento.fichas_dron.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="memory"
                        :title="__('mantenimiento.fichas_dron.vacio_titulo')"
                        :detail="__('mantenimiento.fichas_dron.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-fichas-dron__tabla" role="table">
                    <div class="ag-fichas-dron__head" role="row">
                        <span role="columnheader" class="ag-fichas-dron__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_numero_serie') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_chasis') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_version_software') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_region') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_accesorios') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($fichas as $ficha)
                        <div class="ag-fichas-dron__fila" role="row">
                            <span role="cell" class="ag-fichas-dron__indice">
                                {{ ($fichas->currentPage() - 1) * $fichas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-fichas-dron__identificador">{{ $ficha->identificador_dron }}</span>
                            <span role="cell">{{ $ficha->numero_serie ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell">{{ $ficha->chasis ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell">{{ $ficha->version_software ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell">{{ $ficha->region ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell" class="ag-fichas-dron__accesorios">
                                @if (! $ficha->tiene_cargador_control && ! $ficha->tiene_modem && ! $ficha->tiene_maletin)
                                    {{ __('mantenimiento.fichas_dron.sin_accesorios') }}
                                @endif
                                @if ($ficha->tiene_cargador_control)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_cargador_control') }}</x-atoms.badge>
                                @endif
                                @if ($ficha->tiene_modem)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_modem') }}</x-atoms.badge>
                                @endif
                                @if ($ficha->tiene_maletin)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_maletin') }}</x-atoms.badge>
                                @endif
                            </span>

                            <span role="cell" class="ag-fichas-dron__acciones">
                                @puede('mantenimiento.ficha_dron.editar')
                                    <x-atoms.button href="{{ route('panel.fichas-dron.edit', $ficha) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('mantenimiento.fichas_dron.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('mantenimiento.ficha_dron.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.fichas-dron.destroy', $ficha) }}"
                                        onsubmit="return confirm('{{ __('mantenimiento.fichas_dron.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('mantenimiento.fichas_dron.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$fichas" :aria-label="__('mantenimiento.fichas_dron.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
