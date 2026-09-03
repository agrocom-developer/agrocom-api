{{--
    Page: anticipos/index (GET /panel/anticipos, panel.anticipos.index)
    Listado de anticipos (HU-29, tarea 41): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que bases/index.blade.php, con dos filtros en vez
    de uno (persona y período) y sin acción de editar (invariante de esta
    tarea: un anticipo es inmutable salvo baja).

    Datos esperados (ver AnticiposController::index()): la cáscara de
    CascaraPanel, más:
    - $anticipos (LengthAwarePaginator<Anticipo>): fecha descendente.
    - $etiquetasPersona (array<int, string>): persona_id => nombre, solo de
      las personas presentes en la página actual (evita un JOIN en el
      listado — mismo criterio que OrdenesController::etiquetasContrato()).
    - $personasDisponibles (Collection<int, string>): id => nombre, para el
      <select> del filtro.
    - $filtros (array{persona_id: int|null, periodo: string}): valores
      aplicados, para dejar los campos con el valor tras el submit.
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.anticipo.ver`, verificado server-side en el
    controlador. El botón "Nuevo anticipo" y "Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    AnticiposController).

    Estilos en resources/css/pages/anticipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.anticipos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('finanzas.anticipos.titulo')"
    >
        <div class="ag-anticipos">
            <x-organisms.page-header
                :title="__('finanzas.anticipos.titulo')"
                :subtitle="__('finanzas.anticipos.subtitulo')"
            >
                @puede('finanzas.anticipo.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.anticipos.create') }}" variant="primary" icon="add">
                            {{ __('finanzas.anticipos.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-anticipos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.anticipos.index') }}" class="ag-anticipos__filtros">
                <div class="ag-input">
                    <label for="filtro-persona" class="ag-input__label">{{ __('finanzas.anticipos.filtro_persona') }}</label>
                    <div class="ag-input__control">
                        <select name="persona_id" id="filtro-persona" class="ag-input__field">
                            <option value="">{{ __('finanzas.anticipos.filtro_persona_placeholder') }}</option>
                            @foreach ($personasDisponibles as $id => $nombre)
                                <option value="{{ $id }}" @selected((string) $filtros['persona_id'] === (string) $id)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-periodo" class="ag-input__label">{{ __('finanzas.anticipos.filtro_periodo') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="month"
                            name="periodo"
                            id="filtro-periodo"
                            class="ag-input__field"
                            value="{{ $filtros['periodo'] }}"
                        >
                    </div>
                </div>

                <div class="ag-anticipos__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.anticipos.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['persona_id'] !== null || $filtros['periodo'] !== '')
                        <x-atoms.button href="{{ route('panel.anticipos.index') }}" variant="text" size="md">
                            {{ __('finanzas.anticipos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($anticipos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="payments" class="ag-anticipos__aviso">
                    {{ __(($filtros['persona_id'] !== null || $filtros['periodo'] !== '') ? 'finanzas.anticipos.filtro_vacio' : 'finanzas.anticipos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-anticipos__tabla" role="table">
                    <div class="ag-anticipos__head" role="row">
                        <span role="columnheader">{{ __('finanzas.anticipos.col_persona') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_motivo') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($anticipos as $anticipo)
                        <div class="ag-anticipos__fila" role="row">
                            <span role="cell" class="ag-anticipos__persona">{{ $etiquetasPersona[$anticipo->persona_id] ?? "#{$anticipo->persona_id}" }}</span>
                            <span role="cell" class="ag-anticipos__cifra">{{ $anticipo->fecha->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-anticipos__cifra">{{ __('finanzas.anticipos.monto_valor', ['monto' => $anticipo->monto]) }}</span>
                            <span role="cell">{{ $anticipo->motivo ?? __('finanzas.anticipos.sin_motivo') }}</span>

                            <span role="cell" class="ag-anticipos__acciones">
                                @if ($puedeEliminar)
                                    <form
                                        method="POST"
                                        action="{{ route('panel.anticipos.destroy', $anticipo) }}"
                                        onsubmit="return confirm('{{ __('finanzas.anticipos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('finanzas.anticipos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($anticipos->hasPages())
                    <nav class="ag-anticipos__paginacion" aria-label="{{ __('finanzas.anticipos.paginacion_aria') }}">
                        @if (! $anticipos->onFirstPage())
                            <x-atoms.button href="{{ $anticipos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('finanzas.anticipos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-anticipos__paginacion-info">
                            {{ __('finanzas.anticipos.paginacion_info', ['actual' => $anticipos->currentPage(), 'total' => $anticipos->lastPage()]) }}
                        </span>

                        @if ($anticipos->hasMorePages())
                            <x-atoms.button href="{{ $anticipos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('finanzas.anticipos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
