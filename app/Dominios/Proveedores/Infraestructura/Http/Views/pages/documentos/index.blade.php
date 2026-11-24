{{--
    Page: documentos/index (GET /panel/documentos, panel.documentos.index)
    Cola de revisión de documentos de verificación (ADR 0032, punto 3): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md. Por defecto muestra lo
    que espera revisión, del más antiguo al más nuevo. Cada fila abre el archivo
    (acceso restringido) y permite aprobarlo o rechazarlo con motivo; el sujeto
    del documento se abre desde su nombre.

    Datos esperados (ver DocumentosController::index()): la cáscara de
    CascaraPanel, más $documentos (LengthAwarePaginator<Documento>), $etiquetas
    (array<int, string>: nombre del sujeto por documento), $rutaFicha (array),
    $filtros (array{estado: ?string, sujeto_tipo: ?string, tipo: ?string}),
    $filtroEstadoExplicito (bool), las listas de filtros y $puedeRevisar.

    Gateada por `proveedores.documento.ver`, verificado server-side en el
    controlador; aprobar y rechazar exigen `proveedores.documento.revisar`.
    Estilos en resources/css/pages/proveedores.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('proveedores.documentos.cola_titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.documentos.cola_titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('proveedores.documentos.cola_titulo')"
                :subtitle="__('proveedores.documentos.cola_subtitulo')"
            />

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = $filtroEstadoExplicito || $filtros['sujeto_tipo'] !== null || $filtros['tipo'] !== null;
                $filtrosPanelActivos = collect(['estado', 'sujeto_tipo', 'tipo'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('proveedores.documentos.estado.'.$opcion->value)])->all();
                $opcionesSujeto = collect($sujetosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('aprobacion.sujeto.'.$opcion->value)])->all();
                $opcionesTipo = collect($tiposFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('proveedores.documentos.tipo.'.$opcion->value)])->all();
                $tonoPorEstado = ['en_revision' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger', 'vencido' => 'danger'];
            @endphp

            @if ($hayFiltrosActivos || $documentos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.documentos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('proveedores.documentos.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                        <x-atoms.select
                            name="sujeto_tipo"
                            id="filtro-sujeto"
                            :label="__('proveedores.documentos.filtro_sujeto')"
                            :options="$opcionesSujeto"
                            :value="$filtros['sujeto_tipo']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            :label="__('proveedores.documentos.filtro_tipo')"
                            :options="$opcionesTipo"
                            :value="$filtros['tipo']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($documentos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('proveedores.documentos.filtro_vacio_titulo')"
                        :detail="__('proveedores.documentos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="task"
                        :title="__('proveedores.documentos.cola_vacia_titulo')"
                        :detail="__('proveedores.documentos.cola_vacia_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('proveedores.documentos.col_sujeto') }}</span>
                        <span role="columnheader">{{ __('proveedores.documentos.col_tipo') }}</span>
                        <span role="columnheader">{{ __('proveedores.documentos.col_vence') }}</span>
                        <span role="columnheader">{{ __('proveedores.documentos.col_estado') }}</span>
                        <span role="columnheader">{{ __('proveedores.documentos.col_subido') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($documentos as $documento)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($documentos->currentPage() - 1) * $documentos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $etiquetas[$documento->id] }}</span>
                                <span class="ag-proveedores__sub">{{ __('aprobacion.sujeto.'.$documento->sujeto_tipo->value) }}</span>
                            </span>
                            <span role="cell">{{ __('proveedores.documentos.tipo.'.$documento->tipo->value) }}</span>
                            <span role="cell">{{ $documento->vence_en?->format('d/m/Y') ?? __('proveedores.documentos.sin_vencimiento') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$documento->estado->value] ?? 'neutral'">{{ __('proveedores.documentos.estado.'.$documento->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell">{{ $documento->created_at->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                @include('proveedores::pages._acciones-documento', [
                                    'docId' => $documento->id,
                                    'docEstado' => $documento->estado->value,
                                    'docEtiqueta' => __('proveedores.documentos.tipo.'.$documento->tipo->value),
                                    'puedeVer' => true,
                                    'puedeRevisar' => $puedeRevisar,
                                ])
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$documentos" :aria-label="__('proveedores.documentos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
