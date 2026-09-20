{{--
    Page: estadias/index (GET /panel/estadias, panel.estadias.index)
    Listado de estadías en hacienda (HU-51, tarea 74; reforma 19/9/2026):
    entrada y salida de cada cuadrilla en cada propiedad, con sus tiempos
    efectivos. Arquetipo Listado (§6.2 de docs/diseno/guia_pantalla_panel.md):
    filter-panel + table-search + index-table + row-actions + empty-state +
    pagination.

    Datos esperados (ver EstadiasHaciendaController::index()): la cáscara de
    CascaraPanel, más:
    - $estadias (LengthAwarePaginator<EstadiaHacienda>, más reciente primero).
    - $filtros (array{q, desde, hasta, equipo_trabajo_id, propiedad_id, estado, tipo_alojamiento}).
    - $etiquetasEquipo, $etiquetasPropiedad, $etiquetasVehiculo (array<int,string>).
    - $equiposDisponibles, $propiedadesDisponibles (array<int,string>).
    - $estadosFiltro, $tiposAlojamientoFiltro (enum cases).
    - $tonoPorEstado (array<string,string>).
    - $resumen (array{en_curso, finalizadas, dias_efectivos, cuadrillas_en_campo}):
      cifras de la franja de KPI, dentro del mismo filtro que la tabla. El
      desglose por cuadrilla y por propiedad NO va acá: vive en el dashboard
      (`seguridad::pages.dashboard._seccion-dias-hacienda`).
    - $puedeCrear, $puedeEditar, $puedeEliminar (bool).

    Gateada por `operaciones.estadia.ver`, verificado en el controlador.
    Los botones de acción se ocultan con @puede (presentación, no autorización).

    Estilos en resources/css/pages/estadias.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.estadias.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.estadias.titulo')"
    >
        <div class="ag-estadias">
            <x-organisms.page-header
                :title="__('operaciones.estadias.titulo')"
                :subtitle="__('operaciones.estadias.subtitulo')"
            >
                @puede('operaciones.estadia.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.estadias.create')" variant="primary" icon="add">
                            {{ __('operaciones.estadias.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-estadias__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-estadias__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect(['equipo_trabajo_id', 'propiedad_id', 'estado', 'tipo_alojamiento', 'desde', 'hasta'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            {{-- KPI del listado: franja fija bajo la cabecera, mismo patrón que la
                 ficha de la orden. Responden al filtro aplicado. Nunca entre los
                 filtros y la tabla: esos dos van pegados. --}}
            @if ($hayFiltrosActivos || $estadias->isNotEmpty())
                <div class="ag-estadias__kpis">
                    <x-molecules.stat-card
                        :label="__('operaciones.estadias.kpi_en_curso')"
                        icon="holiday_village"
                        :value="$resumen['en_curso']"
                        :state="$resumen['en_curso'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('operaciones.estadias.kpi_cuadrillas_en_campo')"
                        icon="groups"
                        :value="$resumen['cuadrillas_en_campo']"
                        :state="$resumen['cuadrillas_en_campo'] > 0 ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('operaciones.estadias.kpi_finalizadas')"
                        icon="logout"
                        :value="$resumen['finalizadas']"
                        :state="$resumen['finalizadas'] > 0 ? 'success' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('operaciones.estadias.kpi_dias_efectivos')"
                        icon="calendar_month"
                        :value="number_format($resumen['dias_efectivos'], 1, ',', '.')"
                        :value-suffix="__('operaciones.estadias.kpi_dias_sufijo')"
                        :foot="__('operaciones.estadias.kpi_dias_pie')"
                        :state="$resumen['dias_efectivos'] > 0 ? 'distintivo-1' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $estadias->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.estadias.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        <x-atoms.date
                            name="desde"
                            id="filtro-desde"
                            :label="__('operaciones.estadias.filtro_desde')"
                            :value="$filtros['desde']"
                            :placeholder="__('operaciones.estadias.filtro_placeholder_desde')"
                        />

                        <x-atoms.date
                            name="hasta"
                            id="filtro-hasta"
                            :label="__('operaciones.estadias.filtro_hasta')"
                            :value="$filtros['hasta']"
                            :placeholder="__('operaciones.estadias.filtro_placeholder_hasta')"
                        />

                        <x-atoms.select
                            name="equipo_trabajo_id"
                            id="filtro-equipo"
                            :label="__('operaciones.estadias.filtro_equipo')"
                            :options="$equiposDisponibles"
                            :value="$filtros['equipo_trabajo_id']"
                            :placeholder="__('operaciones.estadias.filtro_todos')"
                        />

                        <x-atoms.select
                            name="propiedad_id"
                            id="filtro-propiedad"
                            :label="__('operaciones.estadias.filtro_propiedad')"
                            :options="$propiedadesDisponibles"
                            :value="$filtros['propiedad_id']"
                            :placeholder="__('operaciones.estadias.filtro_todos')"
                        />

                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('operaciones.estadias.filtro_estado')"
                            :options="collect($estadosFiltro)->mapWithKeys(fn ($e) => [$e->value => __('operaciones.estadias.estado.'.$e->value)])->all()"
                            :value="$filtros['estado']"
                            :placeholder="__('operaciones.estadias.filtro_todos')"
                        />

                        <x-atoms.select
                            name="tipo_alojamiento"
                            id="filtro-alojamiento"
                            :label="__('operaciones.estadias.filtro_alojamiento')"
                            :options="collect($tiposAlojamientoFiltro)->mapWithKeys(fn ($t) => [$t->value => __('operaciones.estadias.alojamiento.'.$t->value)])->all()"
                            :value="$filtros['tipo_alojamiento']"
                            :placeholder="__('operaciones.estadias.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.estadias.index')"
                        :value="$filtros['q']"
                        :placeholder="__('operaciones.estadias.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($estadias->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.estadias.filtro_vacio_titulo')"
                        :detail="__('operaciones.estadias.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="holiday_village"
                        :title="__('operaciones.estadias.vacio_titulo')"
                        :detail="__('operaciones.estadias.vacio_detalle')"
                    />
                @endif
            @else
                @php
                    $iconoAlojamiento = ['hacienda' => 'cottage', 'pueblo' => 'location_city', 'camping' => 'camping'];
                    $ahoraLocal = now()->format('Y-m-d\TH:i');
                @endphp

                <x-molecules.index-table columns="3rem minmax(0, 1.3fr) minmax(0, 1.6fr) minmax(0, 1.1fr) minmax(0, 1.1fr) minmax(0, 1.1fr) minmax(0, 0.6fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_cuadrilla') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_propiedad') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_alojamiento') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_entrada') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_salida') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_dias') }}</span>
                        <span role="columnheader">{{ __('operaciones.estadias.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($estadias as $estadia)
                        @php
                            $estadoFila = $estadia->estado()->value;
                            $enCurso = $estadia->salida === null;
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($estadias->currentPage() - 1) * $estadias->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">{{ $etiquetasEquipo[$estadia->equipo_trabajo_id] ?? '—' }}</span>
                            <span role="cell">{{ $etiquetasPropiedad[$estadia->propiedad_id] ?? '—' }}</span>
                            <span role="cell">
                                {{-- Etiqueta CORTA en la tabla: la larga («En el pueblo más
                                     cercano») no entra en la columna y descuadra la fila. --}}
                                @if ($estadia->tipo_alojamiento !== null)
                                    <x-atoms.badge variant="neutral" :icon="$iconoAlojamiento[$estadia->tipo_alojamiento->value] ?? null">
                                        {{ __('operaciones.estadias.alojamiento_corto.'.$estadia->tipo_alojamiento->value) }}
                                    </x-atoms.badge>
                                @else
                                    <span class="ag-estadias__atenuado">{{ __('operaciones.estadias.alojamiento_sin_registrar') }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-estadias__mono">{{ $estadia->entrada->format('d/m/Y H:i') }}</span>
                            <span role="cell" class="ag-estadias__mono">{{ $estadia->salida?->format('d/m/Y H:i') ?? '—' }}</span>
                            <span role="cell" class="ag-estadias__mono">
                                {{ $enCurso ? '—' : number_format($estadia->entrada->diffInSeconds($estadia->salida) / 86400, 1, ',', '.') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoFila] ?? 'neutral'">
                                    {{ __('operaciones.estadias.estado.'.$estadoFila) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Forms FUERA de row-actions a propósito: ese organism repite su
                                     slot dos veces (visible/menú) — un <form> con id ahí adentro se
                                     duplicaría. Los botones los envían por su atributo `form`. --}}
                                @if ($puedeEliminar)
                                    <form id="estadia-eliminar-{{ $estadia->id }}" method="POST" action="{{ route('panel.estadias.destroy', $estadia) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif

                                @if ($puedeEditar && $enCurso)
                                    <form id="estadia-finalizar-{{ $estadia->id }}" method="POST" action="{{ route('panel.estadias.finalizar', $estadia) }}" hidden>
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="'estadia-finalizar-modal-'.$estadia->id"
                                        :form-id="'estadia-finalizar-'.$estadia->id"
                                        :title="__('operaciones.estadias.confirmar_finalizar_titulo')"
                                        :message="__('operaciones.estadias.confirmar_finalizar')"
                                        :confirm-label="__('operaciones.estadias.finalizar_accion')"
                                        :tone="$tonoPorEstado['finalizada'] ?? 'success'"
                                        modal-icon="logout"
                                    >
                                        <x-molecules.state-transition
                                            :from-label="__('operaciones.estadias.estado.en_curso')"
                                            :from-tone="$tonoPorEstado['en_curso'] ?? 'neutral'"
                                            :to-label="__('operaciones.estadias.estado.finalizada')"
                                            :to-tone="$tonoPorEstado['finalizada'] ?? 'neutral'"
                                            :label="__('operaciones.estadias.estado_cambio_de_a', [
                                                'desde' => __('operaciones.estadias.estado.en_curso'),
                                                'hacia' => __('operaciones.estadias.estado.finalizada'),
                                            ])"
                                        />

                                        <x-atoms.datetime
                                            name="salida"
                                            :id="'estadia-salida-'.$estadia->id"
                                            :form="'estadia-finalizar-'.$estadia->id"
                                            :label="__('operaciones.estadias.campo_salida_finalizar')"
                                            :value="$ahoraLocal"
                                            :help="__('operaciones.estadias.campo_salida_finalizar_ayuda')"
                                            required
                                        />
                                    </x-molecules.confirm-modal>
                                @endif

                                <x-organisms.row-actions>
                                    @if ($puedeEditar)
                                        {{-- Una finalizada ya no se edita: su ficha es de solo lectura. --}}
                                        <x-atoms.button
                                            :href="route('panel.estadias.edit', $estadia)"
                                            :variant="$enCurso ? 'warning-outline' : 'outline'"
                                            size="sm"
                                            :icon="$enCurso ? 'edit' : 'visibility'"
                                        >
                                            {{ $enCurso ? __('operaciones.estadias.editar_accion') : __('operaciones.estadias.ver_accion') }}
                                        </x-atoms.button>

                                        @if ($enCurso)
                                            <x-atoms.button variant="success-outline" size="sm" icon="logout" data-bs-toggle="modal" :data-bs-target="'#estadia-finalizar-modal-'.$estadia->id">
                                                {{ __('operaciones.estadias.finalizar_accion') }}
                                            </x-atoms.button>
                                        @endif
                                    @endif

                                    @if ($puedeEliminar)
                                        <span class="ag-row-actions__item">
                                            <x-molecules.confirm-button
                                                :form-id="'estadia-eliminar-'.$estadia->id"
                                                :title="__('operaciones.estadias.confirmar_eliminar_titulo')"
                                                :message="__('operaciones.estadias.confirmar_baja')"
                                                :confirm-label="__('operaciones.estadias.eliminar_accion')"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('operaciones.estadias.eliminar_accion') }}
                                            </x-molecules.confirm-button>
                                        </span>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$estadias" :aria-label="__('operaciones.estadias.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
