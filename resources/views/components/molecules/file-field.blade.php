{{--
    Molecule: file-field (tarea 31 — arquetipo formulario)
    Campo de archivo con preview: nombre/peso + acciones reemplazar/quitar.

    Dos modos, según si se pasa `name`:
    - SIN `name` (default, comportamiento original): decorativo, ambos
      botones `disabled` — el llamador todavía no tiene backend real
      (mismo criterio que "Plan"/"Funcionalidades" de `pages/organizacion`).
    - CON `name` (11/9/2026, logo de empresa — ADR 0019): control real, SIN
      JavaScript. "Reemplazar" es un `<label for="inputId">` (nunca un botón
      con `onclick`) envolviendo un `<input type="file">` fuera de pantalla
      — la asociación label↔input abre el selector nativo con puro HTML.
      "Quitar" es el mismo truco con un `<input type="checkbox">`: tildarlo
      viaja como `{removeName}=1` en el POST, que el caso de uso interpreta
      como "borrar en el guardado" — nada de esto depende de JS para
      funcionar, coherente con el resto del catálogo (`atoms/date`,
      `atoms/select`: el control nativo es el real).

    Props:
    - label, fileName, fileSize, help (nullable): igual que antes.
    - replaceLabel, removeLabel (nullable): copy de las dos acciones, ya
      traducido — si no se pasan, esa acción no se renderiza.
    - name (nullable): habilita el modo real. `id` (default = name),
      `accept` (nullable, ej. ".png,.svg").
    - removeName (nullable): nombre del checkbox de "Quitar" — sin esto,
      "Quitar" queda decorativo aunque `name` esté presente (p. ej. un
      archivo que todavía no existe no tiene qué quitar).
    - disabled (bool, default false): fuerza el modo decorativo aunque haya
      `name` — mismo criterio ver/editar que el resto del panel
      (`:disabled="! $puedeEditar"`).
    - error (nullable): mensaje de validación del campo `name`.
    - size (sm|md|lg, default "md" — 14/9/2026): tamaño del cuadro de
      preview. "lg" es para un logo que es EL dato principal de la pantalla
      (`/panel/organizacion`, HU-19/ADR 0019) — en una fila de formulario
      compartida con otros campos ("Logo" de un cliente, tarea 91) el
      tamaño por defecto sigue siendo el correcto.

    Vista previa EN VIVO del archivo recién elegido (14/9/2026,
    `resources/js/molecules/file-field.js`): mejora progresiva vía JS — el
    campo en sí sigue funcionando sin JS (ver más arriba), solo la vista
    previa de un archivo todavía no guardado depende de él (no hay forma de
    hacerlo sin JS).

    Slot (default): preview cuando NO hay archivo real (ícono, `atoms/logo`
    como placeholder). Con archivo real, el propio componente pinta un
    `<img>` con la URL que el llamador ya resolvió — este componente no
    decide cómo se sirve el archivo, eso es responsabilidad del controlador.
--}}
@props([
    'label' => null,
    'fileName' => null,
    'fileSize' => null,
    'previewUrl' => null,
    'help' => null,
    'replaceLabel' => null,
    'removeLabel' => null,
    'name' => null,
    'id' => null,
    'accept' => null,
    'removeName' => null,
    'disabled' => false,
    'error' => null,
    'size' => 'md',
])

@php
    $esReal = $name !== null && ! $disabled;
    $inputId = $id ?? $name;
    $removeId = $removeName ? "{$inputId}-eliminar" : null;
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $error ? "{$inputId}-error" : null;
@endphp

<div {{ $attributes->class(['ag-file-field', "ag-file-field--{$size}"]) }}>
    @if ($label)
        <p class="ag-file-field__label">{{ $label }}</p>
    @endif

    <div class="ag-file-field__control {{ $error ? 'ag-file-field__control--error' : '' }}">
        <span class="ag-file-field__preview">
            @if ($previewUrl)
                <img src="{{ $previewUrl }}" alt="" class="ag-file-field__preview-img">
            @else
                {{ $slot }}
            @endif
        </span>

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
                @if ($esReal)
                    <input
                        type="file"
                        name="{{ $name }}"
                        id="{{ $inputId }}"
                        @if ($accept) accept="{{ $accept }}" @endif
                        class="ag-file-field__native"
                        @if ($describedBy = trim(($helpId ?? '').' '.($errorId ?? ''))) aria-describedby="{{ $describedBy }}" @endif
                    >
                    <label for="{{ $inputId }}" class="ag-button ag-button--outline ag-button--sm">
                        {{ $replaceLabel }}
                    </label>
                @else
                    <x-atoms.button type="button" variant="outline" size="sm" disabled>
                        {{ $replaceLabel }}
                    </x-atoms.button>
                @endif
            @endif
            @if ($removeLabel)
                @if ($esReal && $removeName)
                    <input
                        type="checkbox"
                        name="{{ $removeName }}"
                        value="1"
                        id="{{ $removeId }}"
                        class="ag-file-field__native"
                    >
                    <label for="{{ $removeId }}" class="ag-button ag-button--text ag-button--sm">
                        {{ $removeLabel }}
                    </label>
                @else
                    <x-atoms.button type="button" variant="text" size="sm" disabled>
                        {{ $removeLabel }}
                    </x-atoms.button>
                @endif
            @endif
        </div>
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-file-field__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-file-field__error" role="alert">{{ $error }}</p>
    @endif
</div>
