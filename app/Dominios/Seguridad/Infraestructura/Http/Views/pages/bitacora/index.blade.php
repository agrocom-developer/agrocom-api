{{--
    Page: bitacora/index (GET /panel/bitacora, panel.bitacora.index)
    Listado de auditoría — quién hizo qué, cuándo, en qué zona horaria (HU-63,
    tarea 63): arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md —
    cabecera → filtros → tabla → paginación. Tabla de solo lectura, sin acciones
    de crear/editar/eliminar. Mismo molde que usuarios/index.blade.php.

    Datos esperados (ver BitacoraController::index()):
    - $bitacora (LengthAwarePaginator<FilaBitacora>): paginado y ordenado del
      más reciente al más viejo.
    - $usuariosDisponibles (Collection<int, string>): id => "Nombre (username)",
      para el <x-atoms.select name="usuario_id">.
    - $tablasDisponibles (array<string, string>): nombre_fisico_tabla =>
      nombre legible, para <x-atoms.select name="tabla">.
    - $accionesDisponibles (array<string, string>): 'creado'|'actualizado'|
      'eliminado' => texto legible, para <x-atoms.select name="accion">.
    - $zonaQueVe (string IANA): zona del usuario autenticado, YA aplicada a
      $bitacora; mostrala solo si $fila->zonaRegistrada no es null.
    - $filtros (array{usuario_id: ?int, tabla: ?string, accion: ?string,
      desde: ?string, hasta: ?string, registro_id: ?int}): valores aplicados,
      para repoblar el formulario de filtros.

    Gateada por `seguridad.bitacora.ver`, verificado server-side en el
    controlador. Ninguna acción de mutación (@puede nunca aparece).

    Estilos en resources/css/pages/bitacora.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('seguridad.bitacora.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.bitacora.titulo')"
    >
        <div class="ag-bitacora">
            <x-organisms.page-header
                :title="__('seguridad.bitacora.titulo')"
                :subtitle="__('seguridad.bitacora.subtitulo')"
            />

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $bitacora->isNotEmpty())
                <form method="GET" action="{{ route('panel.bitacora.index') }}" class="ag-filtros ag-bitacora__filtros">
                    <x-atoms.select
                        name="usuario_id"
                        label="{{ __('seguridad.bitacora.filtro_usuario') }}"
                        :options="$usuariosDisponibles"
                        :value="(string) $filtros['usuario_id']"
                        placeholder="{{ __('seguridad.bitacora.filtro_usuario_todos') }}"
                    />

                    <x-atoms.select
                        name="tabla"
                        label="{{ __('seguridad.bitacora.filtro_entidad') }}"
                        :options="$tablasDisponibles"
                        :value="$filtros['tabla']"
                        placeholder="{{ __('seguridad.bitacora.filtro_entidad_todas') }}"
                    />

                    <x-atoms.select
                        name="accion"
                        label="{{ __('seguridad.bitacora.filtro_accion') }}"
                        :options="$accionesDisponibles"
                        :value="$filtros['accion']"
                        placeholder="{{ __('seguridad.bitacora.filtro_accion_todas') }}"
                    />

                    <x-atoms.date
                        name="desde"
                        label="{{ __('seguridad.bitacora.filtro_desde') }}"
                        value="{{ $filtros['desde'] }}"
                    />

                    <x-atoms.date
                        name="hasta"
                        label="{{ __('seguridad.bitacora.filtro_hasta') }}"
                        value="{{ $filtros['hasta'] }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="registro_id"
                        label="{{ __('seguridad.bitacora.filtro_registro_id') }}"
                        value="{{ $filtros['registro_id'] }}"
                    />

                    <div class="ag-filtros__acciones ag-bitacora__filtros-acciones">
                        <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                            {{ __('seguridad.bitacora.filtro_aplicar') }}
                        </x-atoms.button>

                        @if ($hayFiltrosActivos)
                            <x-atoms.button href="{{ route('panel.bitacora.index') }}" variant="text" size="md">
                                {{ __('seguridad.bitacora.filtro_limpiar') }}
                            </x-atoms.button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($bitacora->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="history" class="ag-bitacora__aviso">
                        {{ __('seguridad.bitacora.sin_resultados') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="history"
                        :title="__('seguridad.bitacora.vacio_titulo')"
                        :detail="__('seguridad.bitacora.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-bitacora__tabla" role="table">
                    <div class="ag-bitacora__head" role="row">
                        <span role="columnheader">{{ __('seguridad.bitacora.columna_instante') }}</span>
                        <span role="columnheader">{{ __('seguridad.bitacora.columna_usuario') }}</span>
                        <span role="columnheader">{{ __('seguridad.bitacora.columna_entidad') }}</span>
                        <span role="columnheader">{{ __('seguridad.bitacora.columna_accion') }}</span>
                        <span role="columnheader">{{ __('seguridad.bitacora.columna_detalle') }}</span>
                    </div>

                    @foreach ($bitacora as $fila)
                        <div class="ag-bitacora__fila" role="row">
                            <span role="cell" class="ag-bitacora__instante">
                                <div class="ag-bitacora__instante-principal">{{ $fila->instante->format('d/m/Y H:i') }} ({{ $fila->offset }})</div>
                                @if ($fila->zonaRegistrada !== null)
                                    <div class="ag-bitacora__instante-zona">{{ __('seguridad.bitacora.registrado_en', ['zona' => $fila->zonaRegistrada]) }}</div>
                                @endif
                            </span>

                            <span role="cell" class="ag-bitacora__usuario">
                                @if ($fila->actorNombre !== null)
                                    {{ $fila->actorNombre }} ({{ $fila->actorUsername }})
                                @else
                                    {{ __('seguridad.bitacora.actor_sistema') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-bitacora__entidad">{{ $fila->tablaLegible }}</span>

                            <span role="cell" class="ag-bitacora__accion">
                                @switch($fila->accion->value)
                                    @case('creado')
                                        <x-atoms.badge variant="success">{{ __('seguridad.bitacora.acciones.creado') }}</x-atoms.badge>
                                        @break

                                    @case('actualizado')
                                        <x-atoms.badge variant="info">{{ __('seguridad.bitacora.acciones.actualizado') }}</x-atoms.badge>
                                        @break

                                    @case('eliminado')
                                        <x-atoms.badge variant="danger">{{ __('seguridad.bitacora.acciones.eliminado') }}</x-atoms.badge>
                                        @break

                                    @default
                                        <x-atoms.badge variant="neutral">{{ $fila->accion->value }}</x-atoms.badge>
                                @endswitch
                            </span>

                            <span role="cell" class="ag-bitacora__detalle">
                                <details class="ag-bitacora__details">
                                    <summary class="ag-bitacora__summary">{{ __('seguridad.bitacora.ver_detalle') }}</summary>
                                    @if (count($fila->diff) === 0)
                                        <p class="ag-bitacora__diff-vacio">{{ __('seguridad.bitacora.diff_sin_datos') }}</p>
                                    @else
                                        <table class="ag-bitacora__diff">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('seguridad.bitacora.diff_campo') }}</th>
                                                    <th>{{ __('seguridad.bitacora.diff_antes') }}</th>
                                                    <th>{{ __('seguridad.bitacora.diff_despues') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($fila->diff as $cambio)
                                                    <tr>
                                                        <td>{{ $cambio['campo'] }}</td>
                                                        <td>{{ is_array($cambio['antes']) || is_bool($cambio['antes']) ? json_encode($cambio['antes']) : ($cambio['antes'] ?? '—') }}</td>
                                                        <td>{{ is_array($cambio['despues']) || is_bool($cambio['despues']) ? json_encode($cambio['despues']) : ($cambio['despues'] ?? '—') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </details>
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($bitacora->hasPages())
                    <nav class="ag-bitacora__paginacion" aria-label="{{ __('seguridad.usuarios.paginacion_aria') }}">
                        @if (! $bitacora->onFirstPage())
                            <x-atoms.button href="{{ $bitacora->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('seguridad.usuarios.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-bitacora__paginacion-info">
                            {{ __('seguridad.usuarios.paginacion_info', ['actual' => $bitacora->currentPage(), 'total' => $bitacora->lastPage()]) }}
                        </span>

                        @if ($bitacora->hasMorePages())
                            <x-atoms.button href="{{ $bitacora->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('seguridad.usuarios.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
