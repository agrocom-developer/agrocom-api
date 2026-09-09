{{--
    Page: contratos/index (GET /panel/contratos, panel.contratos.index)
    Listado de contratos (HU-23, tarea 34): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Segundo ABM del panel, mismo molde que `clientes/index.blade.php`
    (tarea 33) con una máquina de estados encima.

    Datos esperados (ver ContratosController::index()): la cáscara de
    CascaraPanel, más:
    - $contratos (LengthAwarePaginator<Contrato>, con `cliente` y `ventanas`
      precargadas): fecha de inicio descendente. La columna "Ventanas"
      muestra "Día completo" (HU-47, tarea 70) cuando la relación viene
      vacía — cero ventanas ya significa eso, sin booleano propio.
    - $campaniasDisponibles (Collection<int, string>): id => "código —
      cliente", para el <select> del filtro por campaña (ADR 0015 punto 1).
    - $filtros (array{q: string, campania_id: int|null}): filtros aplicados,
      para dejar los campos con el valor tras el submit.

    Gateada por `comercial.contrato.ver`, verificado server-side en el
    controlador. El botón "Nuevo contrato" y las acciones de cambio de
    estado se ocultan con `@puede` (presentación, no autorización — el
    servidor revalida en ContratosController). Las acciones de cambio de
    estado disponibles dependen del estado ACTUAL de cada fila: un contrato
    `borrador` solo ofrece "Activar" o "Cancelar"; uno `vigente`, "Finalizar"
    o "Cancelar"; uno `finalizado`/`cancelado`, ninguna (son terminales) —
    ver `TransicionesContrato`, que es la fuente real de esta regla; acá solo
    se refleja para no ofrecer un botón que el servidor va a rechazar.

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
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
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
                        <x-atoms.button href="{{ route('panel.contratos.create') }}" variant="primary" icon="add">
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

            <form method="GET" action="{{ route('panel.contratos.index') }}" class="ag-filtros ag-contratos__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('comercial.contratos.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('comercial.contratos.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <x-atoms.select
                    name="campania_id"
                    id="filtro-campania"
                    label="{{ __('comercial.contratos.filtro_campania') }}"
                    :options="$campaniasDisponibles"
                    :value="$filtros['campania_id']"
                    placeholder="{{ __('comercial.contratos.filtro_campania_placeholder') }}"
                />

                <div class="ag-filtros__acciones ag-contratos__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('comercial.contratos.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '' || $filtros['campania_id'] !== null)
                        <x-atoms.button href="{{ route('panel.contratos.index') }}" variant="text" size="md">
                            {{ __('comercial.contratos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($contratos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="description" class="ag-contratos__aviso">
                    {{ __(($filtros['q'] !== '' || $filtros['campania_id'] !== null) ? 'comercial.contratos.filtro_vacio' : 'comercial.contratos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-contratos__tabla" role="table">
                    <div class="ag-contratos__head" role="row">
                        <span role="columnheader">{{ __('comercial.contratos.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_monto_total') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_ventanas') }}</span>
                        <span role="columnheader">{{ __('comercial.contratos.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($contratos as $contrato)
                        @php
                            $variantePorEstado = [
                                'borrador' => 'neutral',
                                'vigente' => 'success',
                                'finalizado' => 'info',
                                'cancelado' => 'danger',
                            ];
                            $estadoValor = $contrato->estado->value;
                        @endphp
                        <div class="ag-contratos__fila" role="row">
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
                                @if ($contrato->ventanas->isEmpty())
                                    <x-atoms.badge variant="neutral">
                                        {{ __('comercial.contratos.ventana_dia_completo') }}
                                    </x-atoms.badge>
                                @else
                                    @foreach ($contrato->ventanas as $ventana)
                                        <div class="ag-contratos__mono">
                                            {{ __('comercial.contratos.ventana_rango', ['inicio' => substr((string) $ventana->hora_inicio, 0, 5), 'fin' => substr((string) $ventana->hora_fin, 0, 5)]) }}
                                        </div>
                                    @endforeach
                                @endif
                            </span>

                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                    {{ __('comercial.contrato.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-contratos__acciones">
                                @puede('comercial.contrato.editar')
                                    <x-atoms.button href="{{ route('panel.contratos.edit', $contrato) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('comercial.contratos.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.contrato.cambiar_estado')
                                    @if ($estadoValor === 'borrador' || $estadoValor === 'vigente')
                                        @php
                                            $siguienteEstado = $estadoValor === 'borrador' ? 'vigente' : 'finalizado';
                                            $etiquetaSiguiente = $estadoValor === 'borrador' ? 'accion_activar' : 'accion_finalizar';
                                            $confirmacionSiguiente = $estadoValor === 'borrador' ? 'confirmar_activar' : 'confirmar_finalizar';
                                        @endphp
                                        <form
                                            method="POST"
                                            action="{{ route('panel.contratos.cambiar-estado', $contrato) }}"
                                            onsubmit="return confirm('{{ __('comercial.contratos.'.$confirmacionSiguiente) }}')"
                                        >
                                            @csrf
                                            <input type="hidden" name="estado" value="{{ $siguienteEstado }}">
                                            <x-atoms.button type="submit" variant="outline" size="sm" icon="check_circle">
                                                {{ __('comercial.contratos.'.$etiquetaSiguiente) }}
                                            </x-atoms.button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('panel.contratos.cambiar-estado', $contrato) }}"
                                            onsubmit="return confirm('{{ __('comercial.contratos.confirmar_cancelar') }}')"
                                        >
                                            @csrf
                                            <input type="hidden" name="estado" value="cancelado">
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="cancel">
                                                {{ __('comercial.contratos.accion_cancelar') }}
                                            </x-atoms.button>
                                        </form>
                                    @endif
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($contratos->hasPages())
                    <nav class="ag-contratos__paginacion" aria-label="{{ __('comercial.contratos.paginacion_aria') }}">
                        @if (! $contratos->onFirstPage())
                            <x-atoms.button href="{{ $contratos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.contratos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-contratos__paginacion-info">
                            {{ __('comercial.contratos.paginacion_info', ['actual' => $contratos->currentPage(), 'total' => $contratos->lastPage()]) }}
                        </span>

                        @if ($contratos->hasMorePages())
                            <x-atoms.button href="{{ $contratos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.contratos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
