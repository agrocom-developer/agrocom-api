{{--
    Page: planillas/index (GET /panel/planillas, panel.planillas.index)
    Listado de planillas del período (HU-30, tarea 44): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → formulario de
    alta (generar) → tabla → paginación. Mismo molde que anticipos/index.blade.php,
    sin filtros (`ListarPlanillas` no los tiene) y sin acción de eliminar
    (una planilla no se borra desde el panel).

    Datos esperados (ver PlanillasController::index()): la cáscara de
    CascaraPanel, más:
    - $planillas (LengthAwarePaginator<Planilla>): período descendente.
    - $puedeGenerar (bool): gatea el formulario "Generar planilla".

    Gateada por `finanzas.planilla.ver`, verificado server-side en el
    controlador.

    Estilos en resources/css/pages/planilla.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.planillas.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.planillas.titulo')"
    >
        <div class="ag-planillas">
            <x-organisms.page-header
                :title="__('finanzas.planillas.titulo')"
                :subtitle="__('finanzas.planillas.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-planillas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($puedeGenerar)
                <form method="POST" action="{{ route('panel.planillas.store') }}" class="ag-planillas__generar">
                    @csrf
                    <div class="ag-planillas__generar-texto">
                        <p class="ag-planillas__generar-titulo">{{ __('finanzas.planillas.generar_titulo') }}</p>
                        <p class="ag-planillas__generar-ayuda">{{ __('finanzas.planillas.generar_ayuda') }}</p>
                    </div>

                    <div class="ag-input">
                        <label for="planilla-periodo" class="ag-input__label">{{ __('finanzas.planillas.campo_periodo') }}</label>
                        <div class="ag-input__control">
                            <input type="month" name="periodo" id="planilla-periodo" class="ag-input__field" value="{{ old('periodo') }}" required>
                        </div>
                        @error('periodo')
                            <p class="ag-input__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-atoms.button type="submit" variant="primary" icon="playlist_add_check">
                        {{ __('finanzas.planillas.generar_boton') }}
                    </x-atoms.button>
                </form>
            @endif

            @if ($planillas->isEmpty())
                <x-molecules.alert-strip variant="info" icon="event_note" class="ag-planillas__aviso">
                    {{ __('finanzas.planillas.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-planillas__tabla" role="table">
                    <div class="ag-planillas__head" role="row">
                        <span role="columnheader">{{ __('finanzas.planillas.col_periodo') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_estado') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_total') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($planillas as $planilla)
                        <div class="ag-planillas__fila" role="row">
                            <span role="cell" class="ag-planillas__periodo">{{ $planilla->periodo }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$planilla->estado->value === 'aprobada' ? 'success' : 'warning'">
                                    {{ __("finanzas.planillas.estado.{$planilla->estado->value}") }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-planillas__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => $planilla->total]) }}</span>
                            <span role="cell" class="ag-planillas__acciones">
                                <x-atoms.button href="{{ route('panel.planillas.show', $planilla) }}" variant="outline" size="sm" icon="visibility">
                                    {{ __('finanzas.planillas.ver_accion') }}
                                </x-atoms.button>
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($planillas->hasPages())
                    <nav class="ag-planillas__paginacion" aria-label="{{ __('finanzas.planillas.paginacion_aria') }}">
                        @if (! $planillas->onFirstPage())
                            <x-atoms.button href="{{ $planillas->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('finanzas.planillas.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-planillas__paginacion-info">
                            {{ __('finanzas.planillas.paginacion_info', ['actual' => $planillas->currentPage(), 'total' => $planillas->lastPage()]) }}
                        </span>

                        @if ($planillas->hasMorePages())
                            <x-atoms.button href="{{ $planillas->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('finanzas.planillas.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
