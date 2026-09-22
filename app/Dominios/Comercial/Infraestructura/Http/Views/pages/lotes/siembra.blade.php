{{--
    Page: lotes/siembra (GET/POST /panel/lotes/{lote}/siembra,
    panel.lotes.siembra[.guardar]) — 21/9/2026, pedido directo.
    La siembra de UN lote, a la que se entra desde su ficha: es el formulario de
    un sector sin la elección de lotes (el lote es único), con cliente,
    propiedad, campaña y lote de SOLO LECTURA. La siembra de toda la propiedad,
    por sectores, es `propiedades/siembra`; desde acá se llega con el botón de
    la cabecera.

    Es también donde se cargan hectáreas sembradas menores que las del lote:
    un sector siembra el lote entero.

    Datos esperados (ver SiembraLoteController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $lote (Lote, con `propiedad.cliente`).
    - $campania (object{id, codigo}|null): la pedida o la más reciente; null si
      todavía no hay ninguna.
    - $siembra (LoteCampania|null): la del lote en esa campaña, si existe.
    - $cultivosDisponibles (Collection<int, string>), $etapasDisponibles
      (Collection<string, string>).

    Dejar el cultivo en blanco y guardar quita la siembra del lote en esa
    campaña (baja lógica). Ninguna sección se esconde (§6.3.5): sin campaña, la
    sección de siembra muestra su vacío y el guardado queda deshabilitado.
--}}
@php
    $hayCampania = $campania !== null;
    $hectareasLote = number_format((float) $lote->hectareas, 2, ',', '.');
@endphp
<x-templates.panel-shell :title="__('comercial.siembra.lote_titulo', ['lote' => $lote->codigo])" :tema="$tema">
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
        :vista-actual="__('comercial.siembra.lote_titulo', ['lote' => $lote->codigo])"
    >
        <div class="ag-siembra">
            <x-organisms.page-header
                :title="__('comercial.siembra.lote_titulo', ['lote' => $lote->codigo])"
                :subtitle="__('comercial.siembra.lote_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button
                        :href="route('panel.propiedades.siembra', ['propiedad' => $lote->propiedad, 'campania_id' => $campania?->id])"
                        variant="outline"
                        icon="grid_view"
                    >
                        {{ __('comercial.siembra.lote_ver_propiedad') }}
                    </x-atoms.button>
                    <x-molecules.boton-volver :href="route('panel.lotes.edit', $lote)" :label="__('comercial.siembra.lote_volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('campania_id'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('campania_id') }}
                </x-molecules.alert-strip>
            @endif

            <form method="POST" action="{{ route('panel.lotes.siembra.guardar', $lote) }}" class="ag-siembra-form" novalidate>
                @csrf
                <input type="hidden" name="campania_id" value="{{ $campania?->id }}">

                <x-molecules.form-section
                    :title="__('comercial.siembra.seccion_campania')"
                    :count="__('comercial.siembra.campos_contador', ['cantidad' => 4])"
                >
                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.siembra.campo_cliente') }}</span>
                        <p class="ag-siembra__solo-lectura">{{ $lote->propiedad->cliente->razon_social }}</p>
                    </div>

                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.siembra.campo_propiedad') }}</span>
                        <p class="ag-siembra__solo-lectura">{{ $lote->propiedad->nombre }}</p>
                    </div>

                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.siembra.campo_campania') }}</span>
                        <p class="ag-siembra__solo-lectura">{{ $campania?->codigo ?? __('comercial.siembra.lote_sin_campania') }}</p>
                    </div>

                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.siembra.campo_lote') }}</span>
                        <p class="ag-siembra__solo-lectura">
                            {{ $lote->codigo }}
                            <span class="ag-siembra__dato-secundario">{{ __('comercial.siembra.lote_hectareas_valor', ['cantidad' => $hectareasLote]) }}</span>
                        </p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section
                    :title="__('comercial.siembra.lote_seccion')"
                    :count="__('comercial.siembra.campos_contador', ['cantidad' => 5])"
                >
                    @if (! $hayCampania)
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state
                                icon="event_busy"
                                :title="__('comercial.siembra.sin_campania_titulo')"
                                :detail="__('comercial.siembra.sin_campanias')"
                            />
                        </div>
                    @else
                        <x-atoms.select
                            name="cultivo_id"
                            :label="__('comercial.siembra.campo_cultivo')"
                            :help="__('comercial.siembra.lote_cultivo_ayuda')"
                            :options="$cultivosDisponibles"
                            :value="old('cultivo_id', $siembra?->cultivo_id)"
                            :placeholder="__('comercial.siembra.lote_cultivo_placeholder')"
                            :error="$errors->first('cultivo_id')"
                        />

                        <x-atoms.select
                            name="etapa_cultivo"
                            :label="__('comercial.siembra.campo_etapa')"
                            :help="__('comercial.siembra.lote_etapa_ayuda')"
                            :options="$etapasDisponibles"
                            :value="old('etapa_cultivo', $siembra?->etapa_cultivo?->value)"
                            :placeholder="__('comercial.siembra.campo_etapa_placeholder')"
                            :error="$errors->first('etapa_cultivo')"
                        />

                        <x-atoms.input
                            type="number"
                            name="hectareas_sembradas"
                            :label="__('comercial.siembra.campo_hectareas_sembradas')"
                            :help="__('comercial.siembra.lote_hectareas_ayuda', ['cantidad' => $hectareasLote])"
                            :value="old('hectareas_sembradas', $siembra?->hectareas_sembradas ?? $lote->hectareas)"
                            min="0.01"
                            max="{{ $lote->hectareas }}"
                            step="0.01"
                            :error="$errors->first('hectareas_sembradas')"
                        />

                        <x-atoms.date
                            name="fecha_siembra"
                            :label="__('comercial.siembra.campo_fecha_siembra')"
                            :value="old('fecha_siembra', $siembra?->fecha_siembra?->format('Y-m-d'))"
                            :error="$errors->first('fecha_siembra')"
                        />

                        <x-atoms.date
                            name="fecha_cosecha_estimada"
                            :label="__('comercial.siembra.campo_fecha_cosecha_estimada')"
                            :value="old('fecha_cosecha_estimada', $siembra?->fecha_cosecha_estimada?->format('Y-m-d'))"
                            :error="$errors->first('fecha_cosecha_estimada')"
                        />
                    @endif
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('comercial.siembra.estado_form')">
                    <x-slot:actions>
                        <x-molecules.boton-volver :href="route('panel.lotes.edit', $lote)" cancelar />
                        <x-atoms.button type="submit" variant="primary" icon="save" :disabled="! $hayCampania">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
