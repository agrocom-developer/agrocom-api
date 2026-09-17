{{--
    Page: campanias/index (GET /panel/campanias, panel.campanias.index)
    Listado de campañas (ADR 0015 punto 1, tarea 69): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Migrado al patrón vigente el 15/9/2026 (mismo molde que
    `seguridad::pages.usuarios.index`, sin `filter-panel` porque el único
    filtro es la búsqueda): `empty-state` único para "sin datos"/"la
    búsqueda no trae nada" (nunca `alert-strip`) y `organisms/row-actions`
    en la celda de acciones. `comercial/contratos/index.blade.php` todavía
    no migró — no lo copies como referencia para una pantalla nueva.

    Datos esperados (ver CampaniasController::index()): la cáscara de
    CascaraPanel, más:
    - $campanias (LengthAwarePaginator<Campania>): fecha de inicio descendente.
    - $filtros (array{q: string}): filtros aplicados, para dejar los campos
      con el valor tras el submit.

    Sin filtro ni columna de cliente desde la corrección del 15/9/2026: la
    campaña es un catálogo compartido, no de un cliente.

    Gateada por `campania.campania.ver`, verificado server-side en el
    controlador. El botón "Nueva campaña" y las acciones de cambio de estado
    se ocultan con `@puede` (presentación, no autorización — el servidor
    revalida en CampaniasController). Las acciones de cambio de estado
    disponibles dependen del estado ACTUAL de cada fila: una campaña
    `planificada` solo ofrece "Abrir"; una `abierta`, "Cerrar"; una `cerrada`,
    ninguna (es terminal) — ver `TransicionesCampania`, que es la fuente real
    de esta regla; acá solo se refleja para no ofrecer un botón que el
    servidor va a rechazar. `.cambiar_estado` es exclusivo del rol `dueno`.

    Estilos en resources/css/pages/campanias.css — cero color hardcodeado
    (CLAUDE.md invariante 11).

    Columna "Actividad" (HU-77, tarea 93): "Activa"/"Inactiva" derivada de
    `Campania::esActiva()`, presentación pura — no reemplaza a "Estado", que
    sigue mostrando los tres valores reales de la máquina de estados.
--}}
<x-templates.panel-shell :title="__('campania.campanias.titulo')" :tema="$tema">
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
        :vista-actual="__('campania.campanias.titulo')"
    >
        <div class="ag-campanias">
            <x-organisms.page-header
                :title="__('campania.campanias.titulo')"
                :subtitle="__('campania.campanias.subtitulo')"
            >
                @puede('campania.campania.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.campanias.create')" variant="primary" icon="add">
                            {{ __('campania.campanias.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-campanias__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-campanias__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $campanias->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.campanias.index')"
                        :value="$filtros['q']"
                        :placeholder="__('campania.campanias.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($campanias->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('campania.campanias.filtro_vacio_titulo')"
                        :detail="__('campania.campanias.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="calendar_month"
                        :title="__('campania.campanias.vacio_titulo')"
                        :detail="__('campania.campanias.vacio_detalle')"
                    />
                @endif
            @else
                {{-- SIETE columnas, una por celda: índice · código · nombre · vigencia ·
                     estado · actividad · acciones (sin columna de cliente desde la
                     corrección del 15/9/2026, ADR 0015 — la campaña es catálogo
                     compartido). Nacieron cinco (tarea 69) y la fila siempre tuvo seis
                     celdas, así que la de acciones no entraba en el track explícito y el
                     grid le abría una FILA IMPLÍCITA: "Editar" y "Cerrar" aparecían debajo
                     del nombre del cliente, en la primera columna, con la fila al doble de
                     alto. Reportado por el dueño sobre el panel andando (9/9/2026).

                     Los tracks de dato van en `fr`, ninguno en `auto`: el encabezado y las
                     filas de `molecules/index-table` son grids HERMANOS, no uno solo, así
                     que un track `auto` lo resuelve cada grid contra su propio contenido —
                     con "VIGENCIA" arriba y una fecha abajo, la columna medía distinto en
                     cada fila y los rótulos quedaban corridos respecto de los datos. Con
                     `fr` los dos grids reparten idéntico.

                     La columna de acciones es la excepción: ancho fijo (§6.2 de
                     docs/diseno/guia_pantalla_panel.md), `--ag-row-actions-width` en vez de
                     un valor en rem propio — ver el comentario de
                     resources/css/components/row-actions.css. Con `fr`, el ancho de
                     `row-actions` dependería del resto de columnas y no calzaría entre el
                     head y la fila, que son grids separados. --}}
                <x-molecules.index-table columns="3rem 0.9fr 1.6fr 1.4fr 0.8fr 0.8fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_codigo') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_nombre') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_estado') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_actividad') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($campanias as $campania)
                        @php
                            $variantePorEstado = [
                                'planificada' => 'neutral',
                                'abierta' => 'success',
                                'cerrada' => 'info',
                            ];
                            $estadoValor = $campania->estado->value;
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($campanias->currentPage() - 1) * $campanias->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-campanias__codigo">{{ $campania->codigo }}</span>
                            <span role="cell">{{ $campania->nombre ?? '—' }}</span>
                            <span role="cell" class="ag-campanias__mono">
                                {{ __('campania.campanias.vigencia', ['inicio' => $campania->fecha_inicio->format('d/m/Y'), 'fin' => $campania->fecha_fin->format('d/m/Y')]) }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                    {{ __('campania.campania.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$campania->esActiva() ? 'success' : 'neutral'">
                                    {{ __($campania->esActiva() ? 'campania.campanias.actividad_activa' : 'campania.campanias.actividad_inactiva') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdAbrir = "campania-abrir-{$campania->id}";
                                    $formIdCerrar = "campania-cerrar-{$campania->id}";
                                @endphp

                                {{-- Forms FUERA de row-actions a propósito: ese organism repite su
                                     slot dos veces (visible/menú, ver su docblock) — un <form> ahí
                                     adentro se duplicaría con el mismo id, HTML inválido. El botón
                                     que sí puede duplicarse (confirm-button) envía este form por su
                                     atributo `form`, sin importar dónde viva en el documento. --}}
                                @puede('campania.campania.cambiar_estado')
                                    @if ($estadoValor === 'planificada')
                                        <form id="{{ $formIdAbrir }}" method="POST" action="{{ route('panel.campanias.cambiar-estado', $campania) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="abierta">
                                        </form>
                                    @elseif ($estadoValor === 'abierta')
                                        <form id="{{ $formIdCerrar }}" method="POST" action="{{ route('panel.campanias.cambiar-estado', $campania) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="cerrada">
                                        </form>
                                    @endif
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('campania.campania.editar')
                                        <x-atoms.button :href="route('panel.campanias.edit', $campania)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('campania.campanias.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('campania.campania.cambiar_estado')
                                        @if ($estadoValor === 'planificada')
                                            <x-molecules.confirm-button
                                                :form-id="$formIdAbrir"
                                                :title="__('campania.campanias.confirmar_abrir_titulo')"
                                                :message="__('campania.campanias.confirmar_abrir')"
                                                :confirm-label="__('campania.campanias.accion_abrir')"
                                                tone="success"
                                                variant="outline"
                                                size="sm"
                                                icon="check_circle"
                                            >
                                                {{ __('campania.campanias.accion_abrir') }}
                                            </x-molecules.confirm-button>
                                        @elseif ($estadoValor === 'abierta')
                                            <x-molecules.confirm-button
                                                :form-id="$formIdCerrar"
                                                :title="__('campania.campanias.confirmar_cerrar_titulo')"
                                                :message="__('campania.campanias.confirmar_cerrar')"
                                                :confirm-label="__('campania.campanias.accion_cerrar')"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="lock"
                                            >
                                                {{ __('campania.campanias.accion_cerrar') }}
                                            </x-molecules.confirm-button>
                                        @endif
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$campanias" :aria-label="__('campania.campanias.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
