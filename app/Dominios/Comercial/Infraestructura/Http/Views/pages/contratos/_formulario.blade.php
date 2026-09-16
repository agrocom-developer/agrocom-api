{{--
    Partial: formulario de contrato, compartido por create.blade.php y
    edit.blade.php (HU-23, tarea 34; ampliado tarea "contratos-lotes") —
    arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo
    patrón que `clientes/_formulario.blade.php` (tarea 33): las dos páginas
    arman el MISMO formulario, solo cambia contra qué URL/método postea y los
    valores iniciales.

    Espera:
    - $contrato (Contrato|null): null en alta; el modelo, con `lotes.lote`
      ya cargado, en edición (sin `ventanas`: la relación ya no existe,
      retirada el 16/9/2026 junto con `com_contrato_ventanas`).
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      clientes activos (ver ContratosController::clientesActivos()) — la
      vista no conoce el modelo Cliente.
    - $campaniasDisponibles (Collection<int, string>): id => código, TODAS
      las campañas del catálogo (ADR 0015, corregido el 15/9/2026: la
      campaña es compartida, no hay que filtrarla por cliente) — ver
      ContratosController::campaniasDisponibles().
    - $clienteIdPreseleccionado (int|null, tarea "resumen de cliente"): solo
      en alta, desde `?cliente_id=` (ver ContratosController::create()) — el
      atajo "Nuevo contrato" del aside de `panel.clientes.edit` llega acá con
      el cliente ya elegido. `edit()` no lo pasa (`null` por el `??` de abajo).
    - $propiedadesYLotesPorCliente (array): estructura anidada de cliente →
      propiedad → lotes, para select dependiente del formulario (tarea
      "contratos-lotes", estrategia 'a': datos embebidos en HTML).

    `estado` y `monto_total` NUNCA son campos de este formulario: el primero
    lo cambia `panel.contratos.cambiar-estado` (otra pantalla, otra
    responsabilidad — invariante 7), el segundo se recalcula siempre en
    `Aplicacion/CrearContrato`/`ActualizarContrato` desde sus tres factores
    de origen (invariante 6) — ver docblock de `ContratosController`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    "Día completo" por lote (tarea "contratos-lotes", reemplaza al HU-47 de
    contrato completo del 16/9/2026 — ver más abajo): cada lote agregado
    arranca sin horario propio (`hora_inicio`/`hora_fin` vacíos = día
    completo, mismo criterio que ya regía a nivel de todo el contrato) y
    ofrece un link "Personalizar horario" (`resources/js/pages/contratos-form.js`)
    que despliega los dos campos de hora SOLO para esa fila.

    Orden de secciones (tarea "contratos-lotes", pedido del dueño): Datos →
    Logística → Propiedad/Lotes. Ya no hay una sección de "Orden de
    aplicación"/"Ventanas de aplicación" a nivel de contrato completo — ese
    horario se retiró junto con `com_contrato_ventanas` (16/9/2026): vive
    por lote, dentro de esta misma sección. Propiedad/Lotes es un multi-select
    de propiedades del cliente con pills (podés elegir varias a la vez).

    Selección de lotes vía modal (rediseño sept/2026, reemplaza al panel
    lateral con checkboxes siempre visibles): elegir una propiedad del select
    — o clickear una pill ya agregada — abre el modal único
    (`#ag-modal-lotes-propiedad`, partial propio: `_modal-lotes.blade.php`)
    con TODOS los lotes de esa propiedad, tildados los que ya están en el
    contrato. Guarda la selección en la tabla apilada de abajo (partial
    propio: `_lotes-tabla.blade.php`) recién al apretar "Guardar selección"
    del modal — cerrarlo por la X, Cancelar o el fondo descarta los cambios.
    El ícono de cerrar de la pill saca la propiedad ENTERA de la tabla;
    clickear el cuerpo de la pill reabre el modal para seguir editando esa
    propiedad. Lógica en `abrirModalLotes`/`guardarSeleccionModal` de
    `resources/js/pages/contratos-form.js`, sin cambios por esta separación
    en archivos — los `data-ag-*` que ese script busca son los mismos.
--}}
@php
    $esEdicion = $contrato !== null;
    $accion = $esEdicion ? route('panel.contratos.update', $contrato) : route('panel.contratos.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $contrato?->{$campo} ?? $porDefecto);
    // $clienteIdPreseleccionado (tarea "resumen de cliente"): solo llega en
    // alta, desde el atajo del aside de `panel.clientes.edit` — `?? null`
    // porque `edit()` no lo pasa (no aplica editando un contrato existente).
    $clienteId = old('cliente_id', $contrato?->cliente_id ?? $clienteIdPreseleccionado ?? '');
    $campaniaId = old('campania_id', $contrato?->campania_id ?? '');
    $fechaInicio = old('fecha_inicio', $contrato?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $contrato?->fecha_fin?->toDateString() ?? '');
    $brindaAlimentacion = (bool) $valor('brinda_alimentacion', false);
    $brindaHospedaje = (bool) $valor('brinda_hospedaje', false);
    $brindaCombustible = (bool) $valor('brinda_combustible', false);

    // Lotes iniciales en edición (tarea "contratos-lotes", ampliado con
    // horario por lote el 16/9/2026): mapear el resultado de $contrato->lotes
    // (que son filas ContratoLote con ->lote cargado) a un array por
    // propiedad, para mostrar el agrupamiento visual. Cada lote trae su
    // hora_inicio/hora_fin (nullable, string `H:i` o NULL para "día completo").
    $lotesPorDefecto = [];
    if ($esEdicion && $contrato->lotes->isNotEmpty()) {
        foreach ($contrato->lotes as $contratoLote) {
            $lote = $contratoLote->lote;
            $propiedadId = $lote->propiedad_id;
            if (!isset($lotesPorDefecto[$propiedadId])) {
                $lotesPorDefecto[$propiedadId] = [
                    'propiedad_nombre' => $lote->propiedad->nombre,
                    'lotes' => [],
                ];
            }
            $lotesPorDefecto[$propiedadId]['lotes'][] = [
                'lote_id' => $lote->id,
                'codigo' => $lote->codigo,
                'hectareas' => (string) $lote->hectareas,
                'hora_inicio' => $contratoLote->hora_inicio,
                'hora_fin' => $contratoLote->hora_fin,
            ];
        }
    }
    $lotesIniciales = old('lotes_data', $lotesPorDefecto);
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

    <div class="ag-contratos-form__layout">
        <div class="ag-contratos-form__main">
    {{-- Sección 1: Datos del contrato (8 campos, sin tocar) --}}
    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_datos')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 8])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('comercial.contratos.campo_cliente') }}"
            placeholder="{{ __('comercial.contratos.campo_cliente_placeholder') }}"
            :options="$clientesDisponibles"
            value="{{ $clienteId }}"
            required
            error="{{ $errors->first('cliente_id') }}"
            action-icon="add"
            action-href="{{ route('panel.clientes.create', ['volver_a' => route('panel.contratos.create')]) }}"
            action-label="{{ __('comercial.contratos.crear_cliente') }}"
            action-text="{{ __('comercial.contratos.crear_cliente_corto') }}"
        />


        <x-atoms.select
            name="campania_id"
            label="{{ __('comercial.contratos.campo_campania') }}"
            placeholder="{{ __('comercial.contratos.campo_campania_placeholder') }}"
            :options="$campaniasDisponibles"
            value="{{ $campaniaId }}"
            required
            help="{{ __('comercial.contratos.campo_campania_ayuda') }}"
            error="{{ $errors->first('campania_id') }}"
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

    {{-- Sección 2: Logística (reordenada, antes iba al final) --}}
    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_logistica')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 4])"
    >
        <div class="ag-form-section__field--full ag-contratos-form__logistica-switches">
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
        </div>

        <x-atoms.textarea
            class="ag-form-section__field--full"
            name="observaciones_logistica"
            label="{{ __('comercial.contratos.campo_observaciones_logistica') }}"
            placeholder="{{ __('comercial.contratos.campo_observaciones_logistica_placeholder') }}"
            value="{{ $valor('observaciones_logistica') }}"
            error="{{ $errors->first('observaciones_logistica') }}"
        />
    </x-molecules.form-section>

    {{-- Sección 3: Propiedad y lotes (nueva, tarea "contratos-lotes") --}}
    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_lotes')"
        :count="__('comercial.contratos.lotes_contador', ['cantidad' => collect($lotesIniciales)->sum(fn ($g) => count($g['lotes'] ?? []))])"
    >
        {{-- Datos JSON embebidos: propiedades y lotes por cliente. El JS los
             lee para armar el multi-select de propiedades sin AJAX. --}}
        <script type="application/json" data-ag-propiedades-lotes>
            {!! json_encode($propiedadesYLotesPorCliente) !!}
        </script>

        {{-- Multi-select de Propiedades (dependiente de Cliente, deshabilitado si no
             hay cliente elegido). Elegir una opción agrega su pill Y abre de una
             el modal de lotes de esa propiedad (ver contratos-form.js,
             `abrirModalLotes`) — no hace falta un paso aparte para ver los lotes. --}}
        <x-atoms.select
            name="propiedades_temp"
            id="propiedades_multi"
            label="{{ __('comercial.contratos.campo_propiedad') }}"
            placeholder="{{ __('comercial.contratos.campo_propiedad_placeholder') }}"
            help="{{ __('comercial.contratos.campo_propiedad_ayuda') }}"
            :options="[]"
            data-ag-propiedades-select
            disabled
            action-icon="add"
            action-href="{{ route('panel.propiedades.create') }}"
            action-label="{{ __('comercial.contratos.crear_propiedad') }}"
            action-text="{{ __('comercial.contratos.crear_propiedad_corto') }}"
            action-hidden
        />

        {{-- Pills de propiedades ya elegidas — clickear el cuerpo reabre el
             modal de sus lotes; el ícono de cerrar saca la propiedad ENTERA
             del contrato (y con ella, todos sus lotes de la lista apilada).
             El "label" fantasma de arriba (mismo `ag-select__label`, oculto)
             empuja las pills la misma altura que el label real del select
             empuja su control — así quedan alineadas con el INPUT, no con
             el label, sin numeritos mágicos de margen. --}}
        <div class="ag-contratos-form__propiedades-pills-wrap">
            <span class="ag-select__label" aria-hidden="true">&nbsp;</span>
            <div
                class="ag-contratos-form__propiedades-pills"
                data-ag-propiedades-pills
                data-texto-quitar="{{ __('comercial.contratos.lotes_quitar') }}"
            >
            </div>
        </div>

        {{-- Lista apilada de lotes ya agregados (agrupada por propiedad) —
             partial propio, ver `_lotes-tabla.blade.php`. --}}
        @include('comercial::pages.contratos._lotes-tabla', ['lotesIniciales' => $lotesIniciales])
    </x-molecules.form-section>

    {{-- Modal único de selección de lotes por propiedad — partial propio,
         ver `_modal-lotes.blade.php`. --}}
    @include('comercial::pages.contratos._modal-lotes')

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
        </div>

        @if ($esEdicion && $resumenContrato !== null)
            {{-- Resumen (tarea "resumen de contrato"): sin datos de
                 aplicación todavía, empty-state con atajo a crear una orden
                 (mismo patrón que el aside de clientes); con datos, dos
                 tarjetas de solo lectura (facturación / aplicación) armadas
                 100% server-side en ContratosController::resumenContrato(). --}}
            <aside class="ag-contratos-form__aside">
                @if ($resumenContrato['tieneDatos'])
                    @foreach ($resumenContrato['tarjetas'] as $tarjeta)
                        <x-molecules.summary-card :title="$tarjeta['titulo']" :items="$tarjeta['items']" />
                    @endforeach
                @else
                    <x-molecules.empty-state
                        :icon="$resumenContrato['icono']"
                        :title="$resumenContrato['titulo']"
                        :detail="$resumenContrato['detalle']"
                    >
                        @if ($resumenContrato['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button href="{{ $resumenContrato['accion']['href'] }}" variant="outline" icon="add">
                                    {{ $resumenContrato['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.empty-state>
                @endif
            </aside>
        @endif
    </div>
</form>
