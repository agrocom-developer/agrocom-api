{{--
    Parcial: zonas/_formulario (compartido por create y edit)
    Alta y edición de una zona — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Cuatro campos: nombre, región y las
    coordenadas del centro.

    Datos esperados (ver ZonasController::create()/edit()): la cáscara de
    CascaraPanel, más $zona (Zona|null: null en el alta), $regiones
    (list<string>) y $usos (array{terrenos, proveedores}, solo en edición).

    Gateada por `solicitudes.zona.gestionar`, verificado server-side.
--}}
@php
    $esEdicion = $zona !== null;
    $titulo = $esEdicion ? __('solicitudes.zonas.editar_titulo') : __('solicitudes.zonas.crear_titulo');
    $opcionesRegion = collect($regiones)->mapWithKeys(fn ($region) => [$region => __('solicitudes.region.'.$region)])->all();
@endphp
<x-templates.panel-shell :title="$titulo" :tema="$tema">
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
        :vista-actual="$titulo"
    >
        <form
            method="POST"
            action="{{ $esEdicion ? route('panel.zonas.update', $zona) : route('panel.zonas.store') }}"
            class="ag-solicitudes"
            novalidate
        >
            @csrf
            @if ($esEdicion)
                @method('PUT')
            @endif

            <x-organisms.page-header
                :title="$titulo"
                :subtitle="$esEdicion ? __('solicitudes.zonas.editar_subtitulo') : __('solicitudes.zonas.crear_subtitulo')"
            >
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-layout>
            <x-molecules.form-section accent="primary-2" :title="__('solicitudes.zonas.seccion_datos')">
                <x-atoms.input
                    type="text"
                    name="nombre"
                    :label="__('solicitudes.zonas.campo_nombre')"
                    :value="old('nombre', $zona?->nombre ?? '')"
                    :error="$errors->first('nombre')"
                    required
                    maxlength="80"
                    autocomplete="off"
                />
                <x-atoms.select
                    name="macrorregion"
                    id="zona-region"
                    :label="__('solicitudes.zonas.campo_region')"
                    :options="$opcionesRegion"
                    :value="old('macrorregion', $zona?->macrorregion)"
                    :placeholder="__('solicitudes.zonas.campo_region_placeholder')"
                    :error="$errors->first('macrorregion')"
                    required
                />
                <x-atoms.input
                    type="text"
                    name="latitud"
                    :label="__('solicitudes.zonas.campo_latitud')"
                    :value="old('latitud', $zona?->centroide_lat ?? '')"
                    :error="$errors->first('latitud')"
                    inputmode="decimal"
                    required
                    autocomplete="off"
                />
                <x-atoms.input
                    type="text"
                    name="longitud"
                    :label="__('solicitudes.zonas.campo_longitud')"
                    :value="old('longitud', $zona?->centroide_lng ?? '')"
                    :help="__('solicitudes.zonas.ayuda_coordenadas')"
                    :error="$errors->first('longitud')"
                    inputmode="decimal"
                    required
                    autocomplete="off"
                />
            </x-molecules.form-section>

            <x-organisms.form-actions-bar :status="__('solicitudes.zonas.estado_form')">
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.zonas.index')" cancelar />
                    <x-atoms.button type="submit" variant="primary">{{ __('solicitudes.zonas.guardar') }}</x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>

            @if ($esEdicion)
                <x-slot:aside>
                    <x-molecules.summary-card
                        :title="__('solicitudes.zonas.uso_titulo')"
                        :items="[
                            ['label' => __('solicitudes.zonas.col_terrenos'), 'value' => (string) $usos['terrenos']],
                            ['label' => __('solicitudes.zonas.col_proveedores'), 'value' => (string) $usos['proveedores']],
                        ]"
                    />
                </x-slot:aside>
            @endif
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
