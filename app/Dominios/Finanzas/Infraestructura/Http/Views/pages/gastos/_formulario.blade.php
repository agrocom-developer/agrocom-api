{{--
    Partial: formulario de gasto, compartido por create.blade.php y
    edit.blade.php (HU-33, tarea 47; edición agregada en la tarea 134) —
    arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo
    criterio de extracción que `bases/_formulario.blade.php`.

    Sin motivo obligatorio: a diferencia de la Orden de Trabajo (tarea 127),
    acá no hay un estado intermedio "publicada pero corregible" — un gasto
    sin rendición, o con una `Abierta`, se edita libre; con una
    `Presentada`/`Aprobada`, la política ya bloqueó el acceso a este
    formulario en el controlador (`GastosController::edit()`), así que nunca
    se llega acá con un gasto no editable. Aside SOLO en edición (guía
    §6.3.1), con la rendición asociada si tiene una.

    Espera:
    - $gasto (Gasto|null): null en alta; el modelo en edición.
    - $rubrosConSubrubros (Collection<Rubro> con `subrubros` cargado).
    - $equiposDisponibles / $basesDisponibles / $trabajosDisponibles /
      $campaniasDisponibles (Collection<int, string>).

    `enctype="multipart/form-data"` en ambos casos: en edición, sin archivo
    nuevo se conserva el comprobante que ya tenía (`ActualizarGasto`, ver su
    docblock) — el aviso `campo_comprobante_actual` se lo dice al usuario.

    Estilos en resources/css/pages/gastos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $esEdicion = $gasto !== null;
    $accion = $esEdicion ? route('panel.gastos.update', $gasto) : route('panel.gastos.store');
    $fecha = old('fecha', $gasto?->fecha?->toDateString() ?? now()->toDateString());
    $rubroId = old('rubro_id', (string) ($gasto?->rubro_id ?? ''));
    $subrubroId = old('subrubro_id', (string) ($gasto?->subrubro_id ?? ''));
    $cantidad = old('cantidad', $gasto?->cantidad ?? '');
    $precioUnitario = old('precio_unitario', $gasto?->precio_unitario ?? '');
    $equipoTrabajoId = old('equipo_trabajo_id', (string) ($gasto?->equipo_trabajo_id ?? ''));
    $baseId = old('base_id', (string) ($gasto?->base_id ?? ''));
    $trabajoId = old('trabajo_id', (string) ($gasto?->trabajo_id ?? ''));
    $campaniaId = old('campania_id', (string) ($gasto?->campania_id ?? ''));
    $subrubrosDisponibles = $rubrosConSubrubros->flatMap->subrubros;
    $subrubrosOpciones = $subrubrosDisponibles->pluck('nombre', 'id');
    $mapaRubroSubrubro = $subrubrosDisponibles->mapWithKeys(fn ($subrubro) => [$subrubro->id => $subrubro->rubro_id]);
@endphp

<form
    method="POST"
    action="{{ $accion }}"
    enctype="multipart/form-data"
    class="ag-gastos-form"
    novalidate
    data-ag-gastos-form
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('finanzas.gastos.titulo_editar') : __('finanzas.gastos.titulo_crear')"
        :subtitle="__('finanzas.gastos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.gastos.index')" :label="__('finanzas.gastos.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('finanzas.gastos.seccion_datos')"
            :count="trans_choice('finanzas.gastos.campos_contador', 5, ['cantidad' => 5])"
        >
            <x-atoms.date
                name="fecha"
                :label="__('finanzas.gastos.campo_fecha')"
                :value="$fecha"
                required
                :error="$errors->first('fecha')"
            />

            <x-atoms.select
                name="rubro_id"
                :label="__('finanzas.gastos.campo_rubro')"
                :placeholder="__('finanzas.gastos.campo_rubro_placeholder')"
                :options="$rubrosConSubrubros->pluck('nombre', 'id')"
                :value="$rubroId"
                required
                :error="$errors->first('rubro_id')"
                data-ag-gasto-rubro
            />

            <x-atoms.select
                name="subrubro_id"
                :label="__('finanzas.gastos.campo_subrubro')"
                :placeholder="__('finanzas.gastos.campo_subrubro_placeholder')"
                :options="$subrubrosOpciones"
                :value="$subrubroId"
                :error="$errors->first('subrubro_id')"
                data-ag-gasto-subrubro
                data-mapa-rubro-subrubro="{{ $mapaRubroSubrubro->toJson() }}"
            />

            <x-atoms.input
                type="number"
                name="cantidad"
                :label="__('finanzas.gastos.campo_cantidad')"
                :value="$cantidad"
                min="0.01"
                step="0.01"
                required
                :error="$errors->first('cantidad')"
            />

            <x-atoms.input
                type="number"
                name="precio_unitario"
                :label="__('finanzas.gastos.campo_precio_unitario')"
                :value="$precioUnitario"
                :suffix="__('finanzas.gastos.unidad_moneda')"
                :help="__('finanzas.gastos.campo_precio_unitario_ayuda')"
                min="0.01"
                step="0.01"
                required
                :error="$errors->first('precio_unitario')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('finanzas.gastos.seccion_imputacion')"
            :count="trans_choice('finanzas.gastos.campos_contador', 4, ['cantidad' => 4])"
        >
            <x-atoms.select
                name="equipo_trabajo_id"
                :label="__('finanzas.gastos.campo_equipo')"
                :placeholder="__('finanzas.gastos.campo_equipo_placeholder')"
                :options="$equiposDisponibles"
                :value="$equipoTrabajoId"
                :error="$errors->first('equipo_trabajo_id')"
            />

            <x-atoms.select
                name="base_id"
                :label="__('finanzas.gastos.campo_base')"
                :placeholder="__('finanzas.gastos.campo_base_placeholder')"
                :options="$basesDisponibles"
                :value="$baseId"
                :error="$errors->first('base_id')"
            />

            <x-atoms.select
                name="trabajo_id"
                :label="__('finanzas.gastos.campo_trabajo')"
                :placeholder="__('finanzas.gastos.campo_trabajo_placeholder')"
                :options="$trabajosDisponibles"
                :value="$trabajoId"
                :error="$errors->first('trabajo_id')"
            />

            <x-atoms.select
                name="campania_id"
                :label="__('finanzas.gastos.campo_campania')"
                :placeholder="__('finanzas.gastos.campo_campania_placeholder')"
                :options="$campaniasDisponibles"
                :value="$campaniaId"
                :help="__('finanzas.gastos.campo_campania_ayuda')"
                :error="$errors->first('campania_id')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('finanzas.gastos.seccion_comprobante')"
            :count="trans_choice('finanzas.gastos.campos_contador', 1, ['cantidad' => 1])"
        >
            <x-molecules.file-field
                class="ag-form-section__field--full"
                name="comprobante"
                accept="image/jpeg,image/png,application/pdf"
                :label="__('finanzas.gastos.campo_comprobante')"
                :help="$esEdicion && $gasto->comprobante_url !== null ? __('finanzas.gastos.campo_comprobante_actual') : __('finanzas.gastos.campo_comprobante_ayuda')"
                :replace-label="__('finanzas.gastos.campo_comprobante_elegir')"
                :error="$errors->first('comprobante')"
            >
                <x-atoms.icon name="receipt_long" size="lg" />
            </x-molecules.file-field>
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('finanzas.gastos.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.gastos.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @if ($gasto->rendicion !== null)
                    <x-molecules.summary-card
                        :title="__('finanzas.gastos.aside_rendicion_titulo')"
                        :items="[
                            ['label' => __('finanzas.gastos.aside_rendicion_estado'), 'value' => __('finanzas.rendiciones.estado.'.$gasto->rendicion->estado->value)],
                        ]"
                    >
                        @puede('finanzas.rendicion.ver')
                            <x-slot:action>
                                <x-atoms.button :href="route('panel.rendiciones.show', $gasto->rendicion)" variant="outline" icon="arrow_forward" block>
                                    {{ __('finanzas.gastos.aside_rendicion_ver') }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endpuede
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('finanzas.gastos.aside_sin_rendicion_titulo')"
                        :detail="__('finanzas.gastos.aside_sin_rendicion_detalle')"
                    />
                @endif
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
