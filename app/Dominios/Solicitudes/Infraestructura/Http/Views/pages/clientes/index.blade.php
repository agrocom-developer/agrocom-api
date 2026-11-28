{{--
    Page: clientes/index (GET /panel/clientes, panel.clientes.index)
    Los clientes del marketplace: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Los suspendidos van primero. El cliente se registra solo desde la app o lo carga
    la oficina («Registrar cliente», con permiso); acá se consulta y, con permiso, se
    suspende o reactiva desde su ficha.

    Datos esperados (ver ClientesController::index()): la cáscara de CascaraPanel,
    más $puedeCrear (bool), $clientes (LengthAwarePaginator<Cliente>), $filtros (array{q: string,
    estado: ?string}) y $estadosFiltro (list<EstadoAprobacion>).

    Gateada por `solicitudes.cliente.ver`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('solicitudes.clientes.titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.clientes.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('solicitudes.clientes.titulo')"
                :subtitle="__('solicitudes.clientes.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.clientes.create')" variant="primary" icon="person_add">{{ __('solicitudes.clientes.registrar') }}</x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.clientes.estado.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $clientes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.clientes.index')"
                        :active-count="$filtros['estado'] ? 1 : 0"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('solicitudes.clientes.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('solicitudes.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.clientes.index')"
                        :value="$filtros['q']"
                        :placeholder="__('solicitudes.clientes.buscar_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($clientes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('solicitudes.clientes.filtro_vacio_titulo')"
                        :detail="__('solicitudes.clientes.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="agriculture"
                        :title="__('solicitudes.clientes.vacio_titulo')"
                        :detail="__('solicitudes.clientes.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_cliente') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_tipo') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_estado') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_calificacion') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_alta') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($clientes as $cliente)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($clientes->currentPage() - 1) * $clientes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $cliente->razon_social }}</span>
                                <span class="ag-solicitudes__sub">{{ implode(' · ', array_filter([$cliente->documento_fiscal, $cliente->telefono])) ?: __('solicitudes.clientes.sin_contacto') }}</span>
                            </span>
                            <span role="cell">{{ __('solicitudes.clientes.tipo.'.$cliente->tipo->value) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$cliente->estado->tono()">{{ __('solicitudes.clientes.estado.'.$cliente->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                @if ($cliente->calificaciones_conteo > 0)
                                    {{ $cliente->calificacion_promedio }} ({{ $cliente->calificaciones_conteo }})
                                @else
                                    {{ __('solicitudes.clientes.sin_calificaciones') }}
                                @endif
                            </span>
                            <span role="cell">{{ $cliente->created_at->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $esPendiente = $cliente->estado->value === 'pendiente';
                                    $esAprobado = $cliente->estado->value === 'aprobado';
                                    $esSuspendido = $cliente->estado->value === 'suspendido';
                                    $conAprobar = $puedeAprobar && $esPendiente;
                                    $conSuspender = $puedeSuspender && $esAprobado;
                                    $conReactivar = $puedeSuspender && $esSuspendido;
                                @endphp

                                {{-- Forms y modales FUERA de row-actions (ver su docblock). --}}
                                @if ($conAprobar)
                                    <form id="cliente-aprobar-form-{{ $cliente->id }}" method="POST" action="{{ route('panel.clientes.aprobar', $cliente) }}" hidden>
                                        @csrf
                                        <input type="hidden" name="desde" value="listado">
                                    </form>
                                    <form id="cliente-rechazar-form-{{ $cliente->id }}" method="POST" action="{{ route('panel.clientes.rechazar', $cliente) }}" hidden>
                                        @csrf
                                        <input type="hidden" name="desde" value="listado">
                                    </form>
                                    <x-molecules.confirm-modal
                                        :id="'cliente-rechazar-modal-'.$cliente->id"
                                        :form-id="'cliente-rechazar-form-'.$cliente->id"
                                        :title="__('solicitudes.clientes.rechazar_titulo', ['cliente' => $cliente->razon_social])"
                                        :message="__('solicitudes.clientes.rechazar_mensaje')"
                                        :confirm-label="__('solicitudes.clientes.rechazar')"
                                        tone="alert"
                                    >
                                        <x-atoms.textarea
                                            name="motivo"
                                            :id="'cliente-rechazar-motivo-'.$cliente->id"
                                            :form="'cliente-rechazar-form-'.$cliente->id"
                                            :label="__('solicitudes.clientes.motivo_etiqueta_rechazo')"
                                            :help="__('solicitudes.clientes.motivo_ayuda')"
                                            :rows="3"
                                            required
                                        />
                                    </x-molecules.confirm-modal>
                                @endif

                                @if ($conSuspender)
                                    <form id="cliente-suspender-form-{{ $cliente->id }}" method="POST" action="{{ route('panel.clientes.suspender', $cliente) }}" hidden>
                                        @csrf
                                        <input type="hidden" name="desde" value="listado">
                                    </form>
                                    <x-molecules.confirm-modal
                                        :id="'cliente-suspender-modal-'.$cliente->id"
                                        :form-id="'cliente-suspender-form-'.$cliente->id"
                                        :title="__('solicitudes.clientes.suspender_titulo', ['cliente' => $cliente->razon_social])"
                                        :message="__('solicitudes.clientes.suspender_mensaje')"
                                        :confirm-label="__('solicitudes.clientes.suspender')"
                                        tone="neutral"
                                    >
                                        <x-atoms.textarea
                                            name="motivo"
                                            :id="'cliente-suspender-motivo-'.$cliente->id"
                                            :form="'cliente-suspender-form-'.$cliente->id"
                                            :label="__('solicitudes.clientes.motivo_etiqueta')"
                                            :help="__('solicitudes.clientes.motivo_ayuda')"
                                            :rows="3"
                                            required
                                        />
                                    </x-molecules.confirm-modal>
                                @endif

                                @if ($conReactivar)
                                    <form id="cliente-reactivar-form-{{ $cliente->id }}" method="POST" action="{{ route('panel.clientes.reactivar', $cliente) }}" hidden>
                                        @csrf
                                        <input type="hidden" name="desde" value="listado">
                                    </form>
                                @endif

                                <x-organisms.row-actions>
                                    <x-slot:ver>
                                        <x-molecules.row-action kind="ver" :href="route('panel.clientes.show', $cliente)" icon="visibility" :label="__('solicitudes.clientes.ver')" />
                                    </x-slot:ver>

                                    @if ($conAprobar)
                                        <x-slot:aprobar>
                                            <x-molecules.row-action kind="aprobar" type="submit" :form="'cliente-aprobar-form-'.$cliente->id" icon="check_circle" :label="__('solicitudes.clientes.aprobar')" />
                                        </x-slot:aprobar>
                                    @endif

                                    @if ($conAprobar || $conSuspender || $conReactivar)
                                        <x-slot:transiciones>
                                            @if ($conAprobar)
                                                <x-molecules.row-action kind="transicion" tone="alert" :modal="'cliente-rechazar-modal-'.$cliente->id" icon="cancel" :label="__('solicitudes.clientes.rechazar')" />
                                            @endif
                                            @if ($conSuspender)
                                                <x-molecules.row-action kind="transicion" tone="neutral" :modal="'cliente-suspender-modal-'.$cliente->id" icon="block" :label="__('solicitudes.clientes.suspender')" />
                                            @endif
                                            @if ($conReactivar)
                                                <x-molecules.row-action kind="transicion" tone="success" type="submit" :form="'cliente-reactivar-form-'.$cliente->id" icon="lock_open" :label="__('solicitudes.clientes.reactivar')" />
                                            @endif
                                        </x-slot:transiciones>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$clientes" :aria-label="__('solicitudes.clientes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
