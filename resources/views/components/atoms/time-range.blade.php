{{--
    Atom: time-range (19/9/2026)
    Rango horario —hora de inicio y hora de fin— en UNA sola casilla ("06:00 –
    10:00") con un selector de reloj circular estilo Material, en lugar de dos
    `<input type="time">` nativos: cada navegador los dibuja distinto, no
    respetan los tokens del panel ni el tema oscuro, y son dos casillas sueltas
    para un solo dato. Es propio (sin librería de terceros): flatpickr —el que ya
    usa `atoms/datetime`— no sabe de un rango horario en una casilla.

    Contrato de progressive enhancement, el mismo de `atoms/date`: los dos
    `<input type="time">` nativos son los controles REALES — tienen `name`, se
    envían con el formulario y son lo único que existe si
    `resources/js/atoms/time-range.js` no cargó. Si JS carga, los oculta
    (`.ag-time-range__native--enhanced`, sin `display:none`, para no romper el
    envío ni el foco programático), muestra el disparador único y escribe en los
    nativos `.value` (formato 24 h `H:i`, el que espera `date_format:H:i`) más los
    eventos `input` y `change`, así la barra de "cambios sin guardar" y cualquier
    otro oyente atado al `name` siguen funcionando.

    Cualquier código de la página puede deshabilitar el campo con solo cambiar
    `disabled` en los nativos (lo hace la tabla de lotes con "Día completo"): el
    componente observa ese atributo y se vuelve a leer solo — la página no toca
    nada interno. Las filas que nacen después de cargar la página se inicializan
    con `initTimeRanges(raiz)` (idempotente), exportado por el JS del átomo.

    El selector es un `<dialog>` modal (foco atrapado, Esc cierra, fondo inerte,
    todo nativo): cabecera con "HH:MM – HH:MM" y sus cuatro segmentos pulsables,
    dial de 24 h (anillo exterior 12·1–11, interior 00·13–23; minutos de 1 en 1 al
    arrastrar), y pie con teclado / Limpiar / Cancelar / Aceptar. Elegir una hora
    pasa sola a sus minutos, luego a la hora de fin y a sus minutos. Un modo de
    texto ("HH:MM") es la alternativa accesible al dial. Siempre 24 h, nunca a. m. /
    p. m., igual que el resto del panel.

    Props (los textos ya vienen traducidos por el llamador, ADR 0013; el "chrome"
    del control vive en `lang/es/ui.php` → `ui.time_range.*`):
    - nameStart, nameEnd (requeridos): `name` de los dos nativos.
    - id (nullable): identificador ÚNICO del campo en la página; de él salen los del
      diálogo. Si se clona el componente hay que darle uno distinto a cada copia.
    - valueStart, valueEnd (nullable): `H:i` (o `H:i:s`, se recorta a `H:i`).
    - label (nullable): rótulo visible; sin él, el disparador se rotula solo para
      lectores de pantalla ("Elegir horario").
    - help, error: strings ya traducidos, como en los demás átomos.
    - invalid (bool, default false): solo el borde de error, sin texto — para cuando
      el mensaje lo pinta el contenedor (una fila de tabla, p. ej.).
    - disabled (bool, default false).

    LSP (`$attributes`): la raíz solo fusiona `class`; el resto de atributos va a
    los dos nativos.
--}}
@props([
    'nameStart',
    'nameEnd',
    'id' => null,
    'valueStart' => null,
    'valueEnd' => null,
    'label' => null,
    'help' => null,
    'error' => null,
    'invalid' => false,
    'disabled' => false,
])

@php
    $campoId = $id ?? 'time-range-'.substr(md5($nameStart.'|'.$nameEnd), 0, 8);
    $dialogoId = "{$campoId}-dialogo";
    $ayudaId = $help ? "{$campoId}-ayuda" : null;
    $errorId = $error ? "{$campoId}-error" : null;
    $describedBy = trim(($ayudaId ?? '').' '.($errorId ?? ''));
    $rotulo = $label ?? __('ui.time_range.elegir_horario');

    // `H:i:s` (lo que devuelve la base) se recorta a `H:i`.
    $inicio = $valueStart ? substr((string) $valueStart, 0, 5) : '';
    $fin = $valueEnd ? substr((string) $valueEnd, 0, 5) : '';
    $mostrado = $inicio !== '' && $fin !== '' ? "{$inicio} – {$fin}" : null;
@endphp

<div
    {{ $attributes->class(['ag-time-range'])->only('class') }}
    data-ag-time-range
    data-label-placeholder="{{ __('ui.time_range.placeholder') }}"
    data-label-inicio="{{ __('ui.time_range.inicio') }}"
    data-label-fin="{{ __('ui.time_range.fin') }}"
    data-label-hora="{{ __('ui.time_range.hora') }}"
    data-label-minutos="{{ __('ui.time_range.minutos') }}"
    data-label-usar-teclado="{{ __('ui.time_range.usar_teclado') }}"
    data-label-usar-reloj="{{ __('ui.time_range.usar_reloj') }}"
    data-label-error-ambas="{{ __('ui.time_range.error_ambas') }}"
    data-label-error-orden="{{ __('ui.time_range.error_orden') }}"
    data-label-error-formato="{{ __('ui.time_range.error_formato') }}"
>
    @if ($label)
        <label for="{{ $campoId }}-trigger" class="ag-time-range__label">{{ $label }}</label>
    @endif

    <div class="ag-time-range__control {{ $error || $invalid ? 'ag-time-range__control--error' : '' }}">
        {{-- Controles reales: sin JS son lo único que se ve. --}}
        <input
            type="time"
            name="{{ $nameStart }}"
            value="{{ $inicio }}"
            class="ag-time-range__native"
            aria-label="{{ __('ui.time_range.inicio') }}"
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class') }}
        >
        <input
            type="time"
            name="{{ $nameEnd }}"
            value="{{ $fin }}"
            class="ag-time-range__native"
            aria-label="{{ __('ui.time_range.fin') }}"
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class') }}
        >

        {{-- Disparador y botón de limpiar: `hidden` hasta que el JS los arma. --}}
        <button
            type="button"
            class="ag-time-range__trigger"
            id="{{ $campoId }}-trigger"
            data-ag-time-range-trigger
            aria-haspopup="dialog"
            aria-controls="{{ $dialogoId }}"
            @if (! $label) aria-label="{{ $rotulo }}" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($disabled) disabled @endif
            hidden
        >
            <span class="ag-time-range__value {{ $mostrado ? '' : 'ag-time-range__value--placeholder' }}" data-ag-time-range-value>{{ $mostrado ?? __('ui.time_range.placeholder') }}</span>
            <x-atoms.icon name="schedule" size="sm" class="ag-time-range__icon" />
        </button>

        <button
            type="button"
            class="ag-time-range__clear"
            data-ag-time-range-clear
            aria-label="{{ __('ui.time_range.limpiar_horario') }}"
            hidden
        >
            <x-atoms.icon name="close" size="sm" />
        </button>
    </div>

    @if ($help)
        <p class="ag-time-range__help" id="{{ $ayudaId }}">{{ $help }}</p>
    @endif

    @if ($error)
        <p class="ag-input__error" id="{{ $errorId }}" role="alert">{{ $error }}</p>
    @endif

    {{-- Selector: el dial y los números los pinta time-range.js. Cerrado, un
         <dialog> no ocupa lugar (no rompe la grilla de la fila que lo contiene). --}}
    <dialog class="ag-time-range__dialog" id="{{ $dialogoId }}" aria-label="{{ $rotulo }}" data-ag-time-range-dialog>
        <div class="ag-time-range__header">
            <div class="ag-time-range__par">
                <span class="ag-time-range__par-etiqueta">{{ __('ui.time_range.inicio') }}</span>
                <span class="ag-time-range__par-tiempo">
                    <button type="button" class="ag-time-range__seg" data-seg="ih" aria-label="{{ __('ui.time_range.inicio') }}: {{ __('ui.time_range.hora') }}">--</button>
                    <span aria-hidden="true">:</span>
                    <button type="button" class="ag-time-range__seg" data-seg="im" aria-label="{{ __('ui.time_range.inicio') }}: {{ __('ui.time_range.minutos') }}">--</button>
                </span>
            </div>
            <span class="ag-time-range__guion" aria-hidden="true">–</span>
            <div class="ag-time-range__par">
                <span class="ag-time-range__par-etiqueta">{{ __('ui.time_range.fin') }}</span>
                <span class="ag-time-range__par-tiempo">
                    <button type="button" class="ag-time-range__seg" data-seg="fh" aria-label="{{ __('ui.time_range.fin') }}: {{ __('ui.time_range.hora') }}">--</button>
                    <span aria-hidden="true">:</span>
                    <button type="button" class="ag-time-range__seg" data-seg="fm" aria-label="{{ __('ui.time_range.fin') }}: {{ __('ui.time_range.minutos') }}">--</button>
                </span>
            </div>
        </div>

        <div class="ag-time-range__body">
            <div class="ag-time-range__reloj" data-ag-time-range-reloj>
                <svg class="ag-time-range__dial" viewBox="0 0 240 240" tabindex="0" role="slider" data-ag-time-range-dial></svg>
            </div>

            <div class="ag-time-range__teclado" data-ag-time-range-teclado hidden>
                <label class="ag-time-range__teclado-campo">
                    <span>{{ __('ui.time_range.inicio') }}</span>
                    <input type="text" inputmode="numeric" autocomplete="off" maxlength="5" placeholder="{{ __('ui.time_range.formato') }}" data-teclado="inicio">
                </label>
                <label class="ag-time-range__teclado-campo">
                    <span>{{ __('ui.time_range.fin') }}</span>
                    <input type="text" inputmode="numeric" autocomplete="off" maxlength="5" placeholder="{{ __('ui.time_range.formato') }}" data-teclado="fin">
                </label>
            </div>

            <p class="ag-time-range__aviso" role="alert" data-ag-time-range-aviso></p>
        </div>

        <div class="ag-time-range__pie">
            <button type="button" class="ag-time-range__modo" data-ag-time-range-modo aria-label="{{ __('ui.time_range.usar_teclado') }}">
                <x-atoms.icon name="keyboard" size="md" />
            </button>
            <div class="ag-time-range__acciones">
                <button type="button" class="ag-time-range__accion" data-accion="limpiar">{{ __('ui.time_range.limpiar') }}</button>
                <button type="button" class="ag-time-range__accion" data-accion="cancelar">{{ __('ui.time_range.cancelar') }}</button>
                <button type="button" class="ag-time-range__accion ag-time-range__accion--primaria" data-accion="aceptar">{{ __('ui.time_range.aceptar') }}</button>
            </div>
        </div>
    </dialog>
</div>
