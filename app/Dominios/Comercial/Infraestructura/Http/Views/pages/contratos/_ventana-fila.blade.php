{{--
    Partial: fila de ventana horaria del formulario de contrato (HU-23, tarea
    34) — mismo patrón que `clientes/_contacto-fila.blade.php` (tarea 33):
    una fila repetible dentro de la sección "Ventanas de aplicación" de
    `_formulario.blade.php`, reusada para pintar las ventanas existentes,
    para repetir `old('ventanas')` tras un error de validación, y como
    plantilla que clona `resources/js/pages/contratos-form.js` al apretar
    "Agregar ventana".

    Espera:
    - $indice (int|string): posición dentro del array `ventanas[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`, que el JS
      reemplaza por el próximo número al clonar.
    - $ventana (array{id?: int, hora_inicio?: string, hora_fin?: string}):
      vacío en una fila nueva.

    Ninguna de las dos horas lleva `required` (HU-47, tarea 70): una fila
    solo existe si el usuario la cargó a propósito (con "Día completo"
    apagado) — el Request valida `required_with` mutuo entre las dos, así que
    completar una sin la otra sigue siendo un error, solo que no bloquea el
    envío del formulario con el interruptor encendido y ninguna fila visible.
--}}
<div class="ag-form-section__body ag-contratos-form__ventana" data-ag-ventana-fila>
    @if (! empty($ventana['id']))
        <input type="hidden" name="ventanas[{{ $indice }}][id]" value="{{ $ventana['id'] }}">
    @endif

    <x-atoms.input
        type="time"
        name="ventanas[{{ $indice }}][hora_inicio]"
        label="{{ __('comercial.contratos.ventana_hora_inicio') }}"
        value="{{ $ventana['hora_inicio'] ?? '' }}"
    />

    <x-atoms.input
        type="time"
        name="ventanas[{{ $indice }}][hora_fin]"
        label="{{ __('comercial.contratos.ventana_hora_fin') }}"
        value="{{ $ventana['hora_fin'] ?? '' }}"
    />

    <div class="ag-contratos-form__ventana-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-ventana-quitar>
            {{ __('comercial.contratos.ventana_quitar') }}
        </x-atoms.button>
    </div>
</div>
