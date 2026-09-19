{{--
    Page: contratos/index (GET /panel/contratos, panel.contratos.index)
    Listado de contratos (HU-23, tarea 34): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Segundo ABM del panel, mismo molde que `clientes/index.blade.php`
    (tarea 33) con una máquina de estados encima.

    Datos esperados (ver ContratosController::index()): la cáscara de
    CascaraPanel, más:
    - $contratos (LengthAwarePaginator<Contrato>, con `cliente` precargada):
      fecha de inicio descendente. Sin columna "Ventanas" (retirada el
      16/9/2026 junto con `com_contrato_ventanas`: el horario para fumigar
      pasó a ser un dato de cada lote, no del contrato — ver el detalle por
      lote en la ficha de edición, `_formulario.blade.php`; una vista
      agregada a nivel de fila del listado queda pendiente de una tarea de
      diseño de UI aparte).
    - $campaniasDisponibles (Collection<int, string>): id => código, para el
      <select> del filtro por campaña (ADR 0015 punto 1).
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      para el <select> del filtro por cliente (tarea
      "listado-contratos-acciones", 16/9/2026).
    - $propiedadesDisponibles (Collection<int, string>): id => nombre, para
      el <select> del filtro por propiedad — filtra contratos que tienen al
      menos un lote de esa propiedad (misma tarea).
    - $filtros (array{q: string, campania_id: int|null, cliente_id: int|null,
      propiedad_id: int|null}): filtros aplicados, para dejar los campos con
      el valor tras el submit.

    Gateada por `comercial.contrato.ver`, verificado server-side en el
    controlador. El botón "Nuevo contrato" y las acciones de cambio de
    estado se ocultan con `@puede` (presentación, no autorización — el
    servidor revalida en ContratosController). Las acciones de cambio de
    estado disponibles dependen del estado ACTUAL de cada fila: un contrato
    `borrador` solo ofrece "Aprobar" o "Cancelar"; uno `conflicto` (comparte
    un lote con un contrato ya aprobado, ADR 0021), solo "Cancelar": no se
    aprueba hasta que el choque desaparezca, y sale solo de ahí; uno
    `vigente`, "Finalizar", "Pausar" o "Cancelar"; uno `pausado`,
    "Reanudar"; uno `finalizado`/`cancelado`, ninguna (son terminales) —
    ver `TransicionesContrato`, que
    es la fuente real de esta regla; acá solo se refleja para no ofrecer un
    botón que el servidor va a rechazar.

    Columna de acciones (tarea "listado-contratos-acciones", 16/9/2026,
    replica el patrón de `usuarios/index.blade.php`): `organisms/row-actions`
    en vez de botones sueltos — colapsa a un menú "⋮" las que no entran en
    la fila (nunca más de 2 sueltas, ver su docblock). Los `<form>` Y los
    `molecules/confirm-modal` de cambio de estado viven FUERA de
    `row-actions` (ese organism repite su slot dos veces — un `<form>` o un
    `<div class="modal">` con id ahí adentro se duplicaría, HTML inválido, y
    si la acción caía en el overflow del "⋮" Bootstrap abría el PRIMER id
    del documento, que quedaba oculto por `:nth-child` en la mitad visible:
    pantalla oscura sin modal — bug real, encontrado 17/9/2026 en vivo en
    `campanias/index.blade.php`, mismo síntoma que ya había obligado al
    wrapper `ag-row-actions__item` un día antes). Dentro de `row-actions`
    solo quedan botones disparadores normales (`data-bs-toggle="modal"
    data-bs-target="#..."`), que se duplican sin problema porque no tienen
    id propio — tono por destino: `success` a vigente (aprobar/reanudar),
    `info` a finalizado, `warning` a pausado, `danger` a cancelado.

    Estilos en resources/css/pages/contratos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.contratos.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.contratos.titulo')"
    >
        <div class="ag-contratos">
            <x-organisms.page-header
                :title="__('comercial.contratos.titulo')"
                :subtitle="__('comercial.contratos.subtitulo')"
            >
                @puede('comercial.contrato.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.contratos.create')" variant="primary" icon="add">
                            {{ __('comercial.contratos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-contratos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-contratos__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['campania_id', 'cliente_id', 'propiedad_id'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $contratos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.contratos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="campania_id"
                            id="filtro-campania"
                            :label="__('comercial.contratos.filtro_campania')"
                            :options="$campaniasDisponibles"
                            :value="$filtros['campania_id']"
                            :placeholder="__('comercial.contratos.filtro_campania_placeholder')"
                        />

                        <x-atoms.select
                            name="cliente_id"
                            id="filtro-cliente"
                            :label="__('comercial.contratos.filtro_cliente')"
                            :options="$clientesDisponibles"
                            :value="$filtros['cliente_id']"
                            :placeholder="__('comercial.contratos.filtro_cliente_placeholder')"
                        />

                        <x-atoms.select
                            name="propiedad_id"
                            id="filtro-propiedad"
                            :label="__('comercial.contratos.filtro_propiedad')"
                            :options="$propiedadesDisponibles"
                            :value="$filtros['propiedad_id']"
                            :placeholder="__('comercial.contratos.filtro_propiedad_placeholder')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.contratos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.contratos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($contratos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.contratos.filtro_vacio_titulo')"
                        :detail="__('comercial.contratos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="description"
                        :title="__('comercial.contratos.vacio_titulo')"
                        :detail="__('comercial.contratos.vacio_detalle')"
                    />
                @endif
            @else
                {{-- La última columna (acciones) es un ancho fijo, no `auto` — mismo
                     motivo que usuarios.css: con `auto`, el head (rótulo "Acciones") y
                     la fila (organisms/row-actions) de `molecules/index-table` son
                     grids separados que resuelven ese ancho cada uno por su cuenta y
                     quedan corridos. `--ag-row-actions-width`, no un valor en rem
                     propio: ver el comentario de resources/css/components/row-actions.css
                     (tarea "listado-contratos-acciones", 16/9/2026). --}}
                <x-molecules.index-table columns="3rem 2fr 1fr 1fr 1.4fr 1fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_monto_total') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($contratos as $contrato)
                        @php
                            // 18/9/2026: se probó "borrador" ("En Aprobación")
                            // en primary-2 (azul) y el usuario pidió revertir
                            // — ese estado se queda en "neutral" (badge gris
                            // secondary de siempre), a diferencia de
                            // vigente/finalizado/cancelado/pausado, que sí
                            // usan un color propio de la paleta de 8.
                            // Misma vuelta, pedido siguiente: "pausado" pasa
                            // de warning a info, "finalizado" de info a
                            // distintivo-2 ("purple"/magenta) — reasignación
                            // explícita del usuario, no una corrección de bug.
                            $variantePorEstado = [
                                'borrador' => 'neutral',
                                'vigente' => 'success',
                                'finalizado' => 'distintivo-2',
                                'cancelado' => 'danger',
                                'pausado' => 'info',
                                'conflicto' => 'alert',
                            ];
                            $estadoValor = $contrato->estado->value;
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($contratos->currentPage() - 1) * $contratos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-contratos__cliente">{{ $contrato->cliente->razon_social }}</span>
                            <span role="cell" class="ag-contratos__mono">{{ number_format((float) $contrato->hectareas_contratadas, 2, ',', '.') }}</span>
                            <span role="cell" class="ag-contratos__mono">{{ number_format((float) $contrato->monto_total, 2, ',', '.') }}</span>
                            <span role="cell" class="ag-contratos__mono">
                                @if ($contrato->fecha_fin)
                                    {{ __('comercial.contratos.vigencia_con_fin', ['inicio' => $contrato->fecha_inicio->format('d/m/Y'), 'fin' => $contrato->fecha_fin->format('d/m/Y')]) }}
                                @else
                                    {{ __('comercial.contratos.vigencia_sin_fin', ['inicio' => $contrato->fecha_inicio->format('d/m/Y')]) }}
                                @endif
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                    {{ __('comercial.contrato.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdAprobar = "contrato-aprobar-{$contrato->id}";
                                    $formIdFinalizar = "contrato-finalizar-{$contrato->id}";
                                    $formIdPausar = "contrato-pausar-{$contrato->id}";
                                    $formIdReanudar = "contrato-reanudar-{$contrato->id}";
                                    $formIdCancelar = "contrato-cancelar-{$contrato->id}";
                                    $modalIdAprobar = "contrato-aprobar-modal-{$contrato->id}";
                                    $modalIdFinalizar = "contrato-finalizar-modal-{$contrato->id}";
                                    $modalIdPausar = "contrato-pausar-modal-{$contrato->id}";
                                    $modalIdReanudar = "contrato-reanudar-modal-{$contrato->id}";
                                    $modalIdCancelar = "contrato-cancelar-modal-{$contrato->id}";
                                @endphp

                                {{-- Forms FUERA de row-actions a propósito (mismo motivo que
                                     campanias/index.blade.php): ese organism repite su slot dos
                                     veces (visible/menú, ver su docblock) — un <form> ahí adentro
                                     se duplicaría con el mismo id, HTML inválido. Los botones que
                                     sí pueden duplicarse envían estos forms por su atributo HTML
                                     `form`, sin importar dónde vivan en el documento. --}}
                                @puede('comercial.contrato.cambiar_estado')
                                    @if ($estadoValor === 'borrador')
                                        <form id="{{ $formIdAprobar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="vigente">
                                        </form>

                                        <form id="{{ $formIdCancelar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="cancelado">
                                        </form>
                                    @elseif ($estadoValor === 'vigente')
                                        <form id="{{ $formIdFinalizar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="finalizado">
                                        </form>

                                        <form id="{{ $formIdPausar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="pausado">
                                        </form>

                                        <form id="{{ $formIdCancelar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="cancelado">
                                        </form>
                                    @elseif ($estadoValor === 'conflicto')
                                        <form id="{{ $formIdCancelar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="cancelado">
                                        </form>
                                    @elseif ($estadoValor === 'pausado')
                                        <form id="{{ $formIdReanudar }}" method="POST" action="{{ route('panel.contratos.cambiar-estado', $contrato) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="vigente">
                                        </form>
                                    @endif
                                @endpuede

                                {{-- Modales FUERA de row-actions, mismo motivo que los forms de
                                     arriba: `confirm-button` metía su <div class="modal"> como hijo
                                     del slot que row-actions duplica dos veces — mismo id repetido
                                     (HTML inválido) y, si la acción caía en el overflow del menú
                                     "⋮", Bootstrap resolvía el PRIMER id del documento (el que
                                     quedaba oculto por :nth-child en la mitad visible): pantalla
                                     oscura sin modal (bug real, 17/9/2026, encontrado en vivo en
                                     campanias/index.blade.php). Los triggers viven adentro de
                                     row-actions (botones normales, se duplican sin problema); el
                                     modal, una sola vez, acá afuera. --}}
                                @puede('comercial.contrato.cambiar_estado')
                                    @if ($estadoValor === 'borrador')
                                        <x-molecules.confirm-modal
                                            :id="$modalIdAprobar"
                                            :form-id="$formIdAprobar"
                                            :title="__('comercial.contratos.confirmar_aprobar_titulo')"
                                            :message="__('comercial.contratos.confirmar_aprobar')"
                                            :confirm-label="__('comercial.contratos.accion_aprobar')"
                                            tone="success"
                                        />

                                        <x-molecules.confirm-modal
                                            :id="$modalIdCancelar"
                                            :form-id="$formIdCancelar"
                                            :title="__('comercial.contratos.confirmar_cancelar_titulo')"
                                            :message="__('comercial.contratos.confirmar_cancelar')"
                                            :confirm-label="__('comercial.contratos.accion_cancelar')"
                                            :cancel-label="__('ui.action.close')"
                                            tone="danger"
                                        />
                                    @elseif ($estadoValor === 'vigente')
                                        <x-molecules.confirm-modal
                                            :id="$modalIdFinalizar"
                                            :form-id="$formIdFinalizar"
                                            :title="__('comercial.contratos.confirmar_finalizar_titulo')"
                                            :message="__('comercial.contratos.confirmar_finalizar')"
                                            :confirm-label="__('comercial.contratos.accion_finalizar')"
                                            tone="info"
                                        />

                                        <x-molecules.confirm-modal
                                            :id="$modalIdPausar"
                                            :form-id="$formIdPausar"
                                            :title="__('comercial.contratos.confirmar_pausar_titulo')"
                                            :message="__('comercial.contratos.confirmar_pausar')"
                                            :confirm-label="__('comercial.contratos.accion_pausar')"
                                            tone="warning"
                                        />

                                        <x-molecules.confirm-modal
                                            :id="$modalIdCancelar"
                                            :form-id="$formIdCancelar"
                                            :title="__('comercial.contratos.confirmar_cancelar_titulo')"
                                            :message="__('comercial.contratos.confirmar_cancelar')"
                                            :confirm-label="__('comercial.contratos.accion_cancelar')"
                                            :cancel-label="__('ui.action.close')"
                                            tone="danger"
                                        />
                                    @elseif ($estadoValor === 'conflicto')
                                        <x-molecules.confirm-modal
                                            :id="$modalIdCancelar"
                                            :form-id="$formIdCancelar"
                                            :title="__('comercial.contratos.confirmar_cancelar_titulo')"
                                            :message="__('comercial.contratos.confirmar_cancelar')"
                                            :confirm-label="__('comercial.contratos.accion_cancelar')"
                                            :cancel-label="__('ui.action.close')"
                                            tone="danger"
                                        />
                                    @elseif ($estadoValor === 'pausado')
                                        <x-molecules.confirm-modal
                                            :id="$modalIdReanudar"
                                            :form-id="$formIdReanudar"
                                            :title="__('comercial.contratos.confirmar_reanudar_titulo')"
                                            :message="__('comercial.contratos.confirmar_reanudar')"
                                            :confirm-label="__('comercial.contratos.accion_reanudar')"
                                            tone="success"
                                        />
                                    @endif
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('comercial.contrato.editar')
                                        <x-atoms.button :href="route('panel.contratos.edit', $contrato)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('comercial.contratos.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('comercial.contrato.cambiar_estado')
                                        @if ($estadoValor === 'borrador')
                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdAprobar }}" variant="success-outline" size="sm" icon="check_circle">
                                                {{ __('comercial.contratos.accion_aprobar') }}
                                            </x-atoms.button>

                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdCancelar }}" variant="danger-outline" size="sm" icon="cancel">
                                                {{ __('comercial.contratos.accion_cancelar') }}
                                            </x-atoms.button>
                                        @elseif ($estadoValor === 'vigente')
                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdFinalizar }}" variant="distintivo-2-outline" size="sm" icon="check_circle">
                                                {{ __('comercial.contratos.accion_finalizar') }}
                                            </x-atoms.button>

                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdPausar }}" variant="info-outline" size="sm" icon="pause_circle">
                                                {{ __('comercial.contratos.accion_pausar') }}
                                            </x-atoms.button>

                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdCancelar }}" variant="danger-outline" size="sm" icon="cancel">
                                                {{ __('comercial.contratos.accion_cancelar') }}
                                            </x-atoms.button>
                                        @elseif ($estadoValor === 'conflicto')
                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdCancelar }}" variant="danger-outline" size="sm" icon="cancel">
                                                {{ __('comercial.contratos.accion_cancelar') }}
                                            </x-atoms.button>
                                        @elseif ($estadoValor === 'pausado')
                                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdReanudar }}" variant="success-outline" size="sm" icon="play_circle">
                                                {{ __('comercial.contratos.accion_reanudar') }}
                                            </x-atoms.button>
                                        @endif
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$contratos" :aria-label="__('comercial.contratos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
