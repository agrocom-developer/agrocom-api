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

    El selector es un POPUP anclado a la casilla (Popover API: capa superior sin
    recortes, cierra al hacer clic afuera o con Esc, sin fondo oscuro). Trae la hora
    como reloj de 12 horas con «a. m.»/«p. m.» EXPLÍCITOS junto a cada hora — un
    reloj de 1 a 12 sin período no dice si 3:00 es de la madrugada o de la tarde —:
    cabecera con «10:00 a. m. – 3:00 p. m.» y sus segmentos pulsables, dial de una
    sola vuelta (horas 1–12; minutos de 1 en 1 al arrastrar) y pie con teclado /
    Limpiar / Cancelar / Aceptar. Elegir una hora pasa sola a sus minutos, luego a
    la hora de fin y a sus minutos; y el período de la hora de fin se elige solo
    para que quede después del inicio (10:00 a. m. → 3 = 3:00 p. m.) mientras no se
    toque a mano. Un modo de texto acepta «6:30 a. m.», «6 pm» o «18:30». Lo que se
    guarda es siempre `H:i` en 24 horas (el que espera `date_format:H:i`). Sin
    Popover API el componente no se mejora y quedan los dos inputs nativos.

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
    - required (bool, default false): marca el campo como obligatorio — el asterisco
      junto al rótulo (el mismo de `atoms/input`) y el atributo `required` en los
      dos nativos. Sin rótulo no pinta asterisco.

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
    'required' => false,
])

@php
    $campoId = $id ?? 'time-range-'.substr(md5($nameStart.'|'.$nameEnd), 0, 8);
    $popupId = "{$campoId}-popup";
    $ayudaId = $help ? "{$campoId}-ayuda" : null;
    $errorId = $error ? "{$campoId}-error" : null;
    $describedBy = trim(($ayudaId ?? '').' '.($errorId ?? ''));
    $rotulo = $label ?? __('ui.time_range.elegir_horario');

    // `H:i:s` (lo que devuelve la base) se recorta a `H:i`.
    $inicio = $valueStart ? substr((string) $valueStart, 0, 5) : '';
    $fin = $valueEnd ? substr((string) $valueEnd, 0, 5) : '';

    // «6:00 a. m.»: el mismo formato que arma time-range.js, para que la casilla
    // salga bien desde el servidor. Espacios sin corte: la hora y su período no se parten.
    $en12Horas = function (string $hora): string {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        $periodo = __($h >= 12 ? 'ui.time_range.pm' : 'ui.time_range.am');

        return str_replace(' ', "\u{00A0}", sprintf('%d:%02d %s', $h12, $m, $periodo));
    };
    $mostrado = $inicio !== '' && $fin !== '' ? $en12Horas($inicio).' – '.$en12Horas($fin) : null;
@endphp

<div
    {{ $attributes->class(['ag-time-range'])->only('class') }}
    data-ag-time-range
    data-label-placeholder="{{ __('ui.time_range.placeholder') }}"
    data-label-inicio="{{ __('ui.time_range.inicio') }}"
    data-label-fin="{{ __('ui.time_range.fin') }}"
    data-label-hora="{{ __('ui.time_range.hora') }}"
    data-label-minutos="{{ __('ui.time_range.minutos') }}"
    data-label-am="{{ __('ui.time_range.am') }}"
    data-label-pm="{{ __('ui.time_range.pm') }}"
    data-label-usar-teclado="{{ __('ui.time_range.usar_teclado') }}"
    data-label-usar-reloj="{{ __('ui.time_range.usar_reloj') }}"
    data-label-error-ambas="{{ __('ui.time_range.error_ambas') }}"
    data-label-error-orden="{{ __('ui.time_range.error_orden') }}"
    data-label-error-formato="{{ __('ui.time_range.error_formato') }}"
