{{--
    Partial: formulario de tarifa, compartido por create.blade.php y
    edit.blade.php (ADR 0023) — arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Espera:
    - $tarifa (Tarifa|null): null en alta; el modelo en edición.
    - $modalidades (array): opciones de ModalidadPago (clave => etiqueta).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    Una tarjeta: "Tarifa" (nombre, modalidad, montos, predeterminada, descripción).

    Sin resumen relacionado: las tarifas no tienen elementos dependientes
    en otras pantallas de edición (el aside solo va si hay relaciones).
--}}
@php
    $esEdicion = $tarifa !== null;
    $accion = $esEdicion ? route('panel.tarifas.update', $tarifa) : route('panel.tarifas.store');
    $nombre = old('nombre', $tarifa?->nombre ?? '');
    $modalidad = old('modalidad', $tarifa?->modalidad?->value ?? '');
    $montoPiloto = old('monto_piloto', $tarifa?->monto_piloto ?? '');
    $montoAuxiliar = old('monto_auxiliar', $tarifa?->monto_auxiliar ?? '');
    $predeterminada = old('predeterminada', $tarifa?->predeterminada ?? false);
    $descripcion = old('descripcion', $tarifa?->descripcion ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-tarifas-form" novalidate data-ag-tarifas-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('finanzas.tarifas.editar_titulo') : __('finanzas.tarifas.crear_titulo')"
        :subtitle="__('finanzas.tarifas.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.tarifas.index')" variant="outline" icon="arrow_back">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
    @if ($esEdicion && ($resumenTarifa ?? []) !== [])
        <x-slot:aside>
            @foreach ($resumenTarifa as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['acciones'] !== [])
                            <x-slot:action>
                                @foreach ($resumen['acciones'] as $accion)
                                    <x-atoms.button :href="$accion['href']" variant="outline" :icon="$accion['icono'] ?? 'arrow_forward'" block>
                                        {{ $accion['label'] }}
                                    </x-atoms.button>
                                @endforeach
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state
                        :icon="$resumen['icono']"
                        :title="$resumen['vacioTitulo']"
                        :detail="$resumen['vacioDetalle']"
                    />
                @endif
            @endforeach
        </x-slot:aside>
    @endif
        <x-molecules.form-section
            :title="__('finanzas.tarifas.seccion_datos')"
            :count="__('finanzas.tarifas.campos_contador', ['cantidad' => 5])"
            :detail="__('finanzas.tarifas.seccion_datos_detalle')"
        >
            <x-atoms.input
                type="text"
                name="nombre"
                :label="__('finanzas.tarifas.campo_nombre')"
                :value="$nombre"
                :placeholder="__('finanzas.tarifas.campo_nombre_placeholder')"
                required
                :error="$errors->first('nombre')"
            />

            <x-atoms.select
                name="modalidad"
                id="modalidad"
                :label="__('finanzas.tarifas.campo_modalidad')"
                :options="$modalidades"
                :value="$modalidad"
                :placeholder="__('finanzas.tarifas.campo_modalidad_placeholder')"
                :help="__('finanzas.tarifas.campo_modalidad_ayuda')"
                required
                :error="$errors->first('modalidad')"
            />

            <x-atoms.input
                type="number"
                name="monto_piloto"
                :label="__('finanzas.tarifas.campo_monto_piloto')"
                :value="$montoPiloto"
                step="0.01"
                min="0"
                :help="__('finanzas.tarifas.campo_monto_ayuda')"
                required
                :error="$errors->first('monto_piloto')"
            />

            <x-atoms.input
                type="number"
                name="monto_auxiliar"
                :label="__('finanzas.tarifas.campo_monto_auxiliar')"
                :value="$montoAuxiliar"
                step="0.01"
                min="0"
                :help="__('finanzas.tarifas.campo_monto_ayuda')"
                required
                :error="$errors->first('monto_auxiliar')"
            />

            <x-atoms.switch
                name="predeterminada"
                id="predeterminada"
                value="1"
                :label="__('finanzas.tarifas.campo_predeterminada')"
                :checked="(bool) $predeterminada"
                :help="__('finanzas.tarifas.campo_predeterminada_ayuda')"
            />

            <x-atoms.textarea
                class="ag-form-section__field--full"
                name="descripcion"
                :label="__('finanzas.tarifas.campo_descripcion')"
                :placeholder="__('finanzas.tarifas.campo_descripcion_placeholder')"
                :value="$descripcion"
                :error="$errors->first('descripcion')"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('finanzas.tarifas.estado_form')">
            <x-slot:actions>
                <x-atoms.button :href="route('panel.tarifas.index')" variant="outline">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
