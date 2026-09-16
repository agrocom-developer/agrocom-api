{{--
    Atom: image-modal (15/9/2026)
    Imagen clickeable que abre un modal con la versión completa al hacer
    clic — para previews recortados/chicos (el preview de `molecules/file-field`,
    logos de listados) donde el usuario puede querer ver el archivo real sin
    descargarlo.

    Sin JS propio: usa el modal nativo de Bootstrap (`data-bs-toggle`/
    `data-bs-target`, ya cargado en `resources/js/app.js` como el resto del
    panel — ver `data-bs-toggle="tooltip"`/`"dropdown"` ahí mismo). Abrir,
    cerrar con Escape, cerrar al clickear afuera y el foco atrapado adentro
    ya vienen resueltos por esa pieza; no hay que reinventarlos acá.

    NO es un modal genérico para cualquier contenido — es específico para
    una imagen. Si hiciera falta un modal de propósito general, es una pieza
    aparte.

    Props:
    - src (requerido): URL de la imagen a mostrar en grande.
    - alt (default ''): texto alternativo, compartido por el trigger y el
      modal.
    - label (nullable): título visible en la cabecera del modal y
      `aria-label` del trigger, ya traducido — sin esto, el trigger usa
      `ui.image_modal.ver_completa` como alternativa genérica.

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): fusiona
    en el `<button>` disparador, nunca en el modal — quien lo consume decide
    tamaño/recorte del preview (p. ej. `file-field` le pasa las clases que
    hoy tiene el `<img>` suelto).
--}}
@props([
    'src',
    'alt' => '',
    'label' => null,
])

@php
    $modalId = 'ag-image-modal-'.\Illuminate\Support\Str::random(8);
    $tituloId = "{$modalId}-titulo";
@endphp

<button
    type="button"
    {{ $attributes->class(['ag-image-modal-trigger']) }}
    data-bs-toggle="modal"
    data-bs-target="#{{ $modalId }}"
    aria-label="{{ $label ?? __('ui.image_modal.ver_completa') }}"
>
    <img src="{{ $src }}" alt="{{ $alt }}" class="ag-image-modal-trigger__img">
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $tituloId }}">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content ag-image-modal__content">
            <div class="modal-header">
                <h2 class="modal-title ag-page-header__title" id="{{ $tituloId }}">
                    {{ $label ?? __('ui.image_modal.ver_completa') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body ag-image-modal__body">
                <img src="{{ $src }}" alt="{{ $alt }}" class="ag-image-modal__img">
            </div>
        </div>
    </div>
</div>