>
    @if ($label)
        <label for="{{ $campoId }}-trigger" class="ag-time-range__label">
            {{ $label }}
            @if ($required)
                <span class="ag-input__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="ag-time-range__control {{ $error || $invalid ? 'ag-time-range__control--error' : '' }}">
        {{-- Controles reales: sin JS (o sin Popover API) son lo único que se ve. --}}
        <input
            type="time"
            name="{{ $nameStart }}"
            value="{{ $inicio }}"
            class="ag-time-range__native"
            aria-label="{{ __('ui.time_range.inicio') }}"
            @if ($disabled) disabled @endif
            @if ($required) required @endif
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
            @if ($required) required @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class') }}
        >

        {{-- Disparador y botón de limpiar: `hidden` hasta que el JS los arma. El
             disparador abre el popup con `popovertarget`: el navegador se ocupa de
             alternar abrir/cerrar, y `time-range.js` prepara el contenido en
             `beforetoggle`. --}}
        <button
            type="button"
            class="ag-time-range__trigger"
            id="{{ $campoId }}-trigger"
            data-ag-time-range-trigger
            popovertarget="{{ $popupId }}"
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="{{ $popupId }}"
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

    {{-- Popup: el dial y los números los pinta time-range.js. Cerrado, un popover
         no ocupa lugar (no rompe la grilla de la fila que lo contiene). --}}
    <div class="ag-time-range__popup" id="{{ $popupId }}" popover="auto" role="dialog" aria-label="{{ $rotulo }}" data-ag-time-range-popup>
        <div class="ag-time-range__header">
            @foreach ([['clave' => 'i', 'etiqueta' => 'inicio'], ['clave' => 'f', 'etiqueta' => 'fin']] as $par)
                @if ($par['clave'] === 'f')
                    <span class="ag-time-range__guion" aria-hidden="true">–</span>
                @endif
                <div class="ag-time-range__par">
                    <span class="ag-time-range__par-etiqueta">{{ __('ui.time_range.'.$par['etiqueta']) }}</span>
                    <div class="ag-time-range__par-fila">
                        <span class="ag-time-range__par-tiempo">
                            <button type="button" class="ag-time-range__seg" data-seg="{{ $par['clave'] }}h" aria-label="{{ __('ui.time_range.'.$par['etiqueta']) }}: {{ __('ui.time_range.hora') }}">--</button>
                            <span aria-hidden="true">:</span>
                            <button type="button" class="ag-time-range__seg" data-seg="{{ $par['clave'] }}m" aria-label="{{ __('ui.time_range.'.$par['etiqueta']) }}: {{ __('ui.time_range.minutos') }}">--</button>
                        </span>
                        {{-- a. m. / p. m. de ESTA hora: sin esto el reloj de 1 a 12 no dice
                             si 3:00 es de la madrugada o de la tarde. --}}
                        <span class="ag-time-range__periodo" role="group" aria-label="{{ __('ui.time_range.'.$par['etiqueta']) }}: {{ __('ui.time_range.periodo') }}">
                            <button type="button" class="ag-time-range__periodo-opcion" data-periodo="p{{ $par['clave'] }}" data-valor="am" aria-pressed="true">{{ __('ui.time_range.am') }}</button>
                            <button type="button" class="ag-time-range__periodo-opcion" data-periodo="p{{ $par['clave'] }}" data-valor="pm" aria-pressed="false">{{ __('ui.time_range.pm') }}</button>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ag-time-range__body">
            <div class="ag-time-range__reloj" data-ag-time-range-reloj>
                <svg class="ag-time-range__dial" viewBox="0 0 200 200" tabindex="0" role="slider" data-ag-time-range-dial></svg>
            </div>

            <div class="ag-time-range__teclado" data-ag-time-range-teclado hidden>
                <div class="ag-time-range__teclado-campos">
                    <label class="ag-time-range__teclado-campo">
                        <span>{{ __('ui.time_range.inicio') }}</span>
                        <input type="text" inputmode="text" autocomplete="off" maxlength="12" placeholder="{{ __('ui.time_range.formato') }}" data-teclado="inicio">
                    </label>
                    <label class="ag-time-range__teclado-campo">
                        <span>{{ __('ui.time_range.fin') }}</span>
                        <input type="text" inputmode="text" autocomplete="off" maxlength="12" placeholder="{{ __('ui.time_range.formato') }}" data-teclado="fin">
                    </label>
                </div>
                <p class="ag-time-range__teclado-ayuda">{{ __('ui.time_range.ayuda_teclado') }}</p>
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
    </div>
</div>
