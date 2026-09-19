{{--
    Page: propiedades/mapa (GET/POST /panel/propiedades/{propiedad}/mapa,
    panel.propiedades.mapa[.guardar]) — punto de referencia (un único
    marcador) y perímetro (`geometria`, GeoJSON `MultiPolygon`) de la
    propiedad, en pantalla propia (adenda 16/9/2026 a ADR 0018 punto 1 / ADR
    0020: "el editor de mapa multi-polígono se construye en un feature
    aparte"). Se entra desde el summary "Coordenadas del mapa" del
    formulario de la propiedad — mismo criterio que `siembra.blade.php`
    (reusa el permiso `comercial.propiedad.editar`, no es un ABM propio).

    Datos esperados (ver PropiedadMapaController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad).
    - $proveedorMapa (array{proveedor: 'google'|'leaflet', googleMapsApiKey: ?string}):
      resuelto por ResolverProveedorMapa — mismo contrato que usa el editor
      de Lote.
    - $centroDefecto (array{lat: float, lng: float}): resuelto por
      ResolverCentroReferenciaPropiedad — de dónde arranca el mapa cuando la
      propiedad todavía no tiene marcador ni perímetro.
    - $municipioReferencia (array{municipio, provincia, departamento}|null):
      resuelto por ResolverMunicipioPropiedad — con municipio cargado, el
      editor lo ubica con un geocodificador y arranca ahí (el centro por
      departamento de arriba queda de respaldo).

    El editor (`organisms/propiedad-mapa-editor.js`) es un módulo INDEPENDIENTE
    del de Lote (`organisms/lote-mapa-editor.js`): dibuja VARIOS polígonos
    (islas) más un único marcador, en vez de un solo `Polygon`. Las acciones
    (dibujar/terminar, deshacer, borrar, capa, marcador, ver todo el
    perímetro, expandir) viven DENTRO del lienzo — pedido directo del dueño
    (16/9/2026): el mapa ocupa todo el alto disponible de la pantalla, sin
    scroll propio; latitud/longitud ya no se tipean a mano, viajan ocultas y
    las llena el marcador. Guarda todo con el mismo `<form>` HTML estándar
    del formulario — sin AJAX, mismo criterio que el editor de Lote.
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
                    <x-atoms.button :href="route('panel.propiedades.edit', $propiedad)" variant="outline" icon="arrow_back">
                        {{ __('comercial.propiedades.mapa_volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="POST" action="{{ route('panel.propiedades.mapa.guardar', $propiedad) }}" class="ag-propiedad-mapa-form" novalidate>
                @csrf

                @error('latitud')
                    <p class="ag-input__error" role="alert">{{ $message }}</p>
                @enderror
                @error('longitud')
                    <p class="ag-input__error" role="alert">{{ $message }}</p>
                @enderror

                <x-molecules.form-section
                    :title="__('comercial.propiedades.mapa_titulo_seccion')"
                    :count="__('comercial.propiedades.campos_contador', ['cantidad' => 2])"
                    class="ag-propiedad-mapa-seccion"
                >
                <div
                    class="ag-propiedad-mapa"
                    data-ag-propiedad-mapa
                    data-ag-propiedad-mapa-id="{{ $propiedad->id }}"
                    data-ag-propiedad-mapa-proveedor="{{ $esGoogle ? 'google' : 'leaflet' }}"
                    data-ag-propiedad-mapa-centro-defecto="{{ $centroDefecto['lat'] }},{{ $centroDefecto['lng'] }}"
                    @if ($esGoogle)
                        data-ag-propiedad-mapa-google-key="{{ $proveedorMapa['googleMapsApiKey'] }}"
                        data-ag-propiedad-mapa-error-google="{{ __('ui.errores.google_maps_no_disponible') }}"
                    @endif
                    @if ($propiedad->color)
                        data-ag-propiedad-mapa-color="{{ $propiedad->color }}"
                    @endif
                    @if ($municipioReferencia !== null)
                        data-ag-propiedad-mapa-municipio="{{ $municipioReferencia['municipio'] }}"
                        data-ag-propiedad-mapa-provincia="{{ $municipioReferencia['provincia'] }}"
                        data-ag-propiedad-mapa-departamento="{{ $municipioReferencia['departamento'] }}"
                    @endif
                >
                    <span class="ag-input__label">{{ __('comercial.propiedades.campo_geometria') }}</span>

                    @error('geometria')
                        <p class="ag-input__error" role="alert">{{ $message }}</p>
                    @enderror

                    {{-- Los tres valores reales, escritos por el editor — ya
                         no se tipean a mano (pedido del dueño: "eso es
                         erróneo", latitud/longitud salen siempre del mapa).
                         DENTRO de este contenedor a propósito: el JS busca
                         sus referencias con `contenedor.querySelector(...)`
                         acotado a `[data-ag-propiedad-mapa]`. --}}
                    <input type="hidden" name="latitud" value="{{ $latitud }}" data-ag-propiedad-latitud>
                    <input type="hidden" name="longitud" value="{{ $longitud }}" data-ag-propiedad-longitud>
                    <input type="hidden" name="geometria" value="{{ $geometria }}" data-ag-propiedad-geometria>

                    {{-- Dos niveles: `-lienzo` es el marco (pantalla completa,
                         ancla los overlays); `-mapa` es el div que se le pasa
                         A LEAFLET/GOOGLE — Google Maps toma posesión de ESE
                         elemento y borra cualquier hijo que ya tuviera, así
                         que los overlays tienen que ser HERMANOS del mapa, no
                         hijos (confirmado en vivo, 16/9/2026: con un solo
                         nivel, Google Maps eliminaba la columna de acciones
                         al inicializarse). --}}
                    <div class="ag-propiedad-mapa__lienzo" data-ag-propiedad-mapa-lienzo>
                        <div class="ag-propiedad-mapa__mapa" data-ag-propiedad-mapa-mapa></div>

                        {{-- Columna de acciones flotante, arriba a la derecha
                             — DENTRO del marco, para que el mapa ocupe todo
                             el contenedor sin barra externa (pedido directo
                             del dueño). --}}
                        <div class="ag-propiedad-mapa__acciones" role="toolbar" aria-label="{{ __('comercial.propiedades.mapa_barra_aria') }}">
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="dibujar"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_dibujar') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_dibujar') }}"
                                aria-pressed="false"
                                data-ag-propiedad-mapa-dibujar-iniciar="{{ __('comercial.propiedades.mapa_dibujar') }}"
                                data-ag-propiedad-mapa-dibujar-terminar="{{ __('comercial.propiedades.mapa_dibujar_terminar') }}"
                            >
                                <x-atoms.icon name="draw" data-ag-propiedad-mapa-icono-dibujar />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="deshacer"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_deshacer') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_deshacer') }}"
                                disabled
                            >
                                <x-atoms.icon name="undo" />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="borrar-ultimo"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_borrar_ultimo') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_borrar_ultimo') }}"
                            >
                                <x-atoms.icon name="delete" />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="capa"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                                aria-pressed="false"
                                data-ag-propiedad-mapa-capa-satelite="{{ __('comercial.propiedades.mapa_capa_satelite') }}"
                                data-ag-propiedad-mapa-capa-calles="{{ __('comercial.propiedades.mapa_capa_calles') }}"
                            >
                                <x-atoms.icon name="layers" />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="marcador"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_marcador_colocar') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_marcador_colocar') }}"
                                aria-pressed="false"
                                data-ag-propiedad-mapa-marcador-colocar="{{ __('comercial.propiedades.mapa_marcador_colocar') }}"
                                data-ag-propiedad-mapa-marcador-sacar="{{ __('comercial.propiedades.mapa_marcador_sacar') }}"
                            >
                                <x-atoms.icon name="add_location" data-ag-propiedad-mapa-icono-marcador />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="zoom-completo"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_zoom_completo') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_zoom_completo') }}"
                            >
                                <x-atoms.icon name="zoom_out_map" />
                            </button>
                            <button
                                type="button"
                                class="ag-mapa-accion"
                                data-ag-propiedad-mapa-accion="expandir"
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_expandir') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_expandir') }}"
                                aria-pressed="false"
                                data-ag-propiedad-mapa-expandir-entrar="{{ __('comercial.propiedades.mapa_expandir') }}"
                                data-ag-propiedad-mapa-expandir-salir="{{ __('comercial.propiedades.mapa_salir_expandir') }}"
                            >
                                <x-atoms.icon name="fullscreen" data-ag-propiedad-mapa-icono-expandir />
                            </button>
                        </div>

                        {{-- Buscador de coordenadas, arriba a la izquierda:
                             SOLO centra la vista, nunca mueve el marcador
                             (para eso está la acción "Colocar marcador"). Un
                             `<div>`, no un `<form>` — ya está DENTRO del
                             `<form>` del formulario, y HTML no admite
                             formularios anidados. --}}
                        <div class="ag-propiedad-mapa__buscador" data-ag-propiedad-buscador>
                            <button
                                type="button"
                                class="ag-propiedad-mapa__buscador-boton"
                                data-ag-propiedad-buscador-boton
                                data-ag-propiedad-mapa-tooltip="{{ __('comercial.propiedades.mapa_buscador_label') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_buscador_label') }}"
                            >
                                <x-atoms.icon name="search" />
                            </button>
                            <input
                                type="text"
                                class="ag-propiedad-mapa__buscador-input"
                                placeholder="{{ __('comercial.propiedades.mapa_buscador_placeholder') }}"
                                aria-label="{{ __('comercial.propiedades.mapa_buscador_label') }}"
                                data-ag-propiedad-buscador-input
                            >
                            <p class="ag-propiedad-mapa__buscador-error" role="alert" hidden data-ag-propiedad-buscador-error>
                                {{ __('comercial.propiedades.mapa_buscador_error') }}
                            </p>
                        </div>

                        {{-- Coordenadas del marcador (solo lectura) y
                             superficie dibujada, abajo a la derecha. --}}
                        <div class="ag-propiedad-mapa__info">
                            <span class="ag-propiedad-mapa__coordenadas" data-ag-propiedad-coordenadas-texto></span>
                            <span
                                class="ag-propiedad-mapa__medida"
                                data-ag-propiedad-medida-texto
                                data-ag-propiedad-medida-plantilla="{{ __('comercial.propiedades.mapa_medida') }}"
                            ></span>
                        </div>
                    </div>

                    <p class="ag-input__help ag-propiedad-mapa__ayuda">{{ __('comercial.propiedades.campo_geometria_ayuda') }}</p>
                </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('comercial.propiedades.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.propiedades.edit', $propiedad)" variant="outline">
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
