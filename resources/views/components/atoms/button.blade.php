{{--
    Atom: button
    Sin lógica de negocio ni wiring a ningún caso de uso: quien lo consume
    decide la acción (atributo `type`, `href`, o los `wire:click`/`onclick`
    que agregue vía `$attributes`, que este átomo respeta y no pisa).

    Props:
    - variant: primary|accent|outline|text|danger|danger-outline|
      warning-outline|success-outline|info-outline|distintivo-2-outline|
      alert-outline (default "primary").
      danger-outline (auditoría visual externa, obs. #4): mismo tono
      semántico que "danger" pero sin relleno — para acciones dentro de una
      franja de alerta, donde un botón sólido compite con el CTA primario
      del pliegue ("Programar sesión"). Sigue la misma anatomía que
      "outline" (transparente + borde + hover con `-subtle`), coloreada con
      los tokens de danger en vez de primary.
      warning-outline: mismo criterio, coloreado con los tokens de warning —
      el botón "Editar" de los listados, para distinguirlo del resto de las
      acciones outline neutras de la fila.
      success-outline/info-outline/distintivo-2-outline/alert-outline
      (18/9/2026, pedido explícito del usuario): mismo criterio, para la
      acción que dispara una transición de máquina de estados — el botón
      usa el color del ESTADO DE LLEGADA, no el verde genérico de
      "outline" a secas. En `contratos/index.blade.php`: "Aprobar"/
      "Reanudar" (→ vigente) usan success-outline; "Pausar" (→ pausado,
      reasignado de warning a info) usa info-outline; "Finalizar" (→
      finalizado, reasignado de info a distintivo-2/"purple") usa
      distintivo-2-outline. En `campanias/index.blade.php`: "Cerrar" (→
      cerrada) usa alert-outline (rojo-700 `#880000`, más oscuro que
      `danger`/rojo-600 — distinto del botón "Eliminar", que sí es
      `danger-outline`). La asignación estado↔color es una decisión de
      negocio del usuario, no fija — se reasignó más de una vez en la
      misma sesión sin que el CRITERIO cambie (siempre botón = color del
      estado de llegada). "outline" a secas se queda para acciones que NO
      cambian de estado (navegación, "más acciones", etc.) — sigue siendo
      el default neutro de marca.
    - size: sm|md|lg (default "md").
    - type: button|submit|reset (default "button"), ignorado si se pasa `href`.
    - href: si se pasa, renderiza <a> en vez de <button>.
    - icon: nombre de ícono Material Symbols.
    - iconPosition: "start" (default, ícono antes del texto — comportamiento
      de siempre, sin cambios para ningún consumidor existente) | "end"
      (ícono después del texto — p. ej. "Iniciar sesión" + `arrow_forward`
      al final, rediseño de login HU-02 tercera vuelta). No cambia CSS: el
      `gap` del flex de `.ag-button` ya se aplica sea cual sea el orden de
      los hijos, así que alcanza con invertir el orden de render en Blade.
    - loading (bool): reemplaza el ícono por un spinner y deshabilita el botón
      (solo estado visual — el llamador decide cuándo está en `loading`).
    - block (bool): ocupa el 100% del ancho disponible.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'start',
    'loading' => false,
    'block' => false,
])

@php
    $classes = [
        'ag-button',
        "ag-button--{$variant}",
        "ag-button--{$size}",
        $block ? 'ag-button--block' : '',
    ];
    $iconAtEnd = $iconPosition === 'end';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($iconAtEnd)
            <span class="ag-button__label">{{ $slot }}</span>
            @if ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
        @else
            @if ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
            <span class="ag-button__label">{{ $slot }}</span>
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if ($loading) aria-busy="true" disabled @endif
    >
        @if ($iconAtEnd)
            <span class="ag-button__label">{{ $slot }}</span>
            @if ($loading)
                <span class="ag-button__spinner" aria-hidden="true"></span>
            @elseif ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
        @else
            @if ($loading)
                <span class="ag-button__spinner" aria-hidden="true"></span>
            @elseif ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
            <span class="ag-button__label">{{ $slot }}</span>
        @endif
    </button>
@endif
