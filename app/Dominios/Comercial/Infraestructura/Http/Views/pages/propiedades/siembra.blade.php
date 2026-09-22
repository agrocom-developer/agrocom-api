{{--
    Page: propiedades/siembra (GET/POST /panel/propiedades/{propiedad}/siembra,
    panel.propiedades.siembra[.guardar])
    Qué cultivo tiene cada lote de la propiedad, por campaña, y en qué etapa
    está (HU-48, tarea 71, etapa 3; ADR 0015 punto 4). Se entra desde la ficha
    de la propiedad o la de un lote, y desde el modal de lotes del contrato;
    gateada por `comercial.propiedad.editar` (no es un ABM propio).

    21/9/2026 (pedido directo): arquetipo Formulario de la guía (§6.3) y carga
    por SECTORES — un cultivo, su etapa y sus fechas, más los lotes que lo
    comparten—: una fila por lote no sirve para una propiedad de mil lotes.
    Cada sector elige sus lotes en el modal de esta página, que solo ofrece los
    que siguen libres (más los que el sector ya tiene).

    ALTA vs. EDICIÓN (§6.3.1): sin siembra guardada en la campaña es un alta y
    va sin columna lateral; con siembra es una edición y muestra su resumen.
    Ninguna sección se esconde (§6.3.5): sin campaña o sin lotes, la sección de
    sectores muestra su vacío y el guardado queda deshabilitado.

    Datos esperados (ver SiembraController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad|null, con `cliente` y `lotes` en orden natural):
      null cuando se entra por `GET /panel/siembra` sin propiedad elegida (desde
      la ficha de un cultivo) — la sección de sectores muestra su vacío.
    - $clientes, $clienteId, $propiedadesDelCliente: los selects de cliente y
      propiedad; las propiedades son las del cliente elegido.
    - $cultivoSugeridoId (int|null): el cultivo desde cuya ficha se llegó; es
      el que trae puesto el sector en blanco.
    - $campanias (Collection<int, string>): id => código, todo el catálogo.
    - $campaniaId (int|null): la campaña que se está mostrando.
    - $esEdicion (bool): la campaña ya tiene siembra guardada en esta propiedad.
    - $sectores (list<array>): las siembras guardadas, agrupadas.
    - $lotesCatalogo (list<array{id, codigo, hectareas}>): para el selector.
    - $cultivosDisponibles, $etapasDisponibles: opciones de los selects.
    - $resumenSiembra (array): `tieneDatos` + `items` del aside, de lo guardado.

    Cambiar de cliente, de propiedad o de campaña es un GET nuevo (lo dispara
    `siembra-form.js`): los selects van como `*_ver`, que el servidor ignora;
    lo que se guarda es siempre la propiedad de la ruta y el `campania_id`
    oculto, los de los sectores que se están viendo.
--}}
@php
    $hayPropiedad = $propiedad !== null;
    $hayCampania = $campaniaId !== null;
    $hayLotes = $hayPropiedad && $propiedad->lotes->isNotEmpty();
    $puedeGuardar = $hayCampania && $hayLotes;
    $tituloPagina = $hayPropiedad
        ? __('comercial.siembra.titulo', ['propiedad' => $propiedad->nombre])
        : __('comercial.siembra.titulo_sin_propiedad');

    // Tras un guardado rechazado mandan los sectores que se enviaron; en un
    // alta se arranca con un sector en blanco, listo para completar.
    $sectoresVista = array_values(old('sectores', $sectores));
    if ($sectoresVista === [] && ! $esEdicion) {
        // Si se llegó desde la ficha de un cultivo, el sector ya lo trae puesto.
        $sectoresVista = [['cultivo_id' => $cultivoSugeridoId]];
    }
