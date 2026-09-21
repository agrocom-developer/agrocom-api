{{--
    Page: rendiciones/show (GET /panel/rendiciones/{rendicion}, panel.rendiciones.show)
    Detalle de una rendición (HU-34, tarea 48): arquetipo Detalle, §6 de
    docs/diseno/guia_pantalla_panel.md — resumen → gastos asociados → gastos
    disponibles (si rendición está abierta). Transiciones de estado (presentar,
    aprobar) con gatekeeping por permiso y validaciones internas del backend.

    Datos esperados (ver RendicionesController::show()): la cáscara de
    CascaraPanel, más:
    - $rendicion (Rendicion).
    - $gastosAsociados (Collection<Gasto>): ordenados desc por fecha.
    - $gastosDisponibles (Collection<Gasto>): vacía si rendición no está
      Abierta; 100 primeros de la misma base, sin rendicion_id.
    - $etiquetasBase / $etiquetasJefeCampo (array<int, string>): una entrada
      cada una (solo de esta rendición).
    - $personaId (int|null): persona del usuario autenticado — para decidir
      si mostrar aviso "vos sos quien rindió, no podés aprobar".
    - $puedeCrear / $puedePresentar / $puedeAprobar (bool): permisos.

    Gateada por `finanzas.rendicion.ver`. Los botones "Presentar" y "Aprobar"
    se ocultan o deshabilitan según permisos y estado; el backend revalida
    con `abort(403)` si se fuerza.

    Estilos en resources/css/pages/rendiciones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.rendiciones.detalle_titulo', ['id' => $rendicion->id])" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.titulo')"
    >
        <div class="ag-rendicion-detalle">
            <x-atoms.button :href="route('panel.rendiciones.index')" variant="text" size="sm" icon="arrow_back">
                {{ __('finanzas.rendiciones.volver') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('finanzas.rendiciones.detalle_titulo', ['id' => $rendicion->id])"
                :subtitle="__('finanzas.rendiciones.subtitulo')"
            >
                @if ($puedePresentar && $rendicion->estado->value === 'abierta' && ! $gastosAsociados->isEmpty())
                    <x-slot:actions>
                        <form
                            method="POST"
                            action="{{ route('panel.rendiciones.presentar', $rendicion) }}"
                            onsubmit="return confirm('{{ __('finanzas.rendiciones.confirmar_presentar') }}')"
                            style="display: inline;"
                        >
                            @csrf
                            <x-atoms.button type="submit" variant="outline" icon="send">
                                {{ __('finanzas.rendiciones.presentar_accion') }}
                            </x-atoms.button>
                        </form>
                        @if ($puedeAprobar && $rendicion->estado->value === 'presentada')
                            <form
                                method="POST"
                                action="{{ route('panel.rendiciones.aprobar', $rendicion) }}"
                                onsubmit="return confirm('{{ __('finanzas.rendiciones.confirmar_aprobar') }}')"
                                style="display: inline;"
                            >
                                @csrf
                                <x-atoms.button
                                    type="submit"
                                    variant="primary"
                                    icon="check_circle"
                                    :disabled="$personaId === (int) $rendicion->jefe_campo_id"
                                    :title="$personaId === (int) $rendicion->jefe_campo_id ? __('finanzas.rendiciones.no_puede_aprobar_propia') : ''"
                                >
                                    {{ __('finanzas.rendiciones.aprobar_accion') }}
                                </x-atoms.button>
                            </form>
                        @endif
                    </x-slot:actions>
                @elseif ($puedeAprobar && $rendicion->estado->value === 'presentada')
                    <x-slot:actions>
                        @if ($personaId !== (int) $rendicion->jefe_campo_id)
                            <form
                                method="POST"
                                action="{{ route('panel.rendiciones.aprobar', $rendicion) }}"
                                onsubmit="return confirm('{{ __('finanzas.rendiciones.confirmar_aprobar') }}')"
                                style="display: inline;"
                            >
                                @csrf
                                <x-atoms.button type="submit" variant="primary" icon="check_circle">
                                    {{ __('finanzas.rendiciones.aprobar_accion') }}
                                </x-atoms.button>
                            </form>
                        @endif
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-rendicion-detalle__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @error('estado')
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-rendicion-detalle__aviso">
                    {{ $message }}
                </x-molecules.alert-strip>
            @enderror

            @error('gasto')
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-rendicion-detalle__aviso">
                    {{ $message }}
                </x-molecules.alert-strip>
            @enderror

            @if ($puedePresentar && $rendicion->estado->value === 'abierta' && $gastosAsociados->isEmpty())
                <x-molecules.alert-strip variant="info" icon="info" class="ag-rendicion-detalle__aviso">
                    {{ __('finanzas.rendiciones.no_puede_presentar_sin_gastos') }}
                </x-molecules.alert-strip>
            @endif

            @if ($personaId === (int) $rendicion->jefe_campo_id && $puedeAprobar)
                <x-molecules.alert-strip variant="warning" icon="warning" class="ag-rendicion-detalle__aviso">
                    {{ __('finanzas.rendiciones.no_puede_aprobar_propia') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-rendicion-detalle__resumen">
                <span class="ag-rendicion-detalle__campo">
                    <strong>{{ __('finanzas.rendiciones.col_fecha') }}</strong>
                    {{ $rendicion->fecha->format('d/m/Y') }}
                </span>
                <span class="ag-rendicion-detalle__campo">
                    <strong>{{ __('finanzas.rendiciones.col_base') }}</strong>
                    {{ $etiquetasBase[$rendicion->base_id] ?? "#{$rendicion->base_id}" }}
                </span>
                <span class="ag-rendicion-detalle__campo">
                    <strong>{{ __('finanzas.rendiciones.col_jefe_campo') }}</strong>
                    {{ $etiquetasJefeCampo[$rendicion->jefe_campo_id] ?? "#{$rendicion->jefe_campo_id}" }}
                </span>
                @if ($rendicion->descripcion !== null)
                    <span class="ag-rendicion-detalle__campo">
                        <strong>{{ __('finanzas.rendiciones.campo_descripcion') }}</strong>
                        {{ $rendicion->descripcion }}
                    </span>
                @endif
                <span class="ag-rendicion-detalle__campo">
                    <strong>{{ __('finanzas.rendiciones.detalle_monto') }}</strong>
                    {{ __('finanzas.rendiciones.monto_valor', ['monto' => $rendicion->monto]) }}
                </span>
                <span class="ag-rendicion-detalle__campo">
                    <strong>{{ __('finanzas.rendiciones.col_estado') }}</strong>
                    <x-atoms.badge :variant="$rendicion->estado->value === 'abierta' ? 'secondary' : 'success'">
                        {{ __("finanzas.rendiciones.estado.{$rendicion->estado->value}") }}
                    </x-atoms.badge>
                </span>
            </div>

            @if (! $gastosAsociados->isEmpty())
                <div class="ag-rendicion-detalle__seccion">
                    <h2 class="ag-rendicion-detalle__titulo">{{ __('finanzas.rendiciones.gastos_asociados_titulo') }}</h2>
                    <div class="ag-rendicion-detalle__tabla" role="table">
                        <div class="ag-rendicion-detalle__head" role="row">
                            <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                            <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                            <span role="columnheader">{{ __('finanzas.rendiciones.col_monto') }}</span>
                        </div>

                        @foreach ($gastosAsociados as $gasto)
                            <div class="ag-rendicion-detalle__fila" role="row">
                                <span role="cell" class="ag-rendicion-detalle__cifra">{{ $gasto->fecha->format('d/m/Y') }}</span>
                                <span role="cell">{{ optional($gasto->rubro)->nombre ?? "#{$gasto->rubro_id}" }}</span>
                                <span role="cell" class="ag-rendicion-detalle__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => $gasto->monto]) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-molecules.alert-strip variant="info" icon="receipt_long" class="ag-rendicion-detalle__aviso">
                    {{ __('finanzas.rendiciones.gastos_asociados_vacio') }}
                </x-molecules.alert-strip>
            @endif

            @if ($rendicion->estado->value === 'abierta')
                <div class="ag-rendicion-detalle__seccion">
                    <h2 class="ag-rendicion-detalle__titulo">{{ __('finanzas.rendiciones.gastos_disponibles_titulo') }}</h2>

                    @if ($gastosDisponibles->isEmpty())
                        <x-molecules.alert-strip variant="info" icon="receipt_long" class="ag-rendicion-detalle__aviso">
                            {{ __('finanzas.rendiciones.gastos_disponibles_vacio') }}
                        </x-molecules.alert-strip>
                    @else
                        <div class="ag-rendicion-detalle__tabla" role="table">
                            <div class="ag-rendicion-detalle__head" role="row">
                                <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                                <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                                <span role="columnheader">{{ __('finanzas.rendiciones.col_monto') }}</span>
                                <span role="columnheader" aria-hidden="true"></span>
                            </div>

                            @foreach ($gastosDisponibles as $gasto)
                                <div class="ag-rendicion-detalle__fila" role="row">
                                    <span role="cell" class="ag-rendicion-detalle__cifra">{{ $gasto->fecha->format('d/m/Y') }}</span>
                                    <span role="cell">{{ optional($gasto->rubro)->nombre ?? "#{$gasto->rubro_id}" }}</span>
                                    <span role="cell" class="ag-rendicion-detalle__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => $gasto->monto]) }}</span>

                                    <span role="cell" class="ag-rendicion-detalle__acciones">
                                        @if ($puedeCrear)
                                            <form
                                                method="POST"
                                                action="{{ route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto]) }}"
                                                style="display: inline;"
                                            >
                                                @csrf
                                                <x-atoms.button type="submit" variant="outline" size="sm" icon="add_circle">
                                                    {{ __('finanzas.rendiciones.asociar_accion') }}
                                                </x-atoms.button>
                                            </form>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
