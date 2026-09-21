{{--
    Page: dispositivos/index (GET /panel/dispositivos, panel.dispositivos.index)
    Pantalla de revocación de sesiones de la app de campo (HU-03, CA
    "revocable desde el panel"): quién tiene sesión abierta, desde qué
    equipo, con qué rol, y el botón para dejarlo afuera. Arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver DispositivosController::index()): la cáscara de
    CascaraPanel (menu/roles/…/tema/zonaHoraria/version), más:
    - $dispositivos (LengthAwarePaginator<SecTokenDispositivo>): sesiones
      vivas, con `rol` y `tokenable` (el dueño) precargados, la más reciente
      primero.
    - $rolesDisponibles (Collection<int, string>): id => nombre legible de los
      roles con los que opera algún dispositivo, para el filtro.
    - $filtros (array{q: string, rol_id: int|null}): valores aplicados, para
      dejar los campos con el valor tras el submit. `q` mira el equipo y su
      dueño; el resumen de una cuenta (`usuarios/edit`) enlaza acá con su
      usuario ya puesto en el buscador.

    El botón de revocar se gatea con la directiva `@puede` del módulo, que
    evalúa contra el ROL ACTIVO (nunca la unión de roles — CLAUDE.md
    invariante 10). Ocultarlo es presentación: el DELETE igual se rechaza
    server-side en DispositivosController::destroy().

    Estilos en resources/css/pages/dispositivos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol')
<x-templates.panel-shell :title="__('seguridad.dispositivos.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.dispositivos.titulo')"
    >
        <div class="ag-dispositivos">
            <x-organisms.page-header
                :title="__('seguridad.dispositivos.titulo')"
                :subtitle="__('seguridad.dispositivos.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $dispositivos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.dispositivos.index')"
                        :active-count="$filtros['rol_id'] !== null ? 1 : 0"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="rol_id"
                            id="filtro-rol"
                            :label="__('seguridad.dispositivos.filtro_rol')"
                            :options="$rolesDisponibles"
                            :value="(string) $filtros['rol_id']"
                            :placeholder="__('seguridad.dispositivos.filtro_rol_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.dispositivos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('seguridad.dispositivos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($dispositivos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('seguridad.dispositivos.filtro_vacio_titulo')"
                        :detail="__('seguridad.dispositivos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="smartphone"
                        :title="__('seguridad.dispositivos.vacio_titulo')"
                        :detail="__('seguridad.dispositivos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table
                    class="ag-dispositivos__listado"
                    columns="3rem minmax(0, 1.3fr) minmax(0, 1.8fr) minmax(0, 1.4fr) minmax(0, 1fr) var(--ag-row-actions-width)"
                >
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('seguridad.dispositivos.col_usuario') }}</span>
                        <span role="columnheader">{{ __('seguridad.dispositivos.col_dispositivo') }}</span>
                        <span role="columnheader">{{ __('seguridad.dispositivos.col_rol') }}</span>
                        <span role="columnheader">{{ __('seguridad.dispositivos.col_ultimo_uso') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($dispositivos as $dispositivo)
                        @php
                            $nombreEquipo = $dispositivo->nombre_dispositivo ?? __('seguridad.dispositivos.equipo_sin_nombre');
                            $nombreDueno = $dispositivo->tokenable?->name ?? __('seguridad.dispositivos.usuario_desconocido');
                            $formIdRevocar = "dispositivo-revocar-{$dispositivo->id}";
                            $modalIdRevocar = "dispositivo-revocar-modal-{$dispositivo->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($dispositivos->currentPage() - 1) * $dispositivos->perPage() + $loop->iteration }}
                            </span>

                            <span role="cell">{{ $nombreDueno }}</span>

                            <span role="cell">
                                <span class="ag-dispositivos__equipo">{{ $nombreEquipo }}</span>
                                <span class="ag-dispositivos__uuid">{{ $dispositivo->uuid_dispositivo }}</span>
                            </span>

                            <span role="cell">
                                @if ($dispositivo->rol !== null)
                                    <x-atoms.badge variant="neutral">{{ PresentadorRol::nombreLegible($dispositivo->rol) }}</x-atoms.badge>
                                @endif
                            </span>

                            <span role="cell">
                                {{ $dispositivo->last_used_at?->diffForHumans() ?? __('seguridad.dispositivos.sin_uso') }}
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @puede('seguridad.dispositivo.revocar')
                                    {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                         repite su slot dos veces (visible/menú, ver su docblock), así
                                         que un <form> o un modal con id ahí adentro se duplicaría — y
                                         el que cae dentro del menú ⋮ queda oculto con él y nunca abre.
                                         El disparador sí va adentro (es un botón sin id propio). --}}
                                    <form id="{{ $formIdRevocar }}" method="POST" action="{{ route('panel.dispositivos.revocar', $dispositivo) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdRevocar"
                                        :form-id="$formIdRevocar"
                                        :title="__('seguridad.dispositivos.confirmar_revocar_titulo')"
                                        :message="__('seguridad.dispositivos.confirmar_revocar', ['dispositivo' => $nombreEquipo, 'usuario' => $nombreDueno])"
                                        :confirm-label="__('seguridad.dispositivos.revocar')"
                                        modal-icon="link_off"
                                    />

                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdRevocar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="link_off"
                                        >
                                            {{ __('seguridad.dispositivos.revocar') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$dispositivos" :aria-label="__('seguridad.dispositivos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
