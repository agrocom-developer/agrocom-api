{{--
    Partial: fila de contacto del formulario de cliente (HU-22, tarea 33).
    Una fila repetible dentro de la sección "Contactos" de
    `_formulario.blade.php` — la misma fila se usa para pintar los contactos
    existentes, para repetir `old('contactos')` tras un error de validación,
    y como plantilla que clona `resources/js/pages/clientes-form.js` al
    apretar "Agregar contacto".

    Espera:
    - $indice (int|string): posición dentro del array `contactos[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`, que el JS
      reemplaza por el próximo número al clonar.
    - $contacto (array{id?: int, tipo?: string, nombre?: string,
      telefono?: string, email?: string, observaciones?: string}): vacío en
      una fila nueva.
    - $tiposContacto (list<TipoContactoCliente>): heredado del scope de la
      página (Blade comparte variables con `@include`) — la vista no conoce
      el enum, se lo entrega el controlador.
--}}
<div class="ag-form-section__body ag-clientes-form__contacto" data-ag-contacto-fila>
    @if (! empty($contacto['id']))
        <input type="hidden" name="contactos[{{ $indice }}][id]" value="{{ $contacto['id'] }}">
    @endif

    @php
        $tiposOptions = collect($tiposContacto)->mapWithKeys(fn ($tipo) => [
            $tipo->value => __('comercial.clientes.contacto_tipo_opcion.'.$tipo->value)
        ]);
    @endphp
    <x-atoms.select
        name="contactos[{{ $indice }}][tipo]"
        id="contactos-{{ $indice }}-tipo"
        label="{{ __('comercial.clientes.contacto_tipo') }}"
        :options="$tiposOptions"
        :value="$contacto['tipo'] ?? null"
        placeholder="{{ __('comercial.clientes.contacto_tipo_placeholder') }}"
        required
    />

    <x-atoms.input
        type="text"
        name="contactos[{{ $indice }}][nombre]"
        label="{{ __('comercial.clientes.contacto_nombre') }}"
        value="{{ $contacto['nombre'] ?? '' }}"
        required
    />

    <x-atoms.input
        type="tel"
        name="contactos[{{ $indice }}][telefono]"
        label="{{ __('comercial.clientes.contacto_telefono') }}"
        value="{{ $contacto['telefono'] ?? '' }}"
    />

    <x-atoms.input
        type="email"
        name="contactos[{{ $indice }}][email]"
        label="{{ __('comercial.clientes.contacto_email') }}"
        value="{{ $contacto['email'] ?? '' }}"
    />

    <div class="ag-form-section__field--full">
        <x-atoms.input
            type="text"
            name="contactos[{{ $indice }}][observaciones]"
            label="{{ __('comercial.clientes.contacto_observaciones') }}"
            value="{{ $contacto['observaciones'] ?? '' }}"
        />
    </div>

    <div class="ag-form-section__field--full ag-clientes-form__contacto-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-contacto-quitar>
            {{ __('comercial.clientes.contacto_quitar') }}
        </x-atoms.button>
    </div>
</div>
