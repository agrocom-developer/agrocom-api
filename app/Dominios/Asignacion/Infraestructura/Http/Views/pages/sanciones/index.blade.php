{{--
    Page: sanciones/index (GET /panel/sanciones, panel.sanciones.index)
    Sanciones de los proveedores (ADR 0038): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → tabla → paginación. Las
    vigentes van primero. Levantar una sanción (la apelación o la decisión de
    una revisión de suspensión) pide un motivo en un modal.

    Datos esperados (ver SancionesController::index()): la cáscara de
    CascaraPanel, más $sanciones (LengthAwarePaginator<Sancion>), $nombres
    (array<int, string>) y $puedeLevantar (bool).

    Gateada por `asignacion.sancion.ver`, verificado server-side en el
    controlador; levantar exige además `asignacion.sancion.levantar`.
--}}
<x-templates.panel-shell :title="__('asignacion.sanciones.titulo')" :tema="$tema">
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
        :vista-actual="__('asignacion.sanciones.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('asignacion.sanciones.titulo')"
                :subtitle="__('asignacion.sanciones.subtitulo')"
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

            @if ($sanciones->isEmpty())
                <x-molecules.empty-state
                    icon="verified_user"
                    :title="__('asignacion.sanciones.vacio_titulo')"
                    :detail="__('asignacion.sanciones.vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1.6fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('asignacion.sanciones.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('asignacion.sanciones.col_tipo') }}</span>
                        <span role="columnheader">{{ __('asignacion.sanciones.col_motivo') }}</span>
                        <span role="columnheader">{{ __('asignacion.sanciones.col_desde') }}</span>
                        <span role="columnheader">{{ __('asignacion.sanciones.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($sanciones as $sancion)
                        @php
                            $vigente = $sancion->estaVigente();
                            $formId = 'sancion-levantar-'.$sancion->id;
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($sanciones->currentPage() - 1) * $sanciones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $sancion->proveedor_id) }}">{{ $nombres[$sancion->proveedor_id] }}</a>
                            </span>
                            <span role="cell">
                                {{ __('asignacion.sanciones.tipo.'.$sancion->tipo->value) }}
                                <span class="ag-solicitudes__sub">{{ __('asignacion.sanciones.causa.'.$sancion->causa->value) }}</span>
                            </span>
                            <span role="cell">{{ $sancion->motivo }}</span>
                            <span role="cell">{{ $sancion->desde->format('d/m/Y') }}@if ($sancion->hasta) <span class="ag-solicitudes__sub">{{ __('asignacion.sanciones.hasta', ['fecha' => $sancion->hasta->format('d/m/Y')]) }}</span>@endif</span>
                            <span role="cell">
                                @if ($sancion->levantada_en !== null)
                                    <x-atoms.badge variant="neutral">{{ __('asignacion.sanciones.estado.levantada') }}</x-atoms.badge>
                                @elseif ($vigente)
                                    <x-atoms.badge :variant="$sancion->tipo->impideOfertas() ? 'danger' : 'warning'">{{ __('asignacion.sanciones.estado.vigente') }}</x-atoms.badge>
                                @else
                                    <x-atoms.badge variant="neutral">{{ __('asignacion.sanciones.estado.vencida') }}</x-atoms.badge>
                                @endif
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                @if ($puedeLevantar && $vigente)
                                    <form id="{{ $formId }}" method="POST" action="{{ route('panel.sanciones.levantar', $sancion->id) }}" hidden>
                                        @csrf
                                    </form>
                                    <x-molecules.confirm-modal
                                        :id="'sancion-levantar-modal-'.$sancion->id"
                                        :form-id="$formId"
                                        :title="__('asignacion.sanciones.levantar_titulo', ['proveedor' => $nombres[$sancion->proveedor_id]])"
                                        :message="__('asignacion.sanciones.levantar_mensaje')"
                                        :confirm-label="__('asignacion.sanciones.levantar')"
                                        tone="success"
                                    >
                                        <x-atoms.textarea
                                            name="motivo"
                                            :id="'sancion-levantar-motivo-'.$sancion->id"
                                            :form="$formId"
                                            :label="__('asignacion.panel.motivo_etiqueta')"
                                            :help="__('asignacion.sanciones.motivo_ayuda')"
                                            :rows="3"
                                            required
                                        />
                                    </x-molecules.confirm-modal>
                                    <x-organisms.row-actions>
                                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#sancion-levantar-modal-{{ $sancion->id }}" variant="success-outline" size="sm" icon="check_circle">
                                            {{ __('asignacion.sanciones.levantar') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$sanciones" :aria-label="__('asignacion.sanciones.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
