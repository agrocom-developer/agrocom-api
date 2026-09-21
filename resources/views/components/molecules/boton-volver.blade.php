{{--
    Molecule: boton-volver (memento de navegación, 17/9/2026 — pila el mismo
    día). Botón "Volver" del header de un formulario — reemplaza el
    `<x-atoms.button :href="route('panel.X.index')">` que hasta acá repetían
    a mano ~40 pantallas del panel, siempre fijo al listado del propio
    módulo. Si el usuario llegó por una cadena de accesos directos (cliente →
    "Nueva propiedad" → propiedad → "Generar lotes" → ...), vuelve un
    escalón hacia atrás y lo dice ("Volver a Propiedad Santa Cecilia"); si no
    hay ningún escalón apilado, cae al `href`/`label` de siempre.

    De dónde sale "el escalón": `session('navegacion_pila')` — lista de
    `['url' => ..., 'etiqueta' => ...]`, tope = último elemento — leída
    DIRECTO de sesión acá adentro, no como prop: un componente Blade NO
    hereda las variables de la vista que lo incluye (a diferencia de un
    `@include`), así que pasarla ambiental nunca habría llegado — bug real
    de la primera vuelta de este componente, detectado probando el flujo
    completo. La apila `RecordarOrigenNavegacion` (middleware
    `origen.navegacion`) en cuanto ve `?volver_a=&volver_texto=` en la URL, y
    sobrevive en sesión todo el sub-flujo (alta → edición) de CADA escalón,
    sin que ningún controlador la vuelva a tocar.

    El link que arma este átomo agrega `?_volver=1` al tope — es la marca que
    el mismo middleware usa para sacar ESE escalón de la pila al aterrizar
    (y redirigir a la misma URL sin la marca, para que un F5 posterior no
    saque otro escalón de encima). Por eso "Volver" nunca es un simple
    `<a href>` estático cuando hay pila: cada click consume un escalón.

    Complementario al botón "Volver al formulario origen" que ya existe en
    Clientes/Propiedades/Lotes (pie del form, solo en edición, con el id
    recién creado precargado en la URL) — ESE sigue con su propio mecanismo
    `volverA`/`volver_a`; este átomo cubre el botón de CABECERA, en alta Y
    edición.

    Lo recién creado viaja de vuelta (19/9/2026, pedido directo): la ficha
    de un cliente/propiedad/lote recién dado de alta pasa `retorno` con su
    id, y el escalón al que se vuelve lo recibe en la URL — el formulario de
    contratos lo lee y deja lo nuevo ya seleccionado
    (`resources/js/pages/contratos-form.js`). Solo aplica cuando se vuelve a
    un escalón de la pila; con el `href` por defecto no se agrega nada.

    Props:
    - href (requerido): destino por defecto (el índice del propio módulo).
    - label (requerido): texto por defecto ya traducido (ADR 0013) — se usa
      tal cual cuando no hay ningún escalón apilado.
    - retorno (array<string, int|string>, default []): parámetros de query
      que se suman a la URL del escalón (p. ej. `['cliente_id' => 7]`); si la
      URL ya trae uno con ese nombre, gana este. Vacío en el alta (todavía no
      hay nada creado que devolver).
    - cancelar (bool, default false): el «Cancelar» del pie del formulario
      (21/9/2026, hallazgo del dueño en Cuadrillas: «Volver» respetaba la pila
      y «Cancelar» caía siempre al listado del módulo). Mismo destino y mismo
      consumo del escalón que «Volver», pero el texto queda fijo en «Cancelar»
      y no lleva flecha. `label` es opcional en este modo: solo para un pie
      que dice otra cosa («Cerrar» en una ficha de solo lectura).
--}}
@props(['href', 'label' => null, 'retorno' => [], 'cancelar' => false])

@php
    $pila = session('navegacion_pila', []);
    $tope = $pila === [] ? null : end($pila);
    $destino = $tope !== null
        ? \Illuminate\Support\Uri::of($tope['url'])->withQuery([...$retorno, '_volver' => 1])->value()
        : $href;
@endphp

<x-atoms.button
    :href="$destino"
    variant="outline"
    :icon="$cancelar ? null : 'arrow_back'"
    {{ $attributes }}
>
    @if ($cancelar)
        {{ $label ?? __('ui.action.cancel') }}
    @else
        {{ $tope !== null ? __('ui.navegacion.volver_a', ['origen' => $tope['etiqueta']]) : $label }}
    @endif
</x-atoms.button>
