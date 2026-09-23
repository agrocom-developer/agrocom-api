{{--
    Partial: formulario de carga de combustible, compartido por
    create.blade.php y edit.blade.php (HU-35, tarea 49; reescrito por la
    tarea 73, HU-50; edición agregada en la tarea 134) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    La primera sección reúne lo que decide qué recurso se ofrece (fecha y
    cuadrilla, que recargan la pantalla) y el recurso mismo, pegado a ellos
    (guía §6.3.5). Sin motivo obligatorio: sin `rendicion_id`, la edición no
    tiene ninguna guarda de fondo que explicar (a diferencia de Gasto).
    Aside SOLO en edición (guía §6.3.1), con la cuadrilla que consumió la
    carga.

    Espera:
    - $combustible (Combustible|null): null en alta; el modelo en edición.
    - $basesDisponibles / $equiposDisponibles / $campaniasDisponibles
      (Collection<int, string>).
    - $equipoTrabajoIdSeleccionado (int|null) / $fechaSeleccionada (string):
      lo que trae la query string, o los valores actuales de la carga en
      edición si no vino nada por query.
    - $recursoActual (string|null): clave compuesta `"{tipo}:{id}"` del
      recurso que la carga ya tenía (solo en edición).
    - $recursosDisponibles (Collection<string, string>): clave compuesta
      `"{tipo}:{id}"` => etiqueta.

    `data-ag-combustible-recarga-url` apunta a `store`/`update` según el modo
    — `resources/js/pages/combustible-form.js` la usa para recargar con
    `equipo_trabajo_id`/`fecha` al cambiarlos.

    Estilos en resources/css/pages/combustible.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $esEdicion = $combustible !== null;
    $accion = $esEdicion ? route('panel.combustible.update', $combustible) : route('panel.combustible.store');
    $urlRecarga = $esEdicion ? route('panel.combustible.edit', $combustible) : route('panel.combustible.create');
    $fecha = old('fecha', $fechaSeleccionada);
    $baseId = old('base_id', (string) ($combustible?->base_id ?? ''));
    $equipoTrabajoId = old('equipo_trabajo_id', (string) ($equipoTrabajoIdSeleccionado ?? ''));
    $campaniaId = old('campania_id', (string) ($combustible?->campania_id ?? ''));
    $recurso = old('recurso', (string) ($recursoActual ?? ''));
    $litros = old('litros', $combustible?->litros ?? '');
    $monto = old('monto', $combustible?->monto ?? '');
    $descripcion = old('descripcion', $combustible?->descripcion ?? '');
@endphp

<form
    method="POST"
    action="{{ $accion }}"
    class="ag-combustible-form"
    novalidate
    data-ag-combustible-form
    data-ag-combustible-recarga-url="{{ $urlRecarga }}"
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('finanzas.combustible.titulo_editar') : __('finanzas.combustible.titulo_crear')"
        :subtitle="__('finanzas.combustible.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.combustible.index')" :label="__('finanzas.combustible.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('finanzas.combustible.seccion_origen')"
            :count="trans_choice('finanzas.combustible.campos_contador', 4, ['cantidad' => 4])"
        >
            <x-atoms.date
                name="fecha"
                id="fecha"
                :label="__('finanzas.combustible.campo_fecha')"
                :value="$fecha"
                required
                :error="$errors->first('fecha')"
                data-ag-combustible-fecha
            />

            <x-atoms.select
                name="equipo_trabajo_id"
                id="equipo_trabajo_id"
                :label="__('finanzas.combustible.campo_equipo')"
                :options="$equiposDisponibles"
                :value="(string) $equipoTrabajoId"
                :placeholder="__('finanzas.combustible.campo_equipo_placeholder')"
                :error="$errors->first('equipo_trabajo_id')"
                required
                data-ag-combustible-equipo
            />

            <x-atoms.select
                name="recurso"
                id="recurso"
                :label="__('finanzas.combustible.campo_recurso')"
                :options="$recursosDisponibles"
                :value="(string) $recurso"
                :placeholder="__('finanzas.combustible.campo_recurso_placeholder')"
                :help="__('finanzas.combustible.campo_recurso_ayuda')"
                :error="$errors->first('recurso')"
                :disabled="$recursosDisponibles->isEmpty()"
                required
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('finanzas.combustible.campo_base')"
                :options="$basesDisponibles"
                :value="(string) $baseId"
                :placeholder="__('finanzas.combustible.campo_base_placeholder')"
                :error="$errors->first('base_id')"
                required
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('finanzas.combustible.seccion_carga')"
            :count="trans_choice('finanzas.combustible.campos_contador', 4, ['cantidad' => 4])"
        >
            <x-atoms.input
                type="number"
                name="litros"
                :label="__('finanzas.combustible.campo_litros')"
                :value="$litros"
                :suffix="__('finanzas.combustible.unidad_litros')"
                min="0.01"
                step="0.01"
                required
                :error="$errors->first('litros')"
            />

            <x-atoms.input
                type="number"
                name="monto"
                :label="__('finanzas.combustible.campo_monto')"
                :value="$monto"
                :suffix="__('finanzas.combustible.unidad_moneda')"
                min="0.01"
                step="0.01"
                required
                :error="$errors->first('monto')"
            />

            <x-atoms.select
                name="campania_id"
                :label="__('finanzas.combustible.campo_campania')"
                :placeholder="__('finanzas.combustible.campo_campania_placeholder')"
                :options="$campaniasDisponibles"
                :value="$campaniaId"
                :help="__('finanzas.combustible.campo_campania_ayuda')"
                :error="$errors->first('campania_id')"
            />

            <x-atoms.input
                type="text"
                name="descripcion"
                :label="__('finanzas.combustible.campo_descripcion')"
                :value="$descripcion"
                :error="$errors->first('descripcion')"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('finanzas.combustible.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.combustible.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                <x-molecules.summary-card
                    :title="__('finanzas.combustible.aside_equipo_titulo')"
                    :items="[
                        ['label' => __('finanzas.combustible.col_equipo'), 'value' => $equiposDisponibles[$combustible->equipo_trabajo_id] ?? ('#'.$combustible->equipo_trabajo_id)],
                    ]"
                >
                    @puede('personal.equipo_trabajo.ver')
                        <x-slot:action>
                            <x-atoms.button :href="route('panel.cuadrillas.show', $combustible->equipo_trabajo_id)" variant="outline" icon="arrow_forward" block>
                                {{ __('finanzas.combustible.aside_equipo_ver') }}
                            </x-atoms.button>
                        </x-slot:action>
                    @endpuede
                </x-molecules.summary-card>
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
