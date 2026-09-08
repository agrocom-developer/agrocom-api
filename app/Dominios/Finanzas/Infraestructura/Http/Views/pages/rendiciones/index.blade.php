{{--
    Page: rendiciones/index (GET /panel/rendiciones, panel.rendiciones.index)
    Listado de rendiciones (HU-34, tarea 48): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que gastos/index.blade.php y planillas/index.blade.php,
    con dos filtros (base, estado). Sin acción de editar ni eliminar
    (invariante: una rendición es inmutable salvo su estado).

    Datos esperados (ver RendicionesController::index()): la cáscara de
    CascaraPanel, más:
    - $rendiciones (LengthAwarePaginator<Rendicion>): paginado.
    - $etiquetasBase / $etiquetasJefeCampo (array<int, string>): id => nombre,
      solo de los registros presentes en la página actual.
    - $basesDisponibles (Collection<int, string>): para el <select> de filtro.
    - $filtros (array{base_id, estado}): valores aplicados, para dejar los
      campos con el valor tras el submit.
    - $puedeCrear (bool): gatea el botón "Nueva rendición".

    Gateada por `finanzas.rendicion.ver`, verificado server-side en el
    controlador. El botón "Nueva rendición" se oculta con `@puede`
    (presentación, no autorización).

    Estilos en resources/css/pages/rendiciones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.rendiciones.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('finanzas.rendiciones.titulo')"
    >
        <div class="ag-rendiciones">
            <x-organisms.page-header
                :title="__('finanzas.rendiciones.titulo')"
                :subtitle="__('finanzas.rendiciones.subtitulo')"
            >
                @puede('finanzas.rendicion.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.rendiciones.create') }}" variant="primary" icon="add">
                            {{ __('finanzas.rendiciones.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-rendiciones__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.rendiciones.index') }}" class="ag-filtros ag-rendiciones__filtros">
                <x-atoms.select
                    name="base_id"
                    id="filtro-base"
                    label="{{ __('finanzas.rendiciones.filtro_base') }}"
                    :options="$basesDisponibles"
                    :value="(string) $filtros['base_id']"
                    placeholder="{{ __('finanzas.rendiciones.filtro_base_placeholder') }}"
                />

                <x-atoms.select
                    name="estado"
                    id="filtro-estado"
                    label="{{ __('finanzas.rendiciones.filtro_estado') }}"
                    :options="collect(['abierta' => __('finanzas.rendiciones.estado.abierta'), 'presentada' => __('finanzas.rendiciones.estado.presentada'), 'aprobada' => __('finanzas.rendiciones.estado.aprobada')])"
                    value="{{ $filtros['estado'] }}"
                    placeholder="{{ __('finanzas.rendiciones.filtro_estado_placeholder') }}"
                />

                <div class="ag-filtros__acciones ag-rendiciones__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.rendiciones.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['base_id'] !== null || $filtros['estado'] !== '')
                        <x-atoms.button href="{{ route('panel.rendiciones.index') }}" variant="text" size="md">
                            {{ __('finanzas.rendiciones.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($rendiciones->isEmpty())
                <x-molecules.alert-strip variant="info" icon="receipt_long" class="ag-rendiciones__aviso">
                    {{ __(($filtros['base_id'] !== null || $filtros['estado'] !== '') ? 'finanzas.rendiciones.filtro_vacio' : 'finanzas.rendiciones.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-rendiciones__tabla" role="table">
                    <div class="ag-rendiciones__head" role="row">
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_base') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_jefe_campo') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($rendiciones as $rendicion)
                        <div class="ag-rendiciones__fila" role="row">
                            <span role="cell" class="ag-rendiciones__cifra">{{ $rendicion->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasBase[$rendicion->base_id] ?? "#{$rendicion->base_id}" }}</span>
                            <span role="cell">{{ $etiquetasJefeCampo[$rendicion->jefe_campo_id] ?? "#{$rendicion->jefe_campo_id}" }}</span>
                            <span role="cell" class="ag-rendiciones__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => $rendicion->monto]) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$rendicion->estado->value === 'abierta' ? 'secondary' : 'success'">
                                    {{ __("finanzas.rendiciones.estado.{$rendicion->estado->value}") }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-rendiciones__acciones">
                                <x-atoms.button href="{{ route('panel.rendiciones.show', $rendicion) }}" variant="outline" size="sm" icon="visibility">
                                    {{ __('finanzas.rendiciones.ver_accion') }}
                                </x-atoms.button>
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($rendiciones->hasPages())
                    <nav class="ag-rendiciones__paginacion" aria-label="{{ __('finanzas.rendiciones.paginacion_aria') }}">
                        @if (! $rendiciones->onFirstPage())
                            <x-atoms.button href="{{ $rendiciones->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('finanzas.rendiciones.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-rendiciones__paginacion-info">
                            {{ __('finanzas.rendiciones.paginacion_info', ['actual' => $rendiciones->currentPage(), 'total' => $rendiciones->lastPage()]) }}
                        </span>

                        @if ($rendiciones->hasMorePages())
                            <x-atoms.button href="{{ $rendiciones->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('finanzas.rendiciones.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
