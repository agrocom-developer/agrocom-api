{{--
    Partial: mapa de un lote (extraído de `_lote-fila.blade.php`, 16/9/2026 —
    pedido directo: el mapa va en su propia sección del formulario, "Mapa",
    separada de "Datos del lote", mismo criterio que la sección de mapa de
    `propiedades/mapa.blade.php`).

    Espera:
    - $lote (array{geometria?: string}), $prefijo (string): mismo par que
      `_lote-fila.blade.php` — ver su docblock.
    - $proveedorMapa (array{proveedor: 'google'|'leaflet', googleMapsApiKey: ?string}):
      resuelto por ResolverProveedorMapa (Comercial/Aplicacion), que a su vez
      consulta LecturaConfiguracion (Compartido, tarea 78). SIEMPRE llega
      desde afuera vía el scope compartido de @include — LotesController lo
      agrega a `create`/`edit`. La llave solo se imprime cuando el proveedor
      elegido es Google: es lo que evita que `google_maps_api_key` viaje al
      HTML de un formulario que va a usar Leaflet igual (tarea 79).

    `geometria` se dibuja sobre un MAPA SATELITAL: el perímetro de un lote se
    reconoce mirando la imagen, no tipeando pares de coordenadas. Leaflet +
    Esri + Geoman por defecto; Google Maps cuando hay llave configurada
    (tarea 79) — el mismo GeoJSON sale de cualquiera de los dos.
    `organisms/lote-mapa-editor.js` inicializa TODO `[data-ag-lote-mapa]` que
    encuentre en la página al cargar, sin JS adicional acá.

    El valor viaja como string JSON en un `<input hidden>`: el Form Request
    valida la forma mínima (`type`/`coordinates`), y una geometría cargada
    por otra vía sigue siendo válida.
--}}
@php
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $esGoogle = ($proveedorMapa['proveedor'] ?? 'leaflet') === 'google';
@endphp
<div
    class="ag-input ag-form-section__field--full ag-lote-mapa"
    data-ag-lote-mapa
    data-ag-lote-mapa-proveedor="{{ $esGoogle ? 'google' : 'leaflet' }}"
    @if ($esGoogle)
        data-ag-lote-mapa-google-key="{{ $proveedorMapa['googleMapsApiKey'] }}"
    @endif
