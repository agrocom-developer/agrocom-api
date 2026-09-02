{{--
    Molecule: file-field (tarea 31 — arquetipo formulario)
    Campo de archivo con preview: nombre/peso + acciones reemplazar/quitar.
    Hoy era markup suelto en `organizacion.css` (`.ag-organizacion__logo-preview*`)
    — pasa al catálogo porque cualquier pantalla con logo/adjunto/documento
    lo va a necesitar (Sprint 7: clientes, contratos). Sin lógica de subida
    real: los botones son decorativos, el llamador decide si van
    deshabilitados (mismo criterio que el resto de `pages/organizacion`,
    mockup sin persistencia).

    Props:
    - label (nullable): rótulo del campo, mismo lugar que `atoms/input`.
    - fileName (nullable): nombre del archivo actual.
    - fileSize (nullable): peso ya formateado (p. ej. "240 KB").
    - help (nullable): texto de ayuda bajo el control.
    - replaceLabel, removeLabel (nullable): copy de las dos acciones, ya
      traducido — si no se pasan, esa acción no se renderiza.

    Slot (default): preview (imagen, ícono, `atoms/logo`) — este componente
    no decide qué es el preview, solo el marco donde va.
--}}
@props([
    'label' => null,
    'fileName' => null,
    'fileSize' => null,
    'help' => null,
    'replaceLabel' => null,
    'removeLabel' => null,
])

<div {{ $attributes->class(['ag-file-field']) }}>
    @if ($label)
        <p class="ag-file-field__label">{{ $label }}</p>
    @endif

    <div class="ag-file-field__control">
        <span class="ag-file-field__preview">{{ $slot }}</span>

        <div class="ag-file-field__meta">
            @if ($fileName)
                <span class="ag-file-field__name">{{ $fileName }}</span>
            @endif
            @if ($fileSize)
                <span class="ag-file-field__size">{{ $fileSize }}</span>
            @endif
        </div>

        <div class="ag-file-field__actions">
            @if ($replaceLabel)
                <x-atoms.button type="button" variant="outline" size="sm" disabled>
                    {{ $replaceLabel }}
                </x-atoms.button>
            @endif
            @if ($removeLabel)
                <x-atoms.button type="button" variant="text" size="sm" disabled>
                    {{ $removeLabel }}
                </x-atoms.button>
            @endif
        </div>
    </div>

    @if ($help)
        <p class="ag-file-field__help">{{ $help }}</p>
    @endif
</div>
