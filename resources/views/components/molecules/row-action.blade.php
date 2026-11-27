{{--
    Molecule: row-action (`.ag-row-action`)
    Una acción de fila de tabla. Solo se usa DENTRO de un slot de
    `organisms/row-actions`, que es quien fija el orden (Ver, Editar, Aprobar,
    transiciones, Eliminar) y decide si las acciones se ven como botones de ícono
    o dentro del menú de fila.

    El `kind` fija el color, para que nadie lo elija a mano:
    - ver → info-outline · editar → warning-outline · aprobar → success-outline
    - transicion → `<tono>-outline` del estado de llegada (prop `tone`); `danger` se
      traduce a `alert` (el rojo vino es solo de Eliminar) y suspender, bloquear u
      ocultar usan `neutral` (gris)
    - eliminar → danger-outline (token de peligro, rojo vino)

    El texto va siempre (`label`): como `title` y `aria-label` del botón de ícono, y
    como texto visible dentro del menú. Nunca es un botón mudo.

    Props:
    - kind (ver|editar|aprobar|transicion|eliminar, requerido).
    - label (requerido): ya traducido.
    - icon (requerido): ícono Material Symbols.
    - href (nullable): si se pasa, es un enlace (Ver y Editar).
    - modal (nullable): id del modal que abre (acciones que confirman). Sin `href` ni
      `modal`, es un botón común: el llamador lo enlaza por atributos (`form`, `type`).
    - tone (nullable): solo para `transicion`; tono del estado de llegada (success,
      info, warning, danger, alert, neutral, distintivo-1, distintivo-2, primary-2).
--}}
@props([
    'kind',
    'label',
    'icon',
    'href' => null,
    'modal' => null,
    'tone' => 'neutral',
])

@php
    $variante = match ($kind) {
        'ver' => 'info-outline',
        'editar' => 'warning-outline',
        'aprobar' => 'success-outline',
        'eliminar' => 'danger-outline',
        // El rojo vino (`danger`) es solo de Eliminar: una transición cuyo estado de llegada
        // es de peligro (rechazar) se pinta `alert`, y suspender/bloquear/ocultar van en gris.
        default => ($tone === 'danger' ? 'alert' : $tone).'-outline',
    };
@endphp

<span class="ag-row-action" data-ag-row-action="{{ $kind }}">
    <x-atoms.button
        :href="$href"
        :variant="$variante"
        size="sm"
        :icon="$icon"
        :title="$label"
        :aria-label="$label"
        :data-bs-toggle="$modal !== null ? 'modal' : null"
        :data-bs-target="$modal !== null ? '#'.$modal : null"
        {{ $attributes }}
    ><span class="ag-row-action__label">{{ $label }}</span></x-atoms.button>
</span>