@endphp
<x-templates.panel-shell :title="$tituloPagina" :tema="$tema">
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
        :vista-actual="$tituloPagina"
    >
        <div class="ag-siembra">
            <x-organisms.page-header
                :title="$tituloPagina"
                :subtitle="__('comercial.siembra.subtitulo')"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver
                        :href="$hayPropiedad ? route('panel.propiedades.edit', $propiedad) : route('panel.propiedades.index')"
                        :label="$hayPropiedad ? __('comercial.siembra.volver') : __('comercial.siembra.volver_listado')"
                    />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('campania_id') || $errors->has('sectores'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('campania_id') ?: $errors->first('sectores') }}
                </x-molecules.alert-strip>
            @elseif ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ __('comercial.siembra.error_general') }}
                </x-molecules.alert-strip>
            @endif

            <form
                method="POST"
                action="{{ $hayPropiedad ? route('panel.propiedades.siembra.guardar', $propiedad) : '#' }}"
                class="ag-siembra-form"
                novalidate
                data-ag-siembra-form
                data-ag-siembra-url-propiedad="{{ route('panel.propiedades.siembra', '__PROPIEDAD__') }}"
                data-ag-siembra-url-cliente="{{ route('panel.siembra') }}"
                @if ($cultivoSugeridoId !== null) data-ag-siembra-cultivo="{{ $cultivoSugeridoId }}" @endif
                data-texto-sector="{{ __('comercial.siembra.sector_titulo') }}"
                data-texto-sector-resumen="{{ __('comercial.siembra.sector_resumen') }}"
                data-texto-sector-resumen-uno="{{ __('comercial.siembra.sector_resumen_uno') }}"
                data-texto-sector-vacio="{{ __('comercial.siembra.sector_sin_lotes') }}"
                data-texto-fichas-mas="{{ __('comercial.siembra.sector_fichas_mas') }}"
                data-icono-ficha="grid_view"
                data-texto-libres="{{ __('comercial.siembra.lotes_libres') }}"
            >
                @csrf
                <input type="hidden" name="campania_id" value="{{ $campaniaId }}">
                <script type="application/json" data-ag-siembra-lotes>@json($lotesCatalogo)</script>

                <x-molecules.form-layout>
                    <x-molecules.form-section
                        :title="__('comercial.siembra.seccion_campania')"
                        :count="__('comercial.siembra.campos_contador', ['cantidad' => 3])"
                    >
                        {{-- Cliente y propiedad son selects, como la campaña (21/9/2026):
                             cambiar cualquiera recarga la pantalla (`siembra-form.js`).
                             Viajan como `*_ver`, que el servidor ignora: se guarda
                             siempre en la propiedad de la ruta. --}}
                        <x-atoms.select
                            name="cliente_ver"
                            id="cliente_ver"
                            :label="__('comercial.siembra.campo_cliente')"
                            :placeholder="__('comercial.siembra.campo_cliente_placeholder')"
                            :options="$clientes"
                            :value="$clienteId"
                            required
                            data-ag-siembra-cliente
                        />

                        <x-atoms.select
                            name="propiedad_ver"
                            id="propiedad_ver"
                            :label="__('comercial.siembra.campo_propiedad')"
                            :placeholder="__('comercial.siembra.campo_propiedad_placeholder')"
                            :help="$clienteId === null ? __('comercial.siembra.campo_propiedad_sin_cliente') : ($propiedadesDelCliente->isEmpty() ? __('comercial.siembra.campo_propiedad_vacio') : null)"
                            :options="$propiedadesDelCliente"
                            :value="$propiedad?->id"
                            :disabled="$propiedadesDelCliente->isEmpty()"
                            required
                            data-ag-siembra-propiedad
                        />

                        {{-- La campaña solo se ELIGE (21/9/2026, corrección del dueño): una
                             siembra depende de la campaña, pero registrar una siembra no es
                             motivo para crear una. Sin alta rápida pegada al select. --}}
                        <x-atoms.select
                            name="campania_ver"
                            id="campania_ver"
                            :label="__('comercial.siembra.campo_campania')"
                            :placeholder="__('comercial.siembra.campo_campania_placeholder')"
                            :help="$campanias->isEmpty() ? __('comercial.siembra.sin_campanias') : __('comercial.siembra.campo_campania_ayuda')"
                            :options="$campanias"
                            :value="$campaniaId"
                            :disabled="$campanias->isEmpty()"
                            required
                            data-ag-siembra-campania
                        />
                    </x-molecules.form-section>

                    <x-molecules.form-section
                        :title="__('comercial.siembra.seccion_sectores')"
                        :count="__('comercial.siembra.lotes_contador', ['cantidad' => $hayPropiedad ? $propiedad->lotes->count() : 0])"
                    >
                        @if (! $hayPropiedad)
                            <div class="ag-form-section__field--full">
                                <x-molecules.empty-state
                                    icon="home_work"
                                    :title="__('comercial.siembra.sin_propiedad_titulo')"
                                    :detail="__('comercial.siembra.sin_propiedad_detalle')"
                                />
                            </div>
                        @elseif (! $hayLotes)
                            <div class="ag-form-section__field--full">
                                <x-molecules.empty-state
                                    icon="grid_view"
                                    :title="__('comercial.siembra.sin_lotes_titulo')"
                                    :detail="__('comercial.siembra.sin_lotes_detalle')"
                                >
                                    @puede('comercial.lote.crear')
                                        <x-slot:action>
                                            <x-atoms.button :href="route('panel.propiedades.lotes.generar', $propiedad)" variant="outline" icon="add">
                                                {{ __('comercial.siembra.sin_lotes_accion') }}
                                            </x-atoms.button>
                                        </x-slot:action>
                                    @endpuede
                                </x-molecules.empty-state>
                            </div>
                        @elseif (! $hayCampania)
                            <div class="ag-form-section__field--full">
                                <x-molecules.empty-state
                                    icon="event_busy"
                                    :title="__('comercial.siembra.sin_campania_titulo')"
                                    :detail="__('comercial.siembra.sin_campania_detalle')"
                                />
                            </div>
                        @else
                            <div class="ag-form-section__field--full ag-siembra__intro">
                                <p class="ag-siembra__ayuda">{{ __('comercial.siembra.sectores_ayuda') }}</p>
                                <p class="ag-siembra__libres" data-ag-siembra-libres aria-live="polite"></p>
                            </div>

                            <div class="ag-form-section__field--full ag-siembra-form__sectores" data-ag-siembra-sectores>
                                @foreach ($sectoresVista as $indice => $sector)
                                    @include('comercial::pages.propiedades._siembra-sector', [
                                        'indice' => $indice,
                                        'sector' => $sector,
                                        'cultivosDisponibles' => $cultivosDisponibles,
                                        'etapasDisponibles' => $etapasDisponibles,
                                    ])
                                @endforeach
                            </div>

                            {{-- Sin lotes libres no hay nada que poner en otro sector:
                                 `siembra-form.js` esconde este botón. --}}
                            <div class="ag-form-section__field--full" data-ag-siembra-sector-agregar-fila>
                                <x-atoms.button type="button" variant="outline" icon="add" data-ag-siembra-sector-agregar>
                                    {{ __('comercial.siembra.sector_agregar') }}
                                </x-atoms.button>
                            </div>

                            {{-- Molde de un sector nuevo: `siembra-form.js` lo clona y reemplaza
                                 `__INDICE__`. Un `<template>` no se envía con el formulario. --}}
                            <template data-ag-siembra-sector-molde>
                                @include('comercial::pages.propiedades._siembra-sector', [
                                    'indice' => '__INDICE__',
                                    'sector' => ['cultivo_id' => $cultivoSugeridoId],
                                    'cultivosDisponibles' => $cultivosDisponibles,
                                    'etapasDisponibles' => $etapasDisponibles,
                                ])
                            </template>
                        @endif
                    </x-molecules.form-section>

                    @if ($esEdicion)
                        <x-slot:aside>
                            <x-molecules.summary-card :title="__('comercial.siembra.resumen_titulo')" :items="$resumenSiembra['items']" />
                        </x-slot:aside>
                    @endif
                </x-molecules.form-layout>

                <x-organisms.form-actions-bar :status="__('comercial.siembra.estado_form')">
                    <x-slot:actions>
                        <x-molecules.boton-volver :href="$hayPropiedad ? route('panel.propiedades.edit', $propiedad) : route('panel.propiedades.index')" cancelar />
                        <x-atoms.button type="submit" variant="primary" icon="save" :disabled="! $puedeGuardar">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>

            @include('comercial::pages.propiedades._siembra-modal-lotes')
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
