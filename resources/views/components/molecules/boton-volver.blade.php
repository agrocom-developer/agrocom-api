{{--
    Molecule: boton-volver (memento de navegación, 17/9/2026)
    Botón "Volver" del header de un formulario — reemplaza el
    `<x-atoms.button :href="route('panel.X.index')">` que hasta acá repetían
    a mano ~40 pantallas del panel, siempre fijo al listado del propio
    módulo. Si el usuario llegó por un acceso directo (crear cliente desde el
    formulario de contrato, "Nueva orden" desde el resumen de un cliente,
    etc.), vuelve a ESE origen y lo dice ("Volver a Nuevo contrato"); si no,
    cae al `href`/`label` de siempre.

    De dónde sale "el origen": `session('navegacion_origen')` —
    `['url' => ..., 'etiqueta' => ...]|null` — leído DIRECTO de sesión acá
    adentro, no como prop: un componente Blade NO hereda las variables de la
    vista que lo incluye (a diferencia de un `@include`), así que pasarlo
    como `$origenNavegacion` ambiental nunca habría llegado — bug real de la
    primera vuelta de este componente, detectado probando el flujo completo.
    Lo escribe `RecordarOrigenNavegacion` (middleware `origen.navegacion`) en
    cuanto ve `?volver_a=&volver_texto=` en la URL, y sobrevive en sesión todo
    el sub-flujo (alta → edición) sin que ningún controlador lo vuelva a
    tocar. Se reinicia solo al visitar un listado (`*.index`) — entrar por un
    módulo a propósito.

    Complementario al botón "Volver al formulario origen" que ya existe en
    Clientes/Propiedades/Lotes (pie del form, solo en edición, con el id
    recién creado precargado en la URL) — ESE sigue con su propio mecanismo
    `volverA`/`volver_a` por ahora; este átomo cubre el botón de CABECERA, en
    alta Y edición, sin precarga de id.

    Props:
    - href (requerido): destino por defecto (el índice del propio módulo).
    - label (requerido): texto por defecto ya traducido (ADR 0013) — se usa
      tal cual cuando no hay origen guardado.
--}}
@props(['href', 'label'])

@php
    $origen = session('navegacion_origen');
@endphp

<x-atoms.button
    :href="$origen['url'] ?? $href"
    variant="outline"
    icon="arrow_back"
    {{ $attributes }}
>
    {{ $origen !== null ? __('ui.navegacion.volver_a', ['origen' => $origen['etiqueta']]) : $label }}
</x-atoms.button>
