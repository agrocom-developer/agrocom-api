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
    - $contacto (array{id?: int, tipo?: string, tipo_otro?: string|null,
      nombre?: string, telefono?: string, email?: string,
      observaciones?: string}): vacío en una fila nueva.
    - $tiposContacto (list<TipoContactoCliente>): heredado del scope de la
      página (Blade comparte variables con `@include`) — la vista no conoce
      el enum, se lo entrega el controlador. `Otro` es siempre la última
      opción (orden de declaración del enum).

    `tipo_otro` (tarea "resumen de cliente"): campo libre que solo aplica
    cuando `tipo = otro` — oculto por defecto (SSR, sin parpadeo) y
    sincronizado por delegación de eventos en
    resources/js/pages/clientes-form.js (la fila puede clonarse dinámico, no
    alcanza con un listener fijado una sola vez al cargar la página).
    `data-ag-contacto-tipo` en el `<select>` es el gancho que ese script usa
    para encontrar el tipo de CADA fila sin depender del `name` indexado.
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
        :label="__('comercial.clientes.contacto_tipo')"
        :options="$tiposOptions"
        :value="$contacto['tipo'] ?? null"
        :placeholder="__('comercial.clientes.contacto_tipo_placeholder')"
        required
        :error="$errors->first(\"contactos.{$indice}.tipo\")"
        data-ag-contacto-tipo
    />

    {{-- col-6 a propósito (sin `--field--full`, pedido directo): se coloca al
         lado del <select> de "Tipo" en la misma fila, no debajo a ancho
         completo. --}}
    <div
        data-ag-contacto-tipo-otro
        @if (($contacto['tipo'] ?? null) !== 'otro') hidden @endif
    >
        <x-atoms.input
            type="text"
            name="contactos[{{ $indice }}][tipo_otro]"
            :label="__('comercial.clientes.contacto_tipo_otro')"
            :value="$contacto['tipo_otro'] ?? ''"
            :help="__('comercial.clientes.contacto_tipo_otro_ayuda')"
            :error="$errors->first(\"contactos.{$indice}.tipo_otro\")"
        />
    </div>

    <x-atoms.input
        type="text"
        name="contactos[{{ $indice }}][nombre]"
        :label="__('comercial.clientes.contacto_nombre')"
        :value="$contacto['nombre'] ?? ''"
        required
        :error="$errors->first(\"contactos.{$indice}.nombre\")"
    />

    <x-atoms.input
        type="tel"
        name="contactos[{{ $indice }}][telefono]"
        :label="__('comercial.clientes.contacto_telefono')"
        :value="$contacto['telefono'] ?? ''"
        :error="$errors->first(\"contactos.{$indice}.telefono\")"
    />

    <x-atoms.input
        type="email"
        name="contactos[{{ $indice }}][email]"
        :label="__('comercial.clientes.contacto_email')"
        :value="$contacto['email'] ?? ''"
        :error="$errors->first(\"contactos.{$indice}.email\")"
    />

    <div class="ag-form-section__field--full">
        <x-atoms.input
            type="text"
            name="contactos[{{ $indice }}][observaciones]"
            :label="__('comercial.clientes.contacto_observaciones')"
            :value="$contacto['observaciones'] ?? ''"
            :error="$errors->first(\"contactos.{$indice}.observaciones\")"
        />
    </div>

    <div class="ag-form-section__field--full ag-clientes-form__contacto-pie">
        <x-atoms.button type="button" variant="danger-outline" size="sm" icon="delete" data-ag-contacto-quitar>
            {{ __('comercial.clientes.contacto_quitar') }}
        </x-atoms.button>
    </div>
</div>
