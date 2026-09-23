{{--
    Partial: formulario de la CABECERA de una rendición, compartido por
    create.blade.php y edit.blade.php (HU-34, tarea 48; edición agregada en
    la tarea 134) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md.

    Solo cabecera (`base_id`/`jefe_campo_id`/`fecha`/`descripcion`): `estado`,
    `monto` y `aprobado_por` nunca viajan por acá (invariante 7 de
    CLAUDE.md) — los gastos asociados y las transiciones de estado se
    manejan desde `show.blade.php`, no desde este formulario.

    Espera:
    - $rendicion (Rendicion|null): null en alta; el modelo en edición
      (siempre `Abierta` — `RendicionesController::edit()` ya bloqueó el
      acceso si no lo estaba).
    - $basesDisponibles / $personasDisponibles (Collection<int, string>).
    - $gastosAsociadosCount (int, solo en edición): cuántos gastos ya tiene
      asociados — el aside de edición (guía §6.3.1) resume esto y enlaza a
      la ficha completa, donde se gestionan.
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
@php
    $esEdicion = $rendicion !== null;
    $accion = $esEdicion ? route('panel.rendiciones.update', $rendicion) : route('panel.rendiciones.store');
    $baseId = old('base_id', (string) ($rendicion?->base_id ?? ''));
    $jefeId = old('jefe_campo_id', (string) ($rendicion?->jefe_campo_id ?? ''));
    $fecha = old('fecha', $rendicion?->fecha?->toDateString() ?? now()->toDateString());
    $descripcion = old('descripcion', $rendicion?->descripcion ?? '');
@endphp

<form
    method="POST"
    action="{{ $accion }}"
    class="ag-rendiciones-form"
    novalidate
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('finanzas.rendiciones.titulo_editar') : __('finanzas.rendiciones.titulo_crear')"
        :subtitle="$esEdicion ? __('finanzas.rendiciones.subtitulo_form_editar') : __('finanzas.rendiciones.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="$esEdicion ? route('panel.rendiciones.show', $rendicion) : route('panel.rendiciones.index')"
                :label="__('finanzas.rendiciones.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('finanzas.rendiciones.seccion_datos')"
            :count="__('finanzas.rendiciones.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('finanzas.rendiciones.campo_base')"
                :options="$basesDisponibles"
                :value="(string) $baseId"
                :placeholder="__('finanzas.rendiciones.campo_base_placeholder')"
                :error="$errors->first('base_id')"
                required
            />

            <x-atoms.select
                name="jefe_campo_id"
                id="jefe_campo_id"
                :label="__('finanzas.rendiciones.campo_jefe_campo')"
                :options="$personasDisponibles"
                :value="(string) $jefeId"
                :placeholder="__('finanzas.rendiciones.campo_jefe_campo_placeholder')"
                :error="$errors->first('jefe_campo_id')"
                required
            />

            <x-atoms.date
                name="fecha"
                :label="__('finanzas.rendiciones.campo_fecha')"
                :value="$fecha"
                required
                :error="$errors->first('fecha')"
            />

            <x-atoms.textarea
                class="ag-form-section__field--full"
                name="descripcion"
                :label="__('finanzas.rendiciones.campo_descripcion')"
                :value="$descripcion"
                :error="$errors->first('descripcion')"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('finanzas.rendiciones.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="$esEdicion ? route('panel.rendiciones.show', $rendicion) : route('panel.rendiciones.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                <x-molecules.summary-card
                    :title="__('finanzas.rendiciones.aside_relacionado_titulo')"
                    :items="[
                        ['label' => __('finanzas.rendiciones.aside_relacionado_gastos'), 'value' => (string) $gastosAsociadosCount],
                        ['label' => __('finanzas.rendiciones.aside_relacionado_monto'), 'value' => __('finanzas.rendiciones.monto_valor', ['monto' => FormatoMonto::decimal($rendicion->monto)])],
                    ]"
                >
                    <x-slot:action>
                        <x-atoms.button :href="route('panel.rendiciones.show', $rendicion)" variant="outline" icon="arrow_forward" block>
                            {{ __('finanzas.rendiciones.aside_relacionado_ver') }}
                        </x-atoms.button>
                    </x-slot:action>
                </x-molecules.summary-card>
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
