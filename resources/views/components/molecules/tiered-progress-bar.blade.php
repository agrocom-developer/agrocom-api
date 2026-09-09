{{--
    Molecule: tiered-progress-bar (HU-52, tarea 75, espec §9.1) — barra de
    avance del informe de contratos, con color por tramo (invariante 11:
    cinco tokens `--ag-color-avance-*` en tokens/semantic/theme-*.css,
    ninguno hardcodeado acá). Mismo criterio de "no calcula nada" que
    `progress-meter`: `percent` y `tramo` ya vienen resueltos por el caso de
    uso (`TramoAvance::desde()`), este componente solo pinta — por eso
    `tramo` viaja como el `->value` del enum (string), nunca el enum en sí:
    el catálogo de componentes no importa clases de `App\Dominios\*`.

    El relleno visual se recorta a 100% de ancho (una barra no puede "salirse"
    del contenedor) — el tramo `mas-100` se distingue por COLOR, no por un
    ancho que rompería el layout, y el número de `percent` (sin recortar) va
    al lado para que el excedente real siga siendo legible.

    Props:
    - percent (requerido, int): puede superar 100.
    - tramo (requerido, string): `TramoAvance::value` — uno de
      `0-33|34-66|67-99|100|mas-100`.
    - label (nullable): rótulo sobre la barra (nombre de cultivo/cliente).
--}}
@props([
    'percent',
    'tramo',
    'label' => null,
])

@php
    $anchoVisual = max(0, min(100, (int) $percent));
@endphp

<div {{ $attributes->class(['ag-tiered-progress-bar']) }}>
    @if ($label !== null)
        <div class="ag-tiered-progress-bar__head">
            <span class="ag-tiered-progress-bar__label">{{ $label }}</span>
            <span class="ag-tiered-progress-bar__percent">{{ $percent }}%</span>
        </div>
    @endif

    <div
        class="ag-tiered-progress-bar__track"
        role="progressbar"
        aria-valuenow="{{ $percent }}"
        aria-valuemin="0"
        aria-valuemax="{{ max(100, (int) $percent) }}"
        @if ($label !== null) aria-label="{{ $label }}" @endif
    >
        <div
            class="ag-tiered-progress-bar__fill ag-tiered-progress-bar__fill--{{ $tramo }}"
            style="width: {{ $anchoVisual }}%"
        ></div>
    </div>
</div>
