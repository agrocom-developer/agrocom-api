{{--
    Page: propiedades/mapa (GET/POST /panel/propiedades/{propiedad}/mapa,
    panel.propiedades.mapa[.guardar]) — punto de referencia
    (latitud/longitud) y perímetro (`geometria`, GeoJSON `MultiPolygon`) de
    la propiedad, en pantalla propia (adenda 16/9/2026 a ADR 0018 punto 1 /
    ADR 0020: "el editor de mapa multi-polígono se construye en un feature
    aparte"). Se entra desde el summary "Coordenadas del mapa" del
    formulario de la propiedad — mismo criterio que `siembra.blade.php`
    (reusa el permiso `comercial.propiedad.editar`, no es un ABM propio).

    Datos esperados (ver PropiedadMapaController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad).
    - $proveedorMapa (array{proveedor: 'google'|'leaflet', googleMapsApiKey: ?string}):
      resuelto por ResolverProveedorMapa — mismo contrato que usa el editor
      de Lote.

    El editor (`organisms/propiedad-mapa-editor.js`) es un módulo INDEPENDIENTE
    del de Lote (`organisms/lote-mapa-editor.js`): dibuja VARIOS polígonos
    (islas) más un marcador arrastrable, en vez de un solo `Polygon`. Guarda
    todo con el mismo `<form>` HTML estándar del formulario — sin AJAX,
    mismo criterio que el editor de Lote.
--}}
@php
    $esGoogle = ($proveedorMapa['proveedor'] ?? 'leaflet') === 'google';
    $latitud = old('latitud', $propiedad->latitud ?? '');
    $longitud = old('longitud', $propiedad->longitud ?? '');
    $geometria = old('geometria', $propiedad->geometria !== null ? json_encode($propiedad->geometria) : '');
@endphp
<x-templates.panel-shell :title="__('comercial.propiedades.mapa_titulo', ['propiedad' => $propiedad->nombre])" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.mapa_titulo', ['propiedad' => $propiedad->nombre])"
    >
        <div class="ag-propiedad-mapa-pagina">
            <x-organisms.page-header
                :title="__('comercial.propiedades.mapa_titulo', ['propiedad' => $propiedad->nombre])"
                :subtitle="__('comercial.propiedades.mapa_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.propiedades.edit', $propiedad) }}" variant="outline" icon="arrow_back">
                        {{ __('comercial.propiedades.mapa_volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="POST" action="{{ route('panel.propiedades.mapa.guardar', $propiedad) }}" novalidate>
                @csrf

                <x-molecules.form-section :title="__('comercial.propiedades.mapa_titulo_seccion')">
                    <x-atoms.input
                        type="number"
                        name="latitud"
                        label="{{ __('comercial.propiedades.campo_latitud') }}"
                        value="{{ $latitud }}"
                        step="0.000001"
                        min="-90"
                        max="90"
                        data-ag-propiedad-latitud
                        error="{{ $errors->first('latitud') }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="longitud"
                        label="{{ __('comercial.propiedades.campo_longitud') }}"
                        value="{{ $longitud }}"
                        step="0.000001"
                        min="-180"
                        max="180"
                        data-ag-propiedad-longitud
                        error="{{ $errors->first('longitud') }}"
                    />

                    <div
                        class="ag-input ag-form-section__field--full"
                        data-ag-propiedad-mapa
                        data-ag-propiedad-mapa-proveedor="{{ $esGoogle ? 'google' : 'leaflet' }}"
                        @if ($esGoogle)
                            data-ag-propiedad-mapa-google-key="{{ $proveedorMapa['googleMapsApiKey'] }}"
                        @endif
                    >
                        <span class="ag-input__label">{{ __('comercial.propiedades.campo_geometria') }}</span>

                        @error('geometria')
                            <p class="ag-input__error" role="alert">{{ $message }}</p>
                        @enderror

                        {{-- El valor real, escrito por el editor — el input de
                             latitud/longitud de arriba (data-ag-propiedad-latitud/
                             -longitud) también los lee/escribe directo, sin un
                             tercer input oculto. --}}
                        <input type="hidden" name="geometria" value="{{ $geometria }}" data-ag-propiedad-geometria>

                        <div class="ag-mapa-marco">
                            <div class="ag-mapa-barra" role="toolbar" aria-label="{{ __('comercial.propiedades.mapa_barra_aria') }}">
                                <button
                                    type="button"
                                    class="ag-mapa-accion"
                                    data-ag-propiedad-mapa-accion="dibujar"
                                    title="{{ __('comercial.propiedades.mapa_dibujar') }}"
                                    aria-label="{{ __('comercial.propiedades.mapa_dibujar') }}"
                                    aria-pressed="false"
                                >
                                    <x-atoms.icon name="draw" />
                                </button>
                                <button
                                    type="button"
                                    class="ag-mapa-accion"
                                    data-ag-propiedad-mapa-accion="deshacer"
                                    title="{{ __('comercial.propiedades.mapa_deshacer') }}"
                                    aria-label="{{ __('comercial.propiedades.mapa_deshacer') }}"
                                    disabled
                                >
                                    <x-atoms.icon name="undo" />
                                </button>
                                <button
                                    type="button"
                                    class="ag-mapa-accion"
                                    data-ag-propiedad-mapa-accion="borrar-todo"
                                    title="{{ __('comercial.propiedades.mapa_borrar_todo') }}"
                                    aria-label="{{ __('comercial.propiedades.mapa_borrar_todo') }}"
                                >
                                    <x-atoms.icon name="delete_sweep" />
                                </button>
                                <button
                                    type="button"
                                    class="ag-mapa-accion"
                                    data-ag-propiedad-mapa-accion="centrar"
                                    title="{{ __('comercial.propiedades.mapa_centrar') }}"
                                    aria-label="{{ __('comercial.propiedades.mapa_centrar') }}"
                                >
                                    <x-atoms.icon name="center_focus_strong" />
                                </button>
                                <button
                                    type="button"
                                    class="ag-mapa-accion"
                                    data-ag-propiedad-mapa-accion="capa"
                                    title="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                                    aria-label="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                                    aria-pressed="false"
                                    data-ag-propiedad-mapa-capa-satelite="{{ __('comercial.propiedades.mapa_capa_satelite') }}"
                                    data-ag-propiedad-mapa-capa-calles="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                                >
                                    <x-atoms.icon name="layers" />
                                </button>
                            </div>

                            <div class="ag-propiedad-mapa__lienzo" data-ag-propiedad-mapa-lienzo></div>

                            <div class="ag-propiedad-mapa__pie">
                                <p class="ag-input__help ag-propiedad-mapa__ayuda">{{ __('comercial.propiedades.campo_geometria_ayuda') }}</p>
                                <span
                                    class="ag-propiedad-mapa__medida"
                                    data-ag-propiedad-medida-texto
                                    data-ag-propiedad-medida-plantilla="{{ __('comercial.propiedades.mapa_medida') }}"
                                ></span>
                            </div>
                        </div>
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('comercial.propiedades.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.propiedades.edit', $propiedad) }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
