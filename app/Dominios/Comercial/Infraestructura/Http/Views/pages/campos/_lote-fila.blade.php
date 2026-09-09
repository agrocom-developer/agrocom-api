{{--
    Partial: fila de lote del formulario de campo (HU-24, tarea 35) — mismo
    patrón que `clientes/_contacto-fila.blade.php` (tarea 33): una fila
    repetible dentro de la sección "Lotes" de `_formulario.blade.php`, reusada
    para pintar los lotes existentes, para repetir `old('lotes')` tras un
    error de validación, y como plantilla que clona
    `resources/js/pages/campos-form.js` al apretar "Agregar lote".

    Espera:
    - $indice (int|string): posición dentro del array `lotes[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`, que el JS
      reemplaza por el próximo número al clonar. No hace falta si se pasa
      `$prefijo` explícito (ver abajo).
    - $lote (array{id?: int, codigo?: string, hectareas?: string,
      geometria?: string, restricciones?: string}): vacío en una fila nueva.
    - $prefijo (string, opcional): prefijo de los `name` de los campos —
      por defecto `lotes[{indice}]` (el caso de siempre: fila dentro del
      array del formulario de propiedad). La ficha de un lote suelto
      (`pages/lotes/_formulario.blade.php`, tarea 77) pasa `lote` a secas,
      así que sus campos viajan como `lote[codigo]`, `lote[hectareas]`, etc.
      — el mismo partial, sin envolver un único lote en un array de uno.
    - $mostrarQuitar (bool, opcional): `true` por defecto. La ficha de un
      lote suelto no tiene botón "Quitar" — la baja de ESE lote es la acción
      "Eliminar" de su propia página, no "sacarlo de esta lista".

    `geometria` se dibuja sobre un MAPA SATELITAL (Leaflet + Geoman): el
    perímetro de un lote se reconoce mirando la imagen, no tipeando pares de
    coordenadas. Antes era un `<textarea>` donde había que pegar el GeoJSON a
    mano — un editor de mapa es lo que la tarea 68 vino a reemplazar.
    `organisms/lote-mapa-editor.js` inicializa TODO `[data-ag-lote-mapa]` que
    encuentre en la página al cargar, así que funciona igual en el array de
    la propiedad y en la ficha suelta del lote sin JS adicional.

    El valor sigue viajando como el MISMO string JSON en un `<input hidden>`,
    así que el Form Request no cambia: valida la forma mínima
    (`type`/`coordinates`) igual que antes, y una geometría cargada por otra
    vía sigue siendo válida.
--}}
@php
    $prefijo ??= "lotes[{$indice}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $mostrarQuitar ??= true;
