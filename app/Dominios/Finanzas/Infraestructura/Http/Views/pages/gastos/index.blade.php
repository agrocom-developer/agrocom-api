{{--
    Page: gastos/index (GET /panel/gastos, panel.gastos.index)
    Listado de gastos (HU-33, tarea 47): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que anticipos/index.blade.php, con tres filtros
    (rubro, base, período) y sin acción de editar (invariante de esta tarea:
    un gasto es inmutable salvo baja, ver Aplicacion/CrearGasto).

    Datos esperados (ver GastosController::index()): la cáscara de
    CascaraPanel, más:
    - $gastos (LengthAwarePaginator<Gasto>): fecha descendente.
    - $etiquetasRubro / $etiquetasBase (array<int, string>): id => nombre.
    - $etiquetasTrabajo (array<int, string>): trabajo_id => etiqueta legible,
      solo de los trabajos presentes en la página actual (evita un JOIN en
      el listado — mismo criterio que AnticiposController::etiquetasPersona()).
    - $rubrosDisponibles / $basesDisponibles / $equiposDisponibles /
      $campaniasDisponibles (Collection<int, string>): para los <select> de
      filtro. `$campaniasDisponibles` (tarea 73) NO se filtra por estado
      (a diferencia de la del formulario de alta): una campaña `cerrada`
      sigue teniendo historial de gastos que filtrar.
    - $filtros (array{rubro_id, base_id, trabajo_id, equipo_trabajo_id,
      campania_id, periodo}): valores aplicados, para dejar los campos con
      el valor tras el submit.
    - $total (string|null): suma (`Brick\Math\BigDecimal`, nunca `SUM()` de
      SQL) de los gastos filtrados — solo se calcula/muestra cuando hay un
      equipo elegido (tarea 73, punto 5: "un total por equipo").
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.gasto.ver`, verificado server-side en el
    controlador. El botón "Nuevo gasto" y "Eliminar" se ocultan con `@puede`
    (presentación, no autorización — el servidor revalida en
    GastosController).

    Estilos en resources/css/pages/gastos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.gastos.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo')"
    >
        <div class="ag-gastos">
            <x-organisms.page-header
                :title="__('finanzas.gastos.titulo')"
                :subtitle="__('finanzas.gastos.subtitulo')"
            >
                @puede('finanzas.gasto.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.gastos.create')" variant="primary" icon="add">
                            {{ __('finanzas.gastos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-gastos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $gastos->isNotEmpty())
                <form method="GET" action="{{ route('panel.gastos.index') }}" class="ag-filtros ag-gastos__filtros">
                <x-atoms.select
                    name="rubro_id"
                    id="filtro-rubro"
                    :label="__('finanzas.gastos.filtro_rubro')"
                    :options="$rubrosDisponibles"
                    :value="(string) $filtros['rubro_id']"
                    :placeholder="__('finanzas.gastos.filtro_rubro_placeholder')"
                />

                <x-atoms.select
                    name="equipo_trabajo_id"
                    id="filtro-equipo"
                    :label="__('finanzas.gastos.filtro_equipo')"
                    :options="$equiposDisponibles"
                    :value="(string) $filtros['equipo_trabajo_id']"
                    :placeholder="__('finanzas.gastos.filtro_equipo_placeholder')"
                />

                <x-atoms.select
                    name="base_id"
                    id="filtro-base"
                    :label="__('finanzas.gastos.filtro_base')"
                    :options="$basesDisponibles"
                    :value="(string) $filtros['base_id']"
                    :placeholder="__('finanzas.gastos.filtro_base_placeholder')"
                />

                <x-atoms.select
                    name="campania_id"
                    id="filtro-campania"
                    :label="__('finanzas.gastos.filtro_campania')"
                    :options="$campaniasDisponibles"
                    :value="(string) $filtros['campania_id']"
                    :placeholder="__('finanzas.gastos.filtro_campania_placeholder')"
                />

                <div class="ag-input">
                    <label for="filtro-periodo" class="ag-input__label">{{ __('finanzas.gastos.filtro_periodo') }}</label>
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

                <div class="ag-filtros__acciones ag-gastos__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.gastos.filtrar') }}
                    </x-atoms.button>

                    @if ($hayFiltrosActivos)
                        <x-atoms.button :href="route('panel.gastos.index')" variant="text" size="md">
                            {{ __('finanzas.gastos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>
            @endif

            @if ($total !== null)
                <x-molecules.alert-strip variant="info" icon="functions" class="ag-gastos__aviso">
                    {{ __('finanzas.gastos.total_equipo', ['monto' => $total]) }}
                </x-molecules.alert-strip>
            @endif

            @if ($gastos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="receipt_long" class="ag-gastos__aviso">
                        {{ __('finanzas.gastos.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('finanzas.gastos.vacio_titulo')"
                        :detail="__('finanzas.gastos.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-gastos__tabla" role="table">
                    <div class="ag-gastos__head" role="row">
                        <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_imputacion') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_comprobante') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($gastos as $gasto)
                        <div class="ag-gastos__fila" role="row">
                            <span role="cell" class="ag-gastos__cifra">{{ $gasto->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasRubro[$gasto->rubro_id] ?? "#{$gasto->rubro_id}" }}</span>
                            <span role="cell">
                                @if ($gasto->equipo_trabajo_id !== null)
                                    {{ __('finanzas.gastos.imputacion_equipo', ['equipo' => $etiquetasEquipo[$gasto->equipo_trabajo_id] ?? "#{$gasto->equipo_trabajo_id}"]) }}
                                @elseif ($gasto->trabajo_id !== null)
                                    {{ $etiquetasTrabajo[$gasto->trabajo_id] ?? __('finanzas.gastos.imputacion_trabajo', ['id' => $gasto->trabajo_id]) }}
                                @elseif ($gasto->base_id !== null)
                                    {{ __('finanzas.gastos.imputacion_base', ['base' => $etiquetasBase[$gasto->base_id] ?? "#{$gasto->base_id}"]) }}
                                @else
                                    {{ __('finanzas.gastos.imputacion_general') }}
                                @endif
                            </span>
                            <span role="cell" class="ag-gastos__cifra">{{ __('finanzas.gastos.monto_valor', ['monto' => $gasto->monto]) }}</span>
                            <span role="cell">
                                @if ($gasto->comprobante_url !== null)
                                    <x-atoms.button :href="route('panel.gastos.comprobante', $gasto)" target="_blank" rel="noopener" variant="outline" size="sm" icon="description">
                                        {{ __('finanzas.gastos.comprobante_ver') }}
                                    </x-atoms.button>
                                @else
                                    <span class="ag-gastos__sin-comprobante">{{ __('finanzas.gastos.comprobante_sin') }}</span>
                                @endif
                            </span>

                            <span role="cell" class="ag-gastos__acciones">
                                @if ($puedeEliminar)
                                    <form
                                        method="POST"
                                        action="{{ route('panel.gastos.destroy', $gasto) }}"
                                        onsubmit="return confirm('{{ __('finanzas.gastos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('finanzas.gastos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($gastos->hasPages())
                    <nav class="ag-gastos__paginacion" aria-label="{{ __('finanzas.gastos.paginacion_aria') }}">
                        @if (! $gastos->onFirstPage())
                            <x-atoms.button :href="$gastos->previousPageUrl()" variant="outline" size="sm" icon="chevron_left">
                                {{ __('finanzas.gastos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-gastos__paginacion-info">
                            {{ __('finanzas.gastos.paginacion_info', ['actual' => $gastos->currentPage(), 'total' => $gastos->lastPage()]) }}
                        </span>

                        @if ($gastos->hasMorePages())
                            <x-atoms.button :href="$gastos->nextPageUrl()" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('finanzas.gastos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
