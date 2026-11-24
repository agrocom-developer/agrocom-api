{{--
    Page: zonas/edit (GET /panel/zonas/{zona}/editar, panel.zonas.edit)
    Edición del nombre y la macrorregión de una zona — arquetipo Formulario, §6.3
    de docs/diseno/guia_pantalla_panel.md. El centroide y el radio no se editan
    acá: son datos geográficos del catálogo.

    Datos esperados (ver ZonasController::edit()): la cáscara de CascaraPanel,
    más $zona (Zona) y $regiones (list<Macrorregion>).

    Gateada por `solicitudes.zona.editar`, verificado server-side.
--}}
@php
    $nombre = old('nombre', $zona->nombre);
    $region = old('macrorregion', $zona->macrorregion);
    $opcionesRegion = collect($regiones)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.macrorregion.'.$opcion->value)])->all();
    $titulo = __('solicitudes.zonas.editar_titulo', ['zona' => $zona->nombre]);
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
        :vista-actual="__('solicitudes.zonas.titulo')"
    >
        <form method="POST" action="{{ route('panel.zonas.update', $zona) }}" novalidate>
            @csrf
            @method('PUT')

            <x-organisms.page-header :title="$titulo" :subtitle="__('solicitudes.zonas.editar_subtitulo')">
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.zonas.index')" :label="__('solicitudes.zonas.volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-layout>
                <x-slot:aside>
                    <x-molecules.empty-state
                        icon="map"
                        :title="__('solicitudes.zonas.aside_titulo')"
                        :detail="__('solicitudes.zonas.aside_detalle', ['lat' => $zona->centroide_lat, 'lng' => $zona->centroide_lng])"
                    />
                </x-slot:aside>

                <x-molecules.form-section :title="__('solicitudes.zonas.seccion_datos')">
                    <x-atoms.input
                        type="text"
                        name="nombre"
                        :label="__('solicitudes.zonas.campo_nombre')"
                        :value="$nombre"
                        :error="$errors->first('nombre')"
                        required
                        autocomplete="off"
                    />
                    <x-atoms.select
                        name="macrorregion"
                        id="zona-macrorregion"
                        :label="__('solicitudes.zonas.campo_region')"
                        :options="$opcionesRegion"
                        :value="$region"
                        :error="$errors->first('macrorregion')"
                        :placeholder="__('solicitudes.zonas.campo_region_placeholder')"
                        required
                    />
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('solicitudes.zonas.estado_cambios')">
                    <x-slot:actions>
                        <x-molecules.boton-volver :href="route('panel.zonas.index')" cancelar />
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
