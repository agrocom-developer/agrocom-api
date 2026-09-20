{{--
    Page: rendiciones/index (GET /panel/rendiciones, panel.rendiciones.index)
    Listado de rendiciones (HU-34, tarea 48): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar → tabla →
    paginación. Homogeneizado en la tarea 119 con el patrón de Contratos:
    franja de KPI, `filter-panel` (antes el `<form class="ag-filtros">`
    anterior), `index-table`, `row-actions` y `confirm-modal` con la ficha
    «estado actual → destino».

    Una rendición tiene máquina de estados (`TransicionesRendicion`:
    abierta → presentada → aprobada) pero NO ficha de edición: no lleva
    pasos. Lo que sí lleva es el badge del listado y las acciones de fila que
    la mueven de estado, todos con el tono de
    `RendicionesController::TONO_POR_ESTADO` — definido una sola vez y
    compartido con los modales (plan §3.1). Sin editar ni eliminar: una
    rendición es inmutable salvo su estado.

    Qué acción ofrece cada fila (las mismas condiciones que ya gateaban los
    botones del detalle, contra las mismas rutas):
    - «Presentar», solo si está `abierta` y tiene al menos un gasto asociado
      (`gastos_count`): sin gastos, `PresentarRendicion` la rechazaría.
    - «Aprobar», solo si está `presentada` y la persona del usuario NO es la
      que rindió (invariante de `PoliticaAprobacionRendicion`, por PERSONA).
      Acá se oculta; el servidor revalida y responde 403.

    Datos esperados (ver RendicionesController::index()): la cáscara de
    CascaraPanel, más:
    - $rendiciones (LengthAwarePaginator<Rendicion>, con `gastos_count`):
      fecha descendente.
    - $resumen (array{total, abiertas, presentadas, aprobadas}): las cifras de
      la franja de KPI, con el MISMO filtro que la tabla
      (ListarRendiciones::resumen). El monto es DECIMAL sumado con BigDecimal:
      la vista solo lo formatea (`FormatoMonto`, sin `float`), nunca calcula
      con él (invariante 6).
    - $etiquetasBase / $etiquetasJefeCampo (array<int, string>): id => nombre,
      solo de los registros presentes en la página actual.
    - $basesDisponibles (Collection<int, string>): para el <select> de filtro.
    - $filtros (array{base_id, estado}): valores aplicados, para dejar los
      campos con el valor tras el submit.
    - $tonoPorEstado (array<string, string>): estado → tono del badge.
    - $personaId (int|null): persona del usuario autenticado.
    - $puedeCrear / $puedePresentar / $puedeAprobar (bool): permisos del rol
      activo.

    Gateada por `finanzas.rendicion.ver`, verificado server-side en el
    controlador. El botón "Nueva rendición" y las acciones de estado se
    ocultan con `@puede`/`$puede*` (presentación, no autorización — el
    servidor revalida en RendicionesController).

    Estilos en resources/css/pages/rendiciones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
<x-templates.panel-shell :title="__('finanzas.rendiciones.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.titulo')"
    >
        <div class="ag-rendiciones">
            <x-organisms.page-header
                :title="__('finanzas.rendiciones.titulo')"
                :subtitle="__('finanzas.rendiciones.subtitulo')"
            >
                @puede('finanzas.rendicion.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.rendiciones.create')" variant="primary" icon="add">
                            {{ __('finanzas.rendiciones.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $filtrosActivos = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            {{-- KPI del listado (plan §3.6): responden al filtro aplicado, así que
                 el monto de arriba es la suma exacta de la columna de abajo. Una
                 cifra en cero va sin color. --}}
            @if ($filtrosActivos > 0 || $rendiciones->isNotEmpty())
                <div class="ag-rendiciones__kpis">
                    <x-molecules.stat-card
                        :label="__('finanzas.rendiciones.kpi_total')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['total'])"
                        :value-suffix="__('finanzas.rendiciones.unidad_moneda')"
                        :state="$resumen['total'] !== '0.00' ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.rendiciones.kpi_abiertas')"
                        icon="folder_open"
                        :value="$resumen['abiertas']"
                        :foot="__('finanzas.rendiciones.kpi_abiertas_pie')"
                        :state="$resumen['abiertas'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.rendiciones.kpi_presentadas')"
                        icon="fact_check"
                        :value="$resumen['presentadas']"
                        :foot="__('finanzas.rendiciones.kpi_presentadas_pie')"
                        :state="$resumen['presentadas'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.rendiciones.kpi_aprobadas')"
                        icon="task_alt"
                        :value="$resumen['aprobadas']"
                        :foot="__('finanzas.rendiciones.kpi_aprobadas_pie')"
                        :state="$resumen['aprobadas'] > 0 ? 'success' : null"
                    />
                </div>

                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.rendiciones.index')"
                        :active-count="$filtrosActivos"
                    >
                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('finanzas.rendiciones.filtro_base')"
                            :options="$basesDisponibles"
                            :value="(string) $filtros['base_id']"
                            :placeholder="__('finanzas.rendiciones.filtro_base_placeholder')"
                        />

                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('finanzas.rendiciones.filtro_estado')"
                            :options="collect([
                                'abierta' => __('finanzas.rendiciones.estado.abierta'),
                                'presentada' => __('finanzas.rendiciones.estado.presentada'),
                                'aprobada' => __('finanzas.rendiciones.estado.aprobada'),
                            ])"
                            :value="$filtros['estado']"
                            :placeholder="__('finanzas.rendiciones.filtro_estado_placeholder')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($rendiciones->isEmpty())
                @if ($filtrosActivos > 0)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.rendiciones.filtro_vacio_titulo')"
                        :detail="__('finanzas.rendiciones.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('finanzas.rendiciones.vacio_titulo')"
                        :detail="__('finanzas.rendiciones.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 0.9fr) minmax(0, 1.2fr) minmax(0, 1.3fr) minmax(0, 0.7fr) minmax(0, 1fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_base') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_jefe_campo') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.rendiciones.col_gastos') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.rendiciones.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.rendiciones.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($rendiciones as $rendicion)
                        @php
                            $estadoValor = $rendicion->estado->value;
                            $formIdPresentar = "rendicion-presentar-{$rendicion->id}";
                            $formIdAprobar = "rendicion-aprobar-{$rendicion->id}";
                            $modalIdPresentar = "rendicion-presentar-modal-{$rendicion->id}";
                            $modalIdAprobar = "rendicion-aprobar-modal-{$rendicion->id}";
                            $puedePresentarEsta = $puedePresentar
                                && $estadoValor === 'abierta'
                                && ($rendicion->gastos_count ?? 0) > 0;
                            $puedeAprobarEsta = $puedeAprobar
                                && $estadoValor === 'presentada'
                                && $personaId !== (int) $rendicion->jefe_campo_id;
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($rendiciones->currentPage() - 1) * $rendiciones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $rendicion->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasBase[$rendicion->base_id] ?? "#{$rendicion->base_id}" }}</span>
                            <span role="cell">{{ $etiquetasJefeCampo[$rendicion->jefe_campo_id] ?? "#{$rendicion->jefe_campo_id}" }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ $rendicion->gastos_count ?? 0 }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => FormatoMonto::decimal($rendicion->monto)]) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                                    {{ __('finanzas.rendiciones.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Forms y modales FUERA de row-actions a propósito: ese
                                     organism repite su slot dos veces (visible/menú, ver su
                                     docblock), así que un <form> o un modal con id ahí adentro
                                     se duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. Los disparadores sí van adentro (son
                                     botones sin id propio). Mismo criterio que contratos/index. --}}
                                @if ($puedePresentarEsta)
                                    <form id="{{ $formIdPresentar }}" method="POST" action="{{ route('panel.rendiciones.presentar', $rendicion) }}">
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdPresentar"
                                        :form-id="$formIdPresentar"
                                        :title="__('finanzas.rendiciones.confirmar_presentar_titulo')"
                                        :message="__('finanzas.rendiciones.confirmar_presentar')"
                                        :confirm-label="__('finanzas.rendiciones.presentar_accion')"
                                        :tone="$tonoPorEstado['presentada']"
                                        modal-icon="send"
                                    >
                                        @include('finanzas::pages.rendiciones._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'presentada'])
                                    </x-molecules.confirm-modal>
                                @endif

                                @if ($puedeAprobarEsta)
                                    <form id="{{ $formIdAprobar }}" method="POST" action="{{ route('panel.rendiciones.aprobar', $rendicion) }}">
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdAprobar"
                                        :form-id="$formIdAprobar"
                                        :title="__('finanzas.rendiciones.confirmar_aprobar_titulo')"
                                        :message="__('finanzas.rendiciones.confirmar_aprobar')"
                                        :confirm-label="__('finanzas.rendiciones.aprobar_accion')"
                                        :tone="$tonoPorEstado['aprobada']"
                                    >
                                        @include('finanzas::pages.rendiciones._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'aprobada'])
                                    </x-molecules.confirm-modal>
                                @endif

                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.rendiciones.show', $rendicion)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('finanzas.rendiciones.ver_accion') }}
                                    </x-atoms.button>

                                    @if ($puedePresentarEsta)
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdPresentar"
                                            variant="info-outline"
                                            size="sm"
                                            icon="send"
                                        >
                                            {{ __('finanzas.rendiciones.presentar_accion') }}
                                        </x-atoms.button>
                                    @endif

                                    @if ($puedeAprobarEsta)
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdAprobar"
                                            variant="success-outline"
                                            size="sm"
                                            icon="check_circle"
                                        >
                                            {{ __('finanzas.rendiciones.aprobar_accion') }}
                                        </x-atoms.button>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$rendiciones" :aria-label="__('finanzas.rendiciones.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
