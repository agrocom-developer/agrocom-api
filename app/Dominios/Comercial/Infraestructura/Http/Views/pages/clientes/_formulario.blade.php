{{--
    Partial: formulario de cliente, compartido por create.blade.php y
    edit.blade.php (HU-22, tarea 33; actualizado ADR 0018) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Las dos pantallas
    arman el MISMO formulario; lo único que cambia es contra qué URL/método
    postea y los valores iniciales — evita que las dos plantillas diverjan con
    el tiempo, que es justo el riesgo de un molde que van a copiar HU-23 a
    HU-27.

    Espera:
    - $cliente (Cliente|null): null en alta; el modelo, con `contactos` ya
      cargada, en edición.
    - $tiposContacto (list<TipoContactoCliente>): opciones del select de
      tipo de contacto (ver ClientesController) — la vista no conoce el
      enum de dominio.
    - $tiposPersona (list<TipoPersonaCliente>): opciones del select de tipo
      de persona (física/jurídica, ADR 0018) — la vista no conoce el enum
      de dominio.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición, para que el usuario no pierda lo
    que ya había cargado.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito: un cliente no tiene métricas de solo lectura que valga la
    pena mostrar acá (ver runs/33.md).
--}}
@php
    $esEdicion = $cliente !== null;
    $accion = $esEdicion ? route('panel.clientes.update', $cliente) : route('panel.clientes.store');
    $razonSocial = old('razon_social', $cliente?->razon_social ?? '');
    $nit = old('nit', $cliente?->nit ?? '');
    $tipoPersonaValor = old('tipo_persona', $cliente?->tipo_persona?->value ?? '');
    $contactosPorDefecto = $esEdicion
        ? $cliente->contactos->map(fn ($contacto) => [
            'id' => $contacto->id,
            'tipo' => $contacto->tipo->value,
            'nombre' => $contacto->nombre,
            'telefono' => $contacto->telefono,
            'email' => $contacto->email,
            'observaciones' => $contacto->observaciones,
        ])->all()
        : [[]];
    $contactosIniciales = old('contactos', $contactosPorDefecto);
    $tiposPersonaOptions = collect($tiposPersona)->mapWithKeys(fn ($tipo) => [
        $tipo->value => __('comercial.clientes.tipo_persona_opcion.'.$tipo->value)
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-clientes-form" novalidate data-ag-clientes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.clientes.titulo_editar') : __('comercial.clientes.titulo_crear')"
        :subtitle="__('comercial.clientes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.clientes.index') }}" variant="outline" icon="arrow_back">
                {{ __('comercial.clientes.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.clientes.seccion_datos')"
        :count="__('comercial.clientes.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="text"
            name="razon_social"
            label="{{ __('comercial.clientes.campo_razon_social') }}"
            value="{{ $razonSocial }}"
            required
            error="{{ $errors->first('razon_social') }}"
        />

        <x-atoms.select
            name="tipo_persona"
            id="tipo_persona"
            label="{{ __('comercial.clientes.campo_tipo_persona') }}"
            :options="$tiposPersonaOptions"
            :value="$tipoPersonaValor"
            placeholder="{{ __('comercial.clientes.campo_tipo_persona_placeholder') }}"
            required
            error="{{ $errors->first('tipo_persona') }}"
        />

        <x-atoms.input
            type="text"
            name="nit"
            label="{{ __('comercial.clientes.campo_nit') }}"
            value="{{ $nit }}"
            help="{{ __('comercial.clientes.campo_nit_ayuda') }}"
            error="{{ $errors->first('nit') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.clientes.seccion_contactos')">
        <div class="ag-form-section__field--full ag-clientes-form__contactos" data-ag-contactos>
            @if ($errors->has('contactos'))
                <p class="ag-input__error" role="alert">{{ $errors->first('contactos') }}</p>
            @endif

            <div data-ag-contactos-lista>
                @foreach ($contactosIniciales as $indice => $contacto)
                    @include('comercial::pages.clientes._contacto-fila', ['indice' => $indice, 'contacto' => $contacto])
                @endforeach
            </div>

            <x-atoms.button type="button" variant="outline" icon="add" data-ag-contactos-agregar>
                {{ __('comercial.clientes.contacto_agregar') }}
            </x-atoms.button>

            {{-- Plantilla clonable (JS vanilla, resources/js/pages/clientes-form.js):
                 el índice literal se reemplaza por el próximo número al clonar. Un
                 <template> nunca se renderiza ni se envía con el form. --}}
            <template data-ag-contacto-template>
                @include('comercial::pages.clientes._contacto-fila', ['indice' => '__INDICE__', 'contacto' => []])
            </template>
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.clientes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.clientes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
