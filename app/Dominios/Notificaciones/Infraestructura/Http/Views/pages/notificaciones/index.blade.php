{{--
    Page: notificaciones/index (GET /panel/notificaciones, panel.notificaciones.index)
    «Mis notificaciones»: los avisos de la cuenta que mira, del más nuevo al más
    viejo. La campana del header muestra solo los 3 últimos y enlaza aquí. Sin
    permiso propio: el sujeto es siempre la cuenta autenticada. Arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver NotificacionesController::index()): la cáscara de
    CascaraPanel, más $avisos (LengthAwarePaginator<Notificacion>) y $filas
    (list<array{id, icono, titulo, leida, creado_en}>).

    «Eliminar todas» quita las leídas y las no leídas de la cuenta (baja
    lógica); lo eliminado no aparece de nuevo.
--}}
<x-templates.panel-shell :title="__('notificaciones.mias.titulo')" :tema="$tema">
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
        :vista-actual="__('notificaciones.mias.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('notificaciones.mias.titulo')"
                :subtitle="__('notificaciones.mias.subtitulo')"
            >
                @if ($avisos->isNotEmpty())
                    <x-slot:actions>
                        <x-atoms.button type="submit" form="notificaciones-marcar-todas" variant="outline" icon="done_all">{{ __('notificaciones.mias.marcar_todas') }}</x-atoms.button>
                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#notificaciones-eliminar-modal" variant="danger-outline" icon="delete">{{ __('notificaciones.mias.eliminar_todas') }}</x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if ($avisos->isEmpty())
                <x-molecules.empty-state
                    icon="notifications"
                    :title="__('notificaciones.mias.vacio_titulo')"
                    :detail="__('notificaciones.mias.vacio_detalle')"
                />
            @else
                <form id="notificaciones-marcar-todas" method="POST" action="{{ route('panel.notificaciones.marcar-todas') }}">
                    @csrf
                </form>
                <form id="notificaciones-eliminar-todas" method="POST" action="{{ route('panel.notificaciones.limpiar') }}">
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="notificaciones-eliminar-modal"
                    form-id="notificaciones-eliminar-todas"
                    :title="__('notificaciones.mias.eliminar_titulo')"
                    :message="__('notificaciones.mias.eliminar_mensaje')"
                    :confirm-label="__('notificaciones.mias.eliminar_todas')"
                    tone="danger"
                    modal-icon="delete"
                />

                <x-molecules.index-table columns="3rem minmax(0, 3fr) minmax(0, 0.8fr) minmax(0, 0.9fr) minmax(0, 0.8fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('notificaciones.mias.col_aviso') }}</span>
                        <span role="columnheader">{{ __('notificaciones.mias.col_estado') }}</span>
                        <span role="columnheader">{{ __('notificaciones.mias.col_fecha') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($filas as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($avisos->currentPage() - 1) * $avisos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <x-atoms.icon :name="$fila['icono']" size="sm" />
                                {{ $fila['titulo'] }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$fila['leida'] ? 'neutral' : 'info'">{{ __('notificaciones.mias.'.($fila['leida'] ? 'leida' : 'sin_leer')) }}</x-atoms.badge>
                            </span>
                            <span role="cell">{{ $fila['creado_en'] }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.notificaciones.abrir', $fila['id'])" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('notificaciones.mias.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$avisos" :aria-label="__('notificaciones.mias.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
