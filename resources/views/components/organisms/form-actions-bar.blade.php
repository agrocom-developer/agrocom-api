{{--
    Organism: form-actions-bar (tarea 31 — arquetipo formulario, §6.3 regla 5
    de docs/diseno/guia_pantalla_panel.md)
    Barra de acciones pegajosa al pie de un formulario largo, con el estado
    de guardado en texto ("Sin cambios pendientes" / "Cambios sin guardar")
    y las mismas acciones de `page-header`, para que el usuario no tenga que
    scrollear hasta la cabecera para guardar. Ocupa una franja completa de
    la pantalla y orquesta las acciones del llamador — organism, mismo
    criterio que `page-header`.

    Props:
    - status (nullable): texto de estado de guardado, ya traducido.

    Slot con nombre:
    - actions: botones (`atoms/button`), mismo criterio que `page-header`
      (un único sólido, el resto `outline`).

    Arranca con `hidden` en el propio HTML (15/9/2026, pedido directo sobre
    el formulario de cliente): así no hay parpadeo de "aparece visible y
    después la esconde el JS" mientras carga la página —
    `resources/js/shared/barra-acciones-dirty.js` (cargado en TODA página vía
    `app.js`) le saca el `hidden` en la primera interacción del usuario con
    el formulario. Sin ese script (JS deshabilitado) la barra queda oculta:
    el formulario sigue siendo enviable con Enter sobre un campo de texto
    (submit implícito nativo, no depende de que el botón sea visible), así
    que no es un camino sin salida — solo pierde el atajo visible.
--}}
@props([
    'status' => null,
])

<div {{ $attributes->class(['ag-form-actions-bar']) }} hidden>
    @if ($status)
        <p class="ag-form-actions-bar__status">{{ $status }}</p>
    @endif

    @isset($actions)
        <div class="ag-form-actions-bar__actions">{{ $actions }}</div>
    @endisset
</div>
