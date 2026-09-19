{{--
    Page: ordenes/show (GET /panel/ordenes/{orden}, panel.ordenes.show)
    Detalle de solo lectura de una orden de aplicación (homogeneización
    17/9/2026): primera pantalla del arquetipo Detalle del panel (§6.4 de
    docs/diseno/guia_pantalla_panel.md documenta el patrón que arma esta vista).

    Por qué hace falta: mientras la orden es `emitida`, `edit()` cumple este
    rol; apenas se activa, el link "Editar" se oculta (`Aplicacion/ActualizarOrden`
    exige `emitida`) y no quedaba ningún lugar para volver a ver sus datos.

    Reforma 19/9/2026 (ADR 0022): la orden es UNA aplicación completa del
    contrato — la lista de Lotes es de solo lectura (todos los del contrato) y
    los KPI hablan de las hectáreas contratadas y de "N de M" aplicaciones. El
    encabezado ofrece las acciones que admite el estado: emitida → Editar,
    Activar, Eliminar; vigente → Pausar, Cerrar, Cancelar; pausada → Reanudar,
    Cancelar; consumida/cancelada → ninguna. Pausar y Cancelar piden su motivo
    (y la causa) dentro del modal — ver `_orden-modales.blade.php`. Nada de
    esto lo dispara la app de campo: decide el operador con el informe del
    equipo a la vista.

    Distribución (18/9/2026, pedido explícito): la columna principal lleva lo
    que se lee de corrido — Datos del contrato, Datos de la orden, Lotes; el
    aside pegajoso lleva lo que la acompaña — Avance de asignación,
    Inconvenientes del campo (solo si los hay), Relacionado y Actividad.

    Datos esperados (ver OrdenesController::show()): la cáscara de
    CascaraPanel, más $orden (con `categoriaInsumo` cargada), $contratoLabel,
    $contactoLabel (nullable), $resumenContrato (array nullable),
    $aplicacionesPrevistas (nullable), $lotes (list), $hectareasSolicitadas /
    $hectareasAsignadas (string, ya formateadas), $porcentajeAsignado (int),
    $equiposAsignados (int), $actividad (list), $vinculos (list),
    $inconvenientes (array{incidencias: int, pausas: int, ...}, ver
    `Aplicacion/ResumenDeOrdenes`) y los permisos $puedeEditar / $puedeActivar
    / $puedePausar (pausar y reanudar) / $puedeCerrar / $puedeCancelar /
    $puedeEliminar (bool).

    Gateada por `operaciones.orden.ver` — mismo permiso que `index()`/`edit()`.

    Estilos en resources/css/pages/ordenes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $estadoValor = $orden->estado->value;
    $variantePorEstado = [
        'emitida' => 'neutral',
        'vigente' => 'success',
        'pausada' => 'warning',
        'consumida' => 'info',
        'cancelada' => 'danger',
        'vencida' => 'danger',
    ];
    $tipoInsumo = $orden->categoriaInsumo?->tipo_insumo?->value;
    $dosisTexto = $orden->kilos_por_vuelo !== null
        ? __('operaciones.ordenes.dosis_kilos_por_vuelo', ['cantidad' => number_format((float) $orden->kilos_por_vuelo, 2, ',', '.')])
        : ($orden->litros_ha !== null
            ? __('operaciones.ordenes.dosis_litros_ha', ['cantidad' => number_format((float) $orden->litros_ha, 2, ',', '.')])
            : '—');
    $tieneInconvenientes = ($inconvenientes['incidencias'] + $inconvenientes['pausas']) > 0;
    $sufijoAcciones = 'detalle-'.$orden->id;
    $aplicacionesCompletas = $aplicacionesPrevistas !== null && $orden->nro_aplicacion >= $aplicacionesPrevistas;
    $equiposCompletos = $equiposAsignados >= $orden->cantidad_equipos_necesarios;
    $aplicacionesEstado = $aplicacionesCompletas ? 'success' : null;
    $equiposEstado = $equiposCompletos ? 'success' : ($estadoValor === 'vigente' ? 'warning' : null);
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
    >
        <div class="ag-ordenes-detalle">
            <x-organisms.page-header
                :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
                :subtitle="__('operaciones.ordenes.detalle_subtitulo', ['cliente' => $contratoLabel, 'fecha' => $orden->fecha_emision->format('d/m/Y')])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                        {{ __('operaciones.estado.'.$estadoValor) }}
                    </x-atoms.badge>
                    @if ($tieneInconvenientes)
                        <x-atoms.badge variant="warning" icon="warning">
                            {{ __('operaciones.ordenes.badge_inconvenientes') }}
                        </x-atoms.badge>
                    @endif
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.volver')" />

                    @if ($puedeEditar && $estadoValor === 'emitida')
                        <x-atoms.button :href="route('panel.ordenes.edit', $orden)" variant="primary" icon="edit">
                            {{ __('operaciones.ordenes.editar') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeActivar && $estadoValor === 'emitida')
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-activar-modal-'.$sufijoAcciones" variant="success-outline" icon="check_circle">
                            {{ __('operaciones.ordenes.activar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedePausar && $estadoValor === 'vigente')
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-pausar-modal-'.$sufijoAcciones" variant="warning-outline" icon="pause_circle">
                            {{ __('operaciones.ordenes.pausar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedePausar && $estadoValor === 'pausada')
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-reanudar-modal-'.$sufijoAcciones" variant="success-outline" icon="play_circle">
                            {{ __('operaciones.ordenes.reanudar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeCerrar && $estadoValor === 'vigente')
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-cerrar-modal-'.$sufijoAcciones" variant="info-outline" icon="done_all">
                            {{ __('operaciones.ordenes.cerrar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeCancelar && ($estadoValor === 'vigente' || $estadoValor === 'pausada'))
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-cancelar-modal-'.$sufijoAcciones" variant="danger-outline" icon="cancel">
                            {{ __('operaciones.ordenes.cancelar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeEliminar && $estadoValor === 'emitida')
                        <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-eliminar-modal-'.$sufijoAcciones" variant="danger-outline" icon="delete">
                            {{ __('operaciones.ordenes.eliminar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            {{-- Forms + modales de las acciones (fuera del page-header, ver el
                 docblock de `_orden-modales.blade.php`). --}}
            @include('operaciones::pages.ordenes._orden-modales', ['orden' => $orden, 'contexto' => 'detalle-'])

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

            <div class="ag-ordenes-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.aside_hectareas_contratadas')"
                    icon="landscape"
                    :value="$hectareasSolicitadas"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.campo_categoria_insumo')"
                    icon="science"
                    :value="$dosisTexto"
                    state="distintivo-1"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_aplicaciones')"
                    icon="repeat"
                    :value="$orden->nro_aplicacion"
                    :value-suffix="$aplicacionesPrevistas !== null ? __('operaciones.ordenes.kpi_aplicaciones_sufijo', ['total' => $aplicacionesPrevistas]) : null"
                    :state="$aplicacionesEstado"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_equipos_necesarios')"
                    icon="groups"
                    :value="$orden->cantidad_equipos_necesarios"
                    :foot="__('operaciones.ordenes.kpi_equipos_asignados_pie', ['asignados' => $equiposAsignados, 'necesarios' => $orden->cantidad_equipos_necesarios])"
                    :foot-tone="$equiposCompletos ? 'success' : ($estadoValor === 'vigente' ? 'warning' : 'muted')"
                    :state="$equiposEstado"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.ordenes.seccion_datos_contrato')">
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ $contratoLabel }}</p>
                    </div>
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contacto') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ $contactoLabel ?? __('operaciones.ordenes.campo_contacto_placeholder') }}</p>
                    </div>
                    @if ($resumenContrato)
                        <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato">
                            <img class="ag-ordenes-form__logo" src="{{ $resumenContrato['logo_url'] ?? asset('images/logo-placeholder.png') }}" alt="">
                            <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_cliente') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['cliente'] }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_propiedades') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['propiedades'] === [] ? '—' : implode(', ', $resumenContrato['propiedades']) }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_aplicaciones') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['aplicaciones_previstas'] }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_hectareas') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['hectareas_contratadas'] }} ha</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="success" :title="__('operaciones.ordenes.seccion_datos')" :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 4])">
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_tipo_insumo') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ $tipoInsumo !== null ? __('operaciones.tipo_insumo.'.$tipoInsumo) : '—' }}</p>
                    </div>
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_categoria_insumo') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ $orden->categoriaInsumo?->nombre ?? '—' }}</p>
                    </div>
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_tipo_aplicacion') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value) }}</p>
                    </div>
                    <div class="ag-ordenes-detalle__campo">
                        <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_fecha_emision') }}</p>
                        <p class="ag-ordenes-detalle__campo-valor">{{ $orden->fecha_emision->format('d/m/Y') }}</p>
                    </div>
                    @if ($orden->observaciones)
                        <div class="ag-form-section__field--full ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_observaciones') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->observaciones }}</p>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('operaciones.ordenes.seccion_lotes')" :count="__('operaciones.ordenes.lotes_contador', ['cantidad' => count($lotes), 'hectareas' => $hectareasSolicitadas])">
                    <div class="ag-form-section__field--full">
                        <x-molecules.index-table columns="1fr 8rem">
                            <x-slot:head>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_hectareas_lote') }}</span>
                            </x-slot:head>
                            @foreach ($lotes as $lote)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">{{ $lote['label'] }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $lote['hectareas_solicitadas'], 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.progress-meter
                        :title="__('operaciones.ordenes.avance_titulo')"
                        :percent="$porcentajeAsignado"
                        :summary-label="__('operaciones.ordenes.avance_resumen', ['asignadas' => $hectareasAsignadas, 'solicitadas' => $hectareasSolicitadas])"
                    />

                    @if ($tieneInconvenientes)
                        <x-molecules.form-section accent="warning" :title="__('operaciones.ordenes.inconvenientes_seccion')">
                            <div class="ag-form-section__field--full">
                                <p class="ag-ordenes-detalle__campo-valor">
                                    {{ trans_choice('operaciones.ordenes.inconvenientes_incidencias', $inconvenientes['incidencias'], ['cantidad' => $inconvenientes['incidencias']]) }}
                                    ·
                                    {{ trans_choice('operaciones.ordenes.inconvenientes_pausas', $inconvenientes['pausas'], ['cantidad' => $inconvenientes['pausas']]) }}
                                </p>
                                <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.inconvenientes_detalle') }}</p>
                            </div>
                        </x-molecules.form-section>
                    @endif

                    @if (count($vinculos))
                        <x-molecules.form-section accent="alert" :title="__('operaciones.ordenes.seccion_vinculos')">
                            <div class="ag-form-section__field--full ag-ordenes-detalle__vinculos">
                                @foreach ($vinculos as $vinculo)
                                    <x-molecules.link-row
                                        :href="$vinculo['href']"
                                        :icon="$vinculo['icon']"
                                        :title="$vinculo['title']"
                                        :meta="$vinculo['meta']"
                                        :tone="$vinculo['tone']"
                                    />
                                @endforeach
                            </div>
                        </x-molecules.form-section>
                    @endif

                    <x-molecules.form-section accent="distintivo-2" :title="__('operaciones.ordenes.seccion_actividad')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
