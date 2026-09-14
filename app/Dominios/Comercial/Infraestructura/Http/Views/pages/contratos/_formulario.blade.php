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
      elegido, mismo patrón que rubro/subrubro en `gastos-form.js`. El mapa
      campaña→cliente viaja como `data-mapa-cliente-campania` (JSON) en el
      propio `<select>` de campaña (tarea 76: `x-atoms.select` arma un
      combobox encima del nativo y no soporta atributos por `<option>`, así
      que el mapeo no puede ir en cada opción como antes).

    `estado` y `monto_total` NUNCA son campos de este formulario: el primero
    lo cambia `panel.contratos.cambiar-estado` (otra pantalla, otra
    responsabilidad — invariante 7), el segundo se recalcula siempre en
    `Aplicacion/CrearContrato`/`ActualizarContrato` desde sus tres factores
    de origen (invariante 6) — ver docblock de `ContratosController`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    "Día completo" (HU-47, tarea 70) NO es un campo que se envíe ni se
    guarde: es un interruptor puramente de presentación
    (`resources/js/pages/contratos-form.js`) que muestra/oculta la lista de
    filas de `ventanas[]` — cero ventanas en la base YA significa "día
    completo" (ADR 0015 punto 5), así que no hace falta un booleano extra
    que pudiera contradecir a las filas cargadas. Arranca encendido cuando no
    hay ninguna fila cargada (alta nueva, o edición de un contrato sin
    ventanas) y apagado cuando sí la hay — se deriva de `$ventanasIniciales`,
    nunca de un valor propio.
--}}
@php
    $esEdicion = $contrato !== null;
    $accion = $esEdicion ? route('panel.contratos.update', $contrato) : route('panel.contratos.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $contrato?->{$campo} ?? $porDefecto);
    $clienteId = old('cliente_id', $contrato?->cliente_id ?? '');
    $campaniaId = old('campania_id', $contrato?->campania_id ?? '');
    $fechaInicio = old('fecha_inicio', $contrato?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $contrato?->fecha_fin?->toDateString() ?? '');
    $mapaClienteCampania = $campaniasDisponibles->pluck('cliente_id', 'id');
    $ventanasPorDefecto = $esEdicion
        ? $contrato->ventanas->map(fn ($ventana) => [
            'id' => $ventana->id,
            'hora_inicio' => substr((string) $ventana->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $ventana->hora_fin, 0, 5),
        ])->all()
        : [];
    $ventanasIniciales = old('ventanas', $ventanasPorDefecto);
    $hayVentanasCargadas = collect($ventanasIniciales)->contains(
        fn ($ventana) => ($ventana['hora_inicio'] ?? '') !== '' || ($ventana['hora_fin'] ?? '') !== '',
    );
    $brindaAlimentacion = (bool) $valor('brinda_alimentacion', false);
    $brindaHospedaje = (bool) $valor('brinda_hospedaje', false);
    $brindaCombustible = (bool) $valor('brinda_combustible', false);
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
            <x-atoms.button href="{{ route('panel.contratos.index') }}" variant="outline" icon="arrow_back">
                {{ __('comercial.contratos.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_datos')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 9])"
    >
        <x-atoms.select
            name="cliente_id"
            label="{{ __('comercial.contratos.campo_cliente') }}"
            placeholder="{{ __('comercial.contratos.campo_cliente_placeholder') }}"
            :options="$clientesDisponibles"
            value="{{ $clienteId }}"
            required
            error="{{ $errors->first('cliente_id') }}"
            data-ag-contrato-cliente
        />

        <x-atoms.select
            name="campania_id"
            label="{{ __('comercial.contratos.campo_campania') }}"
            placeholder="{{ __('comercial.contratos.campo_campania_placeholder') }}"
            :options="$campaniasDisponibles->pluck('codigo', 'id')"
            value="{{ $campaniaId }}"
            required
            help="{{ __('comercial.contratos.campo_campania_ayuda') }}"
            error="{{ $errors->first('campania_id') }}"
            data-ag-contrato-campania
            data-mapa-cliente-campania="{{ $mapaClienteCampania->toJson() }}"
        />

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

        <x-atoms.date
            name="fecha_inicio"
            label="{{ __('comercial.contratos.campo_fecha_inicio') }}"
            value="{{ $fechaInicio }}"
            required
            error="{{ $errors->first('fecha_inicio') }}"
        />

        <x-atoms.date
            name="fecha_fin"
            label="{{ __('comercial.contratos.campo_fecha_fin') }}"
            value="{{ $fechaFin }}"
            help="{{ __('comercial.contratos.campo_fecha_fin_ayuda') }}"
            error="{{ $errors->first('fecha_fin') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_clima')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 7])"
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

        <x-atoms.input
            type="number"
            name="altura_vuelo_m"
            label="{{ __('comercial.contratos.campo_altura_vuelo_m') }}"
            value="{{ $valor('altura_vuelo_m') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('altura_vuelo_m') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.contratos.seccion_ventanas')">
        <div class="ag-form-section__field--full">
            <x-atoms.switch
                name="dia_completo"
                label="{{ __('comercial.contratos.ventana_dia_completo') }}"
                help="{{ __('comercial.contratos.ventana_dia_completo_ayuda') }}"
                :checked="! $hayVentanasCargadas"
                data-ag-dia-completo
            />
        </div>

        <div class="ag-form-section__field--full ag-contratos-form__ventanas" data-ag-ventanas @if (! $hayVentanasCargadas) hidden @endif>
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

    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_logistica')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.switch
            name="brinda_alimentacion"
            label="{{ __('comercial.contratos.campo_brinda_alimentacion') }}"
            :checked="$brindaAlimentacion"
        />

        <x-atoms.switch
            name="brinda_hospedaje"
            label="{{ __('comercial.contratos.campo_brinda_hospedaje') }}"
            :checked="$brindaHospedaje"
        />

        <x-atoms.switch
            name="brinda_combustible"
            label="{{ __('comercial.contratos.campo_brinda_combustible') }}"
            :checked="$brindaCombustible"
        />

        <x-atoms.textarea
            class="ag-form-section__field--full"
            name="observaciones_logistica"
            label="{{ __('comercial.contratos.campo_observaciones_logistica') }}"
            placeholder="{{ __('comercial.contratos.campo_observaciones_logistica_placeholder') }}"
            value="{{ $valor('observaciones_logistica') }}"
            error="{{ $errors->first('observaciones_logistica') }}"
        />
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
