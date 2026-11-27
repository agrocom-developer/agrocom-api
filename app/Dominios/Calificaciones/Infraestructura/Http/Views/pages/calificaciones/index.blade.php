{{--
    Page: calificaciones/index (GET /panel/calificaciones, panel.calificaciones.index)
    Moderación de calificaciones (ADR 0031 y 0039): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Las reportadas por el proveedor van primero. Ocultar pide causa y motivo; dejar
    visible, solo motivo. Ocultar no borra: la calificación deja de contar para el
    promedio y conserva su registro.

    Datos esperados (ver CalificacionesController::index()): la cáscara de
    CascaraPanel, más:
    - $calificaciones (LengthAwarePaginator<Calificacion>).
    - $nombres (array<int, string>) nombre del sujeto por id de calificación.
    - $filtros (array{estado: ?string, sujeto: ?string}).
    - $estadosFiltro (list<EstadoCalificacion>), $sujetosFiltro (list<SujetoCalificacion>),
      $causas (list<CausaModeracion>) y $puedeModerar (bool).

    Gateada por `calificaciones.calificacion.ver`, verificado server-side en el
    controlador; moderar exige además `calificaciones.calificacion.moderar`.
--}}
<x-templates.panel-shell :title="__('calificaciones.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('calificaciones.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('calificaciones.panel.titulo')"
                :subtitle="__('calificaciones.panel.subtitulo')"
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
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect($filtros)->except('q')->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('calificaciones.estado.'.$opcion->value)])->all();
                $opcionesSujeto = collect($sujetosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('calificaciones.sujeto.'.$opcion->value)])->all();
                $opcionesCausa = collect($causas)->mapWithKeys(fn ($opcion) => [$opcion->value => __('calificaciones.causa.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $calificaciones->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.calificaciones.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('calificaciones.panel.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('calificaciones.panel.filtro_todos')"
                        />
                        <x-atoms.select
                            name="sujeto"
                            id="filtro-sujeto"
                            :label="__('calificaciones.panel.filtro_sujeto')"
                            :options="$opcionesSujeto"
                            :value="$filtros['sujeto']"
                            :placeholder="__('calificaciones.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.calificaciones.index')"
                        :value="$filtros['q']"
                        :placeholder="__('calificaciones.panel.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($calificaciones->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('calificaciones.panel.filtro_vacio_titulo')"
                        :detail="__('calificaciones.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="star"
                        :title="__('calificaciones.panel.vacio_titulo')"
                        :detail="__('calificaciones.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.3fr) minmax(0, 0.8fr) minmax(0, 2fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('calificaciones.panel.col_sujeto') }}</span>
                        <span role="columnheader">{{ __('calificaciones.panel.col_estrellas') }}</span>
                        <span role="columnheader">{{ __('calificaciones.panel.col_comentario') }}</span>
                        <span role="columnheader">{{ __('calificaciones.panel.col_fecha') }}</span>
                        <span role="columnheader">{{ __('calificaciones.panel.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($calificaciones as $calificacion)
                        @php
                            $formOcultar = 'calificacion-ocultar-'.$calificacion->id;
                            $formRestablecer = 'calificacion-restablecer-'.$calificacion->id;
                            $ultimaModeracion = $calificacion->moderaciones->last();
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($calificaciones->currentPage() - 1) * $calificaciones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $nombres[$calificacion->id] }}</span>
                                <span class="ag-solicitudes__sub">{{ __('calificaciones.sujeto.'.$calificacion->sujeto_tipo->value) }} · {{ __('calificaciones.panel.de_servicio', ['solicitud' => $calificacion->solicitud_id]) }}</span>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $calificacion->estrellas }} / 5</span>
                            <span role="cell">
                                {{ $calificacion->comentario ?? __('calificaciones.panel.sin_comentario') }}
                                @if (! empty($calificacion->etiquetas))
                                    <span class="ag-solicitudes__sub">{{ collect($calificacion->etiquetas)->map(fn ($etiqueta) => __('calificaciones.etiqueta.'.$etiqueta))->implode(' · ') }}</span>
                                @endif
                                @if ($ultimaModeracion !== null)
                                    <span class="ag-solicitudes__sub">{{ __('calificaciones.panel.ultima_moderacion', ['rol' => __('calificaciones.panel.autor_'.$ultimaModeracion->autor_rol), 'motivo' => $ultimaModeracion->motivo]) }}</span>
                                @endif
                            </span>
                            <span role="cell">{{ $calificacion->created_at->format('d/m/Y') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$calificacion->estado->tono()">{{ __('calificaciones.estado.'.$calificacion->estado->value) }}</x-atoms.badge>
                                <span class="ag-solicitudes__sub">{{ __('calificaciones.autor.'.$calificacion->autor_rol->value) }}</span>
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                @if ($puedeModerar)
                                    @if ($calificacion->estado->value !== 'oculta')
                                        <form id="{{ $formOcultar }}" method="POST" action="{{ route('panel.calificaciones.ocultar', $calificacion->id) }}" hidden>
                                            @csrf
                                        </form>
                                        <x-molecules.confirm-modal
                                            :id="'calificacion-ocultar-modal-'.$calificacion->id"
                                            :form-id="$formOcultar"
                                            :title="__('calificaciones.panel.ocultar_titulo')"
                                            :message="__('calificaciones.panel.ocultar_mensaje')"
                                            :confirm-label="__('calificaciones.panel.ocultar')"
                                            tone="danger"
                                        >
                                            <x-atoms.select
                                                name="causa"
                                                :id="'calificacion-causa-'.$calificacion->id"
                                                :form="$formOcultar"
                                                :label="__('calificaciones.panel.causa_etiqueta')"
                                                :options="$opcionesCausa"
                                                required
                                            />
                                            <x-atoms.textarea
                                                name="motivo"
                                                :id="'calificacion-ocultar-motivo-'.$calificacion->id"
                                                :form="$formOcultar"
                                                :label="__('calificaciones.panel.motivo_etiqueta')"
                                                :help="__('calificaciones.panel.motivo_ayuda')"
                                                :rows="3"
                                                required
                                            />
                                        </x-molecules.confirm-modal>
                                    @endif
                                    @if ($calificacion->estado->value !== 'visible')
                                        <form id="{{ $formRestablecer }}" method="POST" action="{{ route('panel.calificaciones.restablecer', $calificacion->id) }}" hidden>
                                            @csrf
                                        </form>
                                        <x-molecules.confirm-modal
                                            :id="'calificacion-restablecer-modal-'.$calificacion->id"
                                            :form-id="$formRestablecer"
                                            :title="__('calificaciones.panel.restablecer_titulo')"
                                            :message="__('calificaciones.panel.restablecer_mensaje')"
                                            :confirm-label="__('calificaciones.panel.restablecer')"
                                            tone="success"
                                        >
                                            <x-atoms.textarea
                                                name="motivo"
                                                :id="'calificacion-restablecer-motivo-'.$calificacion->id"
                                                :form="$formRestablecer"
                                                :label="__('calificaciones.panel.motivo_etiqueta')"
                                                :help="__('calificaciones.panel.motivo_ayuda')"
                                                :rows="3"
                                                required
                                            />
                                        </x-molecules.confirm-modal>
                                    @endif
                                    <x-organisms.row-actions>
                                        <x-slot:transiciones>
                                            @if ($calificacion->estado->value !== 'oculta')
                                                <x-molecules.row-action kind="transicion" tone="danger" :modal="'calificacion-ocultar-modal-'.$calificacion->id" icon="visibility_off" :label="__('calificaciones.panel.ocultar')" />
                                            @endif
                                            @if ($calificacion->estado->value !== 'visible')
                                                <x-molecules.row-action kind="transicion" tone="success" :modal="'calificacion-restablecer-modal-'.$calificacion->id" icon="check_circle" :label="__('calificaciones.panel.restablecer')" />
                                            @endif
                                        </x-slot:transiciones>
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$calificaciones" :aria-label="__('calificaciones.panel.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
