{{--
    Page: devengos/show (GET /panel/devengos/{persona}, panel.devengos.show)
    "Como piloto o auxiliar, quiero ver mis devengos por período" (HU-28,
    tarea 40). Tabla de solo lectura — sin alta/edición/baja, más cercana al
    panel de consulta que al arquetipo Listado con acciones (§6.2 de
    docs/diseno/guia_pantalla_panel.md, sin la columna de acciones ni el
    formulario de baja).

    Datos esperados (ver DevengosController::show()): la cáscara de
    CascaraPanel, más:
    - $personaId (int): la persona autenticada — DevengosController ya
      garantizó que coincide con el {persona} de la ruta (404 si no).
    - $devengos (Collection<DevengoPersonal>): del período filtrado, fecha
      ascendente.
    - $total (string decimal): suma exacta del período (Brick\Math\BigDecimal
      en ListarDevengosPersona, invariante 6 de CLAUDE.md).
    - $periodoFiltro (string, formato YYYY-MM): período efectivamente
      aplicado (el pedido, o el mes actual si no vino/era inválido).

    Gateada por `finanzas.devengo.ver`, verificado server-side en el
    controlador. Sin `@puede` acá: no hay acciones que ocultar, la pantalla
    entera ya está detrás del `abort_unless` del controlador.

    Estilos en resources/css/pages/devengos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.devengos.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.devengos.titulo')"
    >
        <div class="ag-devengos">
            <x-organisms.page-header
                :title="__('finanzas.devengos.titulo')"
                :subtitle="__('finanzas.devengos.subtitulo')"
            />

            <form method="GET" action="{{ route('panel.devengos.show', $personaId) }}" class="ag-filtros ag-devengos__filtros">
                <div class="ag-input">
                    <label for="filtro-periodo" class="ag-input__label">{{ __('finanzas.devengos.filtro_periodo') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="month"
                            name="periodo"
                            id="filtro-periodo"
                            class="ag-input__field"
                            value="{{ $periodoFiltro }}"
                        >
                    </div>
                </div>

                <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                    {{ __('finanzas.devengos.filtrar') }}
                </x-atoms.button>
            </form>

            @if ($devengos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="request_quote" class="ag-devengos__aviso">
                    {{ __('finanzas.devengos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-devengos__tabla" role="table">
                    <div class="ag-devengos__head" role="row">
                        <span role="columnheader">{{ __('finanzas.devengos.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.devengos.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('finanzas.devengos.col_tarifa') }}</span>
                        <span role="columnheader">{{ __('finanzas.devengos.col_monto') }}</span>
                    </div>

                    @foreach ($devengos as $devengo)
                        <div class="ag-devengos__fila" role="row">
                            <span role="cell">{{ $devengo->fecha->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-devengos__cifra">{{ $devengo->hectareas }}</span>
                            <span role="cell" class="ag-devengos__cifra">{{ __('finanzas.devengos.monto_valor', ['monto' => $devengo->tarifa_ha]) }}</span>
                            <span role="cell" class="ag-devengos__cifra">{{ __('finanzas.devengos.monto_valor', ['monto' => $devengo->monto]) }}</span>
                        </div>
                    @endforeach

                    <div class="ag-devengos__fila ag-devengos__fila--total" role="row">
                        <span role="cell" class="ag-devengos__total-etiqueta">{{ __('finanzas.devengos.total') }}</span>
                        <span role="cell" aria-hidden="true"></span>
                        <span role="cell" aria-hidden="true"></span>
                        <span role="cell" class="ag-devengos__cifra ag-devengos__total-valor">{{ __('finanzas.devengos.monto_valor', ['monto' => $total]) }}</span>
                    </div>
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
