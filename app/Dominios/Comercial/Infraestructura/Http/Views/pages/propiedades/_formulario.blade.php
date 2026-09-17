{{--
    Partial: formulario de propiedad, compartido por create.blade.php y
    edit.blade.php (ADR 0018) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `clientes/_formulario.blade.php` (tarea 33): las dos páginas arman el
    MISMO formulario; lo único que cambia es contra qué URL/método postea y
    los valores iniciales.

    Ubicación estructurada (adenda 16/9/2026 a ADR 0018 punto 1):
    Departamento → Provincia → Municipio es catálogo cerrado con selects en
    cascada (`propiedades-form.js`, datos embebidos vía `$geografia` — sin
    AJAX). Localidad sigue siendo texto libre, justo después de Municipio
    ("una localidad dentro del municipio"). `color` es paleta curada (ver
    `ColorPropiedad`). Latitud/longitud/geometría NO viven acá: se editan en
    `/panel/propiedades/{propiedad}/mapa` (summary "Coordenadas del mapa" del
    aside, solo edición).

    Aside (§6.3.1, solo edición): 3 summary-card armadas server-side por
    `PropiedadesController::resumenPropiedad()` — Coordenadas del mapa,
    Lotes, Siembra/cultivo actual. Un registro recién creado no tiene aside:
    mismo criterio que Clientes/Campañas.

    Espera:
    - $propiedad (Propiedad|null): null en alta; el modelo, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      clientes activos (ver PropiedadesController::clientesActivos()) — la vista
      no conoce el modelo Cliente.
    - $departamentosDisponibles (Collection<int, string>): id => nombre, los
      9 departamentos — a diferencia de provincia/municipio (que arrancan
      vacíos y los llena `propiedades-form.js` en cascada), departamento no
      depende de nada, así que se renderiza server-side como cualquier otro
      select.
    - $geografia (array{departamentos: list, provincias: list, municipios: list}):
      los 3 niveles del catálogo, embebidos para la cascada de selects.
    - $resumenPropiedad (list<array>, solo edición): las 3 tarjetas del aside.
    - $clienteIdPreseleccionado (int|null, tarea "resumen de cliente"): solo
      en alta, desde `?cliente_id=` (ver PropiedadesController::create()) —
      el atajo "Nueva propiedad" del aside de `panel.clientes.edit` llega acá
      con el cliente ya elegido. `edit()` no lo pasa (`null` por el `??` de
      abajo).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $propiedad !== null;
    $accion = $esEdicion ? route('panel.propiedades.update', $propiedad) : route('panel.propiedades.store');
    // $clienteIdPreseleccionado (tarea "resumen de cliente"): solo llega en
    // alta, desde el atajo del aside de `panel.clientes.edit`.
    $clienteId = old('cliente_id', $propiedad?->cliente_id ?? $clienteIdPreseleccionado ?? '');
    $nombre = old('nombre', $propiedad?->nombre ?? '');
    $hectareas = old('hectareas', $propiedad?->hectareas ?? '');
    $departamentoId = old('departamento_id', $propiedad?->departamento_id ?? '');
    $provinciaId = old('provincia_id', $propiedad?->provincia_id ?? '');
    $municipioId = old('municipio_id', $propiedad?->municipio_id ?? '');
    $localidad = old('localidad', $propiedad?->localidad ?? '');
    $color = old('color', $propiedad?->color ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-propiedades-form" novalidate data-ag-propiedades-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif
    {{-- Alta rápida desde otro formulario (tarea "contratos-lotes", 16/9/2026):
         solo hace falta reenviarlo en el alta — en edición ya llega vía
         sesión (`PropiedadesController::edit()`), no como campo del form. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    {{-- Datos JSON embebidos: catálogo geográfico completo. propiedades-form.js
         filtra provincia/municipio en el cliente al cambiar el select padre —
         sin AJAX, mismo patrón que contratos-form.js para propiedades/lotes. --}}
    <script type="application/json" data-ag-geografia>
        {!! json_encode($geografia) !!}
    </script>

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.propiedades.titulo_editar') : __('comercial.propiedades.titulo_crear')"
        :subtitle="__('comercial.propiedades.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.propiedades.index')" variant="outline" icon="arrow_back">
                {{ __('comercial.propiedades.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
            <x-molecules.form-section
                :title="__('comercial.propiedades.seccion_datos')"
                :count="__('comercial.propiedades.campos_contador', ['cantidad' => 8])"
            >
                <x-atoms.select
                    name="cliente_id"
                    id="cliente_id"
                    :label="__('comercial.propiedades.campo_cliente')"
                    :options="$clientesDisponibles"
                    :value="$clienteId"
                    :placeholder="__('comercial.propiedades.campo_cliente_placeholder')"
                    required
                    :error="$errors->first('cliente_id')"
                />

                <x-atoms.input
                    type="text"
                    name="nombre"
                    :label="__('comercial.propiedades.campo_nombre')"
                    :value="$nombre"
                    required
                    :error="$errors->first('nombre')"
                />

                <x-atoms.input
                    type="number"
                    name="hectareas"
                    :label="__('comercial.propiedades.campo_hectareas')"
                    :value="$hectareas"
                    min="0.01"
                    step="0.01"
                    :help="__('comercial.propiedades.campo_hectareas_ayuda')"
                    :error="$errors->first('hectareas')"
                />

                <x-atoms.select
                    name="departamento_id"
                    id="departamento_id"
                    :label="__('comercial.propiedades.campo_departamento')"
                    :options="$departamentosDisponibles"
                    :value="$departamentoId"
                    :placeholder="__('comercial.propiedades.campo_departamento_placeholder')"
                    :error="$errors->first('departamento_id')"
                />

                <x-atoms.select
                    name="provincia_id"
                    id="provincia_id"
                    :label="__('comercial.propiedades.campo_provincia')"
                    :options="[]"
                    :value="$provinciaId"
                    data-valor-inicial="{{ $provinciaId }}"
                    data-placeholder="{{ __('comercial.propiedades.campo_provincia_placeholder') }}"
                    :placeholder="__('comercial.propiedades.campo_provincia_placeholder')"
                    :disabled="empty($departamentoId)"
                    :error="$errors->first('provincia_id')"
                />

                <x-atoms.select
                    name="municipio_id"
                    id="municipio_id"
                    :label="__('comercial.propiedades.campo_municipio')"
                    :options="[]"
                    :value="$municipioId"
                    data-valor-inicial="{{ $municipioId }}"
                    data-placeholder="{{ __('comercial.propiedades.campo_municipio_placeholder') }}"
                    :placeholder="__('comercial.propiedades.campo_municipio_placeholder')"
                    :disabled="empty($provinciaId)"
                    :error="$errors->first('municipio_id')"
                />

                <x-atoms.input
                    type="text"
                    name="localidad"
                    :label="__('comercial.propiedades.campo_localidad')"
                    :value="$localidad"
                    :placeholder="__('comercial.propiedades.campo_localidad_placeholder')"
                    :help="__('comercial.propiedades.campo_localidad_ayuda')"
                    :error="$errors->first('localidad')"
                />

                <x-molecules.color-swatch-field
                    id="color-field"
                    name="color"
                    :options="$coloresDisponibles"
                    :label="__('comercial.propiedades.campo_color')"
                    :help="__('comercial.propiedades.campo_color_ayuda')"
                    :value="$color ?: null"
                    :change-label="__('comercial.propiedades.campo_color_cambiar')"
                    :placeholder-label="__('comercial.propiedades.campo_color_sin_elegir')"
                    :error="$errors->first('color')"
                />
            </x-molecules.form-section>

            <x-organisms.form-actions-bar :status="__('comercial.propiedades.estado_form')">
                <x-slot:actions>
                    @if ($esEdicion && ! empty($volverA))
                        <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}propiedad_id={{ $propiedad->id }}" variant="outline" icon="arrow_back">
                            {{ __('comercial.propiedades.volver_a_formulario_origen') }}
                        </x-atoms.button>
                    @endif
                    <x-atoms.button :href="route('panel.propiedades.index')" variant="outline">
                        {{ __('ui.action.cancel') }}
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @foreach ($resumenPropiedad ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['mostrarAccion'])
                                <x-slot:action>
                                    <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="arrow_forward" block>
                                        {{ $resumen['accion']['label'] }}
                                    </x-atoms.button>
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state
                            :icon="$resumen['icono']"
                            :title="$resumen['vacioTitulo']"
                            :detail="$resumen['vacioDetalle']"
                        >
                            @if ($resumen['mostrarAccion'])
                                <x-slot:action>
                                    <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="add">
                                        {{ $resumen['accion']['label'] }}
                                    </x-atoms.button>
                                </x-slot:action>
                            @endif
                        </x-molecules.empty-state>
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
