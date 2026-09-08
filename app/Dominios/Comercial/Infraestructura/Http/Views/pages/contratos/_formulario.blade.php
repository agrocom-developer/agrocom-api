{{--
    Partial: formulario de contrato, compartido por create.blade.php y
    edit.blade.php (HU-23, tarea 34) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `clientes/_formulario.blade.php` (tarea 33): las dos páginas arman el
    MISMO formulario, solo cambia contra qué URL/método postea y los valores
    iniciales.

    Espera:
    - $contrato (Contrato|null): null en alta; el modelo, con `ventanas` ya
      cargada, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      clientes activos (ver ContratosController::clientesActivos()) — la
      vista no conoce el modelo Cliente.
    - $campaniasDisponibles (Collection<int, object{id,codigo,cliente_id}>):
      TODAS las campañas activas, con su cliente — `contratos-form.js` (ADR
      0015 punto 1) filtra en cliente cuáles mostrar según el cliente
      elegido, mismo patrón que rubro/subrubro en `gastos-form.js`.

    `estado` y `monto_total` NUNCA son campos de este formulario: el primero
    lo cambia `panel.contratos.cambiar-estado` (otra pantalla, otra
    responsabilidad — invariante 7), el segundo se recalcula siempre en
    `Aplicacion/CrearContrato`/`ActualizarContrato` desde sus tres factores
    de origen (invariante 6) — ver docblock de `ContratosController`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $contrato !== null;
    $accion = $esEdicion ? route('panel.contratos.update', $contrato) : route('panel.contratos.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $contrato?->{$campo} ?? $porDefecto);
    $clienteId = old('cliente_id', $contrato?->cliente_id ?? '');
    $campaniaId = old('campania_id', $contrato?->campania_id ?? '');
    $fechaInicio = old('fecha_inicio', $contrato?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $contrato?->fecha_fin?->toDateString() ?? '');
    $ventanasPorDefecto = $esEdicion
        ? $contrato->ventanas->map(fn ($ventana) => [
            'id' => $ventana->id,
            'hora_inicio' => substr((string) $ventana->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $ventana->hora_fin, 0, 5),
        ])->all()
        : [[]];
    $ventanasIniciales = old('ventanas', $ventanasPorDefecto);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-contratos-form" novalidate data-ag-contratos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.contratos.titulo_editar') : __('comercial.contratos.titulo_crear')"
        :subtitle="__('comercial.contratos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.contratos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_datos')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 9])"
    >
        <div class="ag-input">
            <label for="cliente_id" class="ag-input__label">
                {{ __('comercial.contratos.campo_cliente') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('cliente_id') ? 'ag-input__control--error' : '' }}">
                <select name="cliente_id" id="cliente_id" class="ag-input__field" required data-ag-contrato-cliente>
                    <option value="">{{ __('comercial.contratos.campo_cliente_placeholder') }}</option>
                    @foreach ($clientesDisponibles as $id => $razonSocial)
                        <option value="{{ $id }}" @selected((string) $clienteId === (string) $id)>{{ $razonSocial }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('cliente_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('cliente_id') }}</p>
            @endif
        </div>

        <div class="ag-input">
            <label for="campania_id" class="ag-input__label">
                {{ __('comercial.contratos.campo_campania') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('campania_id') ? 'ag-input__control--error' : '' }}">
                <select name="campania_id" id="campania_id" class="ag-input__field" required data-ag-contrato-campania>
                    <option value="">{{ __('comercial.contratos.campo_campania_placeholder') }}</option>
                    @foreach ($campaniasDisponibles as $campania)
                        <option
                            value="{{ $campania->id }}"
                            data-cliente-id="{{ $campania->cliente_id }}"
                            @selected((string) $campaniaId === (string) $campania->id)
                        >{{ $campania->codigo }}</option>
                    @endforeach
                </select>
            </div>
            <p class="ag-input__help">{{ __('comercial.contratos.campo_campania_ayuda') }}</p>
            @if ($errors->has('campania_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('campania_id') }}</p>
            @endif
        </div>

        <x-atoms.input
            type="number"
            name="hectareas_contratadas"
            label="{{ __('comercial.contratos.campo_hectareas_contratadas') }}"
            value="{{ $valor('hectareas_contratadas') }}"
            min="0.01"
            step="0.01"
            required
            error="{{ $errors->first('hectareas_contratadas') }}"
        />

        <x-atoms.input
            type="number"
            name="aplicaciones_previstas"
            label="{{ __('comercial.contratos.campo_aplicaciones_previstas') }}"
            value="{{ $valor('aplicaciones_previstas') }}"
            min="1"
            step="1"
            required
            error="{{ $errors->first('aplicaciones_previstas') }}"
        />

        <x-atoms.input
            type="number"
            name="precio_ha"
            label="{{ __('comercial.contratos.campo_precio_ha') }}"
            value="{{ $valor('precio_ha') }}"
            min="0"
            step="0.01"
            required
            help="{{ __('comercial.contratos.campo_monto_total_ayuda') }}"
            error="{{ $errors->first('precio_ha') }}"
        />

        <x-atoms.input
            type="number"
            name="adelanto_monto"
            label="{{ __('comercial.contratos.campo_adelanto_monto') }}"
            value="{{ $valor('adelanto_monto') }}"
            min="0"
            step="0.01"
            error="{{ $errors->first('adelanto_monto') }}"
        />

        <x-atoms.input
            type="number"
            name="adelanto_pct"
            label="{{ __('comercial.contratos.campo_adelanto_pct') }}"
            value="{{ $valor('adelanto_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('adelanto_pct') }}"
        />

        <x-atoms.input
            type="date"
            name="fecha_inicio"
            label="{{ __('comercial.contratos.campo_fecha_inicio') }}"
            value="{{ $fechaInicio }}"
            required
            error="{{ $errors->first('fecha_inicio') }}"
        />

        <x-atoms.input
            type="date"
            name="fecha_fin"
            label="{{ __('comercial.contratos.campo_fecha_fin') }}"
            value="{{ $fechaFin }}"
            help="{{ __('comercial.contratos.campo_fecha_fin_ayuda') }}"
            error="{{ $errors->first('fecha_fin') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_clima')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 6])"
    >
        <div class="ag-form-section__field--full ag-contratos-form__ayuda">
            {{ __('comercial.contratos.seccion_clima_ayuda') }}
        </div>

        <x-atoms.input
            type="number"
            name="viento_max_kmh"
            label="{{ __('comercial.contratos.campo_viento_max_kmh') }}"
            value="{{ $valor('viento_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('viento_max_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="temperatura_max_c"
            label="{{ __('comercial.contratos.campo_temperatura_max_c') }}"
            value="{{ $valor('temperatura_max_c') }}"
            step="0.01"
            error="{{ $errors->first('temperatura_max_c') }}"
        />

        <x-atoms.input
            type="number"
            name="humedad_min_pct"
            label="{{ __('comercial.contratos.campo_humedad_min_pct') }}"
            value="{{ $valor('humedad_min_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_min_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="humedad_max_pct"
            label="{{ __('comercial.contratos.campo_humedad_max_pct') }}"
            value="{{ $valor('humedad_max_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_max_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="velocidad_max_kmh"
            label="{{ __('comercial.contratos.campo_velocidad_max_kmh') }}"
            value="{{ $valor('velocidad_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('velocidad_max_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="umbral_reporte_avance_ha"
            label="{{ __('comercial.contratos.campo_umbral_reporte_avance_ha') }}"
            value="{{ $valor('umbral_reporte_avance_ha') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('umbral_reporte_avance_ha') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.contratos.seccion_ventanas')">
        <div class="ag-form-section__field--full ag-contratos-form__ventanas" data-ag-ventanas>
            @if ($errors->has('ventanas'))
                <p class="ag-input__error" role="alert">{{ $errors->first('ventanas') }}</p>
            @endif

            <div data-ag-ventanas-lista>
                @foreach ($ventanasIniciales as $indice => $ventana)
                    @include('comercial::pages.contratos._ventana-fila', ['indice' => $indice, 'ventana' => $ventana])
                @endforeach
            </div>

            <x-atoms.button type="button" variant="outline" icon="add" data-ag-ventanas-agregar>
                {{ __('comercial.contratos.ventana_agregar') }}
            </x-atoms.button>

            {{-- Plantilla clonable (JS vanilla, resources/js/pages/contratos-form.js):
                 el índice literal se reemplaza por el próximo número al clonar. Un
                 <template> nunca se renderiza ni se envía con el form. --}}
            <template data-ag-ventana-template>
                @include('comercial::pages.contratos._ventana-fila', ['indice' => '__INDICE__', 'ventana' => []])
            </template>
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.contratos.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.contratos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