@endphp
<div class="ag-campos-form__lote" data-ag-lote-fila>
    @if (! empty($lote['id']))
        <input type="hidden" name="{{ $prefijo }}[id]" value="{{ $lote['id'] }}">
    @endif

    <x-atoms.input
        type="text"
        name="{{ $prefijo }}[codigo]"
        label="{{ __('comercial.campos.lote_codigo') }}"
        value="{{ $lote['codigo'] ?? '' }}"
        required
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas]"
        label="{{ __('comercial.campos.lote_hectareas') }}"
        value="{{ $lote['hectareas'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
    />

    <div class="ag-input ag-form-section__field--full ag-lote-mapa" data-ag-lote-mapa>
        <span class="ag-input__label">{{ __('comercial.campos.lote_geometria') }}</span>

        {{-- El valor real. Lo escribe el editor; queda en el DOM aunque el
             mapa no llegue a cargar, así que una geometría ya guardada nunca
             se pierde por un fallo del JS. --}}
        <input
            type="hidden"
            name="{{ $prefijo }}[geometria]"
            id="{{ $idBase }}-geometria"
            value="{{ $lote['geometria'] ?? '' }}"
            data-ag-lote-geometria
        >

        {{-- Pantalla completa (Fullscreen API, con respaldo a un contenedor
             fijo al 100% si el navegador la niega) actúa sobre ESTE marco:
             lienzo + barra + medida viajan juntos, así la superficie sigue
             visible mientras se dibuja a pantalla completa (tarea 79). --}}
        <div class="ag-lote-mapa__marco" data-ag-lote-mapa-marco>
            <div
                class="ag-lote-mapa__barra"
                role="toolbar"
                aria-label="{{ __('comercial.campos.lote_mapa_barra_aria') }}"
                data-ag-lote-mapa-barra
            >
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="dibujar"
                    title="{{ __('comercial.campos.lote_mapa_dibujar') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_dibujar') }}"
                    aria-pressed="false"
                >
                    <x-atoms.icon name="draw" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="editar"
                    title="{{ __('comercial.campos.lote_mapa_editar_vertices') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_editar_vertices') }}"
                    aria-pressed="false"
                >
                    <x-atoms.icon name="edit" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="mover"
                    title="{{ __('comercial.campos.lote_mapa_mover') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_mover') }}"
                    aria-pressed="false"
                >
                    <x-atoms.icon name="open_with" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="borrar"
                    title="{{ __('comercial.campos.lote_mapa_borrar') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_borrar') }}"
                    aria-pressed="false"
                >
                    <x-atoms.icon name="delete" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="deshacer"
                    title="{{ __('comercial.campos.lote_mapa_deshacer') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_deshacer') }}"
                    disabled
                >
                    <x-atoms.icon name="undo" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="centrar"
                    title="{{ __('comercial.campos.lote_mapa_centrar') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_centrar') }}"
                >
                    <x-atoms.icon name="center_focus_strong" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion"
                    data-ag-lote-accion="capa"
                    title="{{ __('comercial.campos.lote_mapa_capa_calles') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_capa_calles') }}"
                    aria-pressed="false"
                    data-ag-lote-mapa-capa-satelite="{{ __('comercial.campos.lote_mapa_capa_satelite') }}"
                    data-ag-lote-mapa-capa-calles="{{ __('comercial.campos.lote_mapa_capa_calles') }}"
                >
                    <x-atoms.icon name="layers" />
                </button>
                <button
                    type="button"
                    class="ag-lote-mapa__accion ag-lote-mapa__accion--pantalla-completa"
                    data-ag-lote-mapa-boton-pantalla-completa
                    title="{{ __('comercial.campos.lote_mapa_pantalla_completa') }}"
                    aria-label="{{ __('comercial.campos.lote_mapa_pantalla_completa') }}"
                    aria-pressed="false"
                    data-ag-lote-mapa-entrar="{{ __('comercial.campos.lote_mapa_pantalla_completa') }}"
                    data-ag-lote-mapa-salir="{{ __('comercial.campos.lote_mapa_salir_pantalla_completa') }}"
                >
                    <x-atoms.icon name="fullscreen" data-ag-lote-mapa-icono-pantalla-completa />
                </button>
            </div>

            <div class="ag-lote-mapa__lienzo" data-ag-lote-mapa-lienzo></div>

            <div class="ag-lote-mapa__pie">
                <p class="ag-input__help ag-lote-mapa__ayuda">{{ __('comercial.campos.lote_geometria_ayuda') }}</p>

                <div
                    class="ag-lote-mapa__medida"
                    data-ag-lote-medida
                    hidden
                    data-ag-lote-mapa-medida-plantilla="{{ __('comercial.campos.lote_mapa_medida') }}"
                    data-ag-lote-mapa-medida-plantilla-declarada="{{ __('comercial.campos.lote_mapa_medida_declaradas') }}"
                >
                    <span data-ag-lote-medida-texto></span>
                    {{-- Botón y no autocompletado: `hectareas` es la superficie
                         CONTRATADA, que puede no coincidir con el polígono
                         dibujado, y es la base de lo que se factura (invariante
                         6). La decisión de copiarla es de quien carga el campo. --}}
                    <x-atoms.button type="button" variant="text" size="sm" data-ag-lote-usar-superficie>
                        {{ __('comercial.campos.lote_usar_superficie') }}
                    </x-atoms.button>
                </div>
            </div>
        </div>
    </div>

    <x-atoms.textarea
        name="{{ $prefijo }}[restricciones]"
        id="{{ $idBase }}-restricciones"
        label="{{ __('comercial.campos.lote_restricciones') }}"
        value="{{ $lote['restricciones'] ?? '' }}"
        placeholder="{{ __('comercial.campos.lote_restricciones_placeholder') }}"
        rows="2"
    />

    @if ($mostrarQuitar)
        <div class="ag-form-section__field--full ag-campos-form__lote-pie">
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-quitar>
                {{ __('comercial.campos.lote_quitar') }}
            </x-atoms.button>
        </div>
    @endif
</div>
