{{--
    Partial: formulario de repuesto, compartido por create.blade.php y
    edit.blade.php (HU-36, tarea 52) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Homogeneizado en la tarea 117 con el
    patrón de `mantenimiento::pages.planes._formulario`: `form-layout` con
    aside solo en edición.

    Espera:
    - $repuesto (Repuesto|null): null en alta; el modelo en edición.
    - $resumenRelacionado (list<array>|null): tarjetas del aside, resueltas
      por `ResumenRelacionadoDeRepuesto`; null/ausente en alta.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
    `costo_unitario` queda vacío por defecto (placeholder "sin compras
    todavía"): no tiene sentido forzar un 0 en el alta.

    Aside solo en edición (plan §3.4): un repuesto recién creado no tiene
    existencias, movimientos ni órdenes que resumir. Cada tarjeta ya viene
    gateada por el permiso de lo que muestra (stock, órdenes de mantenimiento)
    y la trae armada el controlador — la vista solo la pinta. El repuesto no
    tiene máquina de estados, así que no lleva pasos.

    Sin campo `activo`: es una propiedad de esquema, no una decisión del
    formulario (guía §6.3.3).
--}}
@php
    $esEdicion = $repuesto !== null;
    $accion = $esEdicion ? route('panel.repuestos.update', $repuesto) : route('panel.repuestos.store');
    $codigo = old('codigo', $repuesto?->codigo ?? '');
    $descripcion = old('descripcion', $repuesto?->descripcion ?? '');
    $unidad = old('unidad', $repuesto?->unidad ?? '');
    $costoUnitario = old('costo_unitario', $repuesto?->costo_unitario ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-repuestos-form" novalidate>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('inventario.repuestos.titulo_editar') : __('inventario.repuestos.titulo_crear')"
        :subtitle="__('inventario.repuestos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.repuestos.index')" :label="__('inventario.repuestos.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('inventario.repuestos.seccion_datos')"
            :count="__('inventario.repuestos.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input
                type="text"
                name="codigo"
                :label="__('inventario.repuestos.campo_codigo')"
                :value="$codigo"
                :help="__('inventario.repuestos.campo_codigo_ayuda')"
                required
                :error="$errors->first('codigo')"
            />

            <x-atoms.input
                type="text"
                name="unidad"
                :label="__('inventario.repuestos.campo_unidad')"
                :value="$unidad"
                :placeholder="__('inventario.repuestos.campo_unidad_placeholder')"
                :help="__('inventario.repuestos.campo_unidad_ayuda')"
                required
                :error="$errors->first('unidad')"
            />

            <x-atoms.input
                class="ag-form-section__field--full"
                type="text"
                name="descripcion"
                :label="__('inventario.repuestos.campo_descripcion')"
                :value="$descripcion"
                required
                :error="$errors->first('descripcion')"
            />

            <x-atoms.input
                type="number"
                name="costo_unitario"
                :label="__('inventario.repuestos.campo_costo')"
                :value="$costoUnitario"
                :placeholder="__('inventario.repuestos.campo_costo_placeholder')"
                :suffix="__('inventario.repuestos.unidad_moneda')"
                :help="__('inventario.repuestos.campo_costo_ayuda')"
                min="0"
                step="0.01"
                :error="$errors->first('costo_unitario')"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('inventario.repuestos.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.repuestos.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @foreach ($resumenRelacionado ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'arrow_forward'" block>
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'add'">
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.empty-state>
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