>
    <span class="ag-input__label">{{ __('comercial.lotes.lote_geometria') }}</span>

    @error($erroresPrefijo.'.geometria')
        <p class="ag-input__error" role="alert">{{ $message }}</p>
    @enderror

    {{-- El valor real. Lo escribe el editor; queda en el DOM aunque el mapa
         no llegue a cargar, así que una geometría ya guardada nunca se
         pierde por un fallo del JS. --}}
    <input
        type="hidden"
        name="{{ $prefijo }}[geometria]"
        id="{{ $idBase }}-geometria"
        value="{{ $lote['geometria'] ?? '' }}"
        data-ag-lote-geometria
    >

    {{-- Pantalla completa (Fullscreen API, con respaldo a un contenedor
         fijo al 100% si el navegador la niega) actúa sobre ESTE marco:
         lienzo + acciones + medida viajan juntos, así la superficie sigue
         visible mientras se dibuja a pantalla completa (tarea 79).

         Acciones flotantes DENTRO del lienzo (16/9/2026, pedido directo:
         "quiero un componente compartido" — mismo patrón visual y de
         interacción que `propiedades/mapa.blade.php`, no una barra externa
         aparte): vértices siempre editables una vez dibujado el polígono,
         un solo botón "dibujar" en vez de dibujar/editar/mover separados
         — ver `organisms/lote-mapa-editor.js`. Dos niveles como en
         Propiedad: `-lienzo` es el marco (ancla los overlays), `-mapa` es
         el div que se le pasa A LEAFLET/GOOGLE — Google Maps toma
         posesión de ESE elemento y borra cualquier hijo que ya tuviera,
         así que las acciones tienen que ser HERMANAS del mapa, no hijas. --}}
    <div class="ag-mapa-marco" data-ag-lote-mapa-marco>
        <div class="ag-lote-mapa__lienzo" data-ag-lote-mapa-lienzo>
            <div class="ag-lote-mapa__mapa" data-ag-lote-mapa-mapa></div>

            <div
                class="ag-lote-mapa__acciones"
                role="toolbar"
                aria-label="{{ __('comercial.lotes.lote_mapa_barra_aria') }}"
            >
                <button
                    type="button"
                    class="ag-mapa-accion"
                    data-ag-lote-accion="dibujar"
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_dibujar') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_dibujar') }}"
                    aria-pressed="false"
                    data-ag-lote-mapa-dibujar-iniciar="{{ __('comercial.lotes.lote_mapa_dibujar') }}"
                    data-ag-lote-mapa-dibujar-terminar="{{ __('comercial.lotes.lote_mapa_dibujar_terminar') }}"
                >
                    <x-atoms.icon name="draw" data-ag-lote-mapa-icono-dibujar />
                </button>
                <button
                    type="button"
                    class="ag-mapa-accion"
                    data-ag-lote-accion="borrar"
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_borrar') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_borrar') }}"
                >
                    <x-atoms.icon name="delete" />
                </button>
                <button
                    type="button"
                    class="ag-mapa-accion"
                    data-ag-lote-accion="deshacer"
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_deshacer') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_deshacer') }}"
                    disabled
                >
                    <x-atoms.icon name="undo" />
                </button>
                <button
                    type="button"
                    class="ag-mapa-accion"
                    data-ag-lote-accion="capa"
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_capa_calles') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_capa_calles') }}"
                    aria-pressed="false"
                    data-ag-lote-mapa-capa-satelite="{{ __('comercial.lotes.lote_mapa_capa_satelite') }}"
                    data-ag-lote-mapa-capa-calles="{{ __('comercial.lotes.lote_mapa_capa_calles') }}"
                >
                    <x-atoms.icon name="layers" />
                </button>
                <button
                    type="button"
                    class="ag-mapa-accion"
                    data-ag-lote-accion="centrar"
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_centrar') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_centrar') }}"
                >
                    <x-atoms.icon name="center_focus_strong" />
                </button>
                <button
                    type="button"
                    class="ag-mapa-accion ag-mapa-accion--pantalla-completa"
                    data-ag-lote-mapa-boton-pantalla-completa
                    data-ag-lote-mapa-tooltip="{{ __('comercial.lotes.lote_mapa_pantalla_completa') }}"
                    aria-label="{{ __('comercial.lotes.lote_mapa_pantalla_completa') }}"
                    aria-pressed="false"
                    data-ag-lote-mapa-entrar="{{ __('comercial.lotes.lote_mapa_pantalla_completa') }}"
                    data-ag-lote-mapa-salir="{{ __('comercial.lotes.lote_mapa_salir_pantalla_completa') }}"
                >
                    <x-atoms.icon name="fullscreen" data-ag-lote-mapa-icono-pantalla-completa />
                </button>
            </div>

            {{-- Cuadro flotante abajo a la izquierda con la superficie
                 dibujada (16/9/2026, pedido directo: "el mismo que tenemos
                 en propiedad") — mismo lugar y chrome que
                 `.ag-propiedad-mapa__info`, sin las coordenadas (el lote no
                 tiene marcador de referencia). --}}
            <div
                class="ag-lote-mapa__info"
                data-ag-lote-medida
                hidden
                data-ag-lote-mapa-medida-plantilla="{{ __('comercial.lotes.lote_mapa_medida') }}"
                data-ag-lote-mapa-medida-plantilla-declarada="{{ __('comercial.lotes.lote_mapa_medida_declaradas') }}"
            >
                <span data-ag-lote-medida-texto></span>
            </div>
        </div>

        <div class="ag-lote-mapa__pie">
            <p class="ag-input__help ag-lote-mapa__ayuda">{{ __('comercial.lotes.lote_geometria_ayuda') }}</p>

            {{-- Botón y no autocompletado: `hectareas` es la superficie
                 CONTRATADA, que puede no coincidir con el polígono dibujado,
                 y es la base de lo que se factura (invariante 6). La
                 decisión de copiarla es de quien carga el campo. --}}
            <x-atoms.button type="button" variant="text" size="sm" data-ag-lote-usar-superficie>
                {{ __('comercial.lotes.lote_usar_superficie') }}
            </x-atoms.button>
        </div>
    </div>
</div>
