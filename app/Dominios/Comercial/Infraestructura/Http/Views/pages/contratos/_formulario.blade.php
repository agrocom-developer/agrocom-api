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
    - $campaniasDisponibles (Collection<int, string>): id => código, solo
      las campañas ABIERTAS (pedido directo del 19/9/2026), más — en
      edición — la campaña del propio contrato aunque ya no esté abierta,
      para que el select no la pierda. La campaña es compartida (ADR 0015,
      corregido el 15/9/2026): no se filtra por cliente — ver
      ContratosController::campaniasParaFormulario().
    - $clienteIdPreseleccionado (int|null, tarea "resumen de cliente"): solo
      en alta, desde `?cliente_id=` (ver ContratosController::create()) — el
      atajo "Nuevo contrato" del aside de `panel.clientes.edit` llega acá con
      el cliente ya elegido. `edit()` no lo pasa (`null` por el `??` de abajo).
    - $propiedadesYLotesPorCliente (array): estructura anidada de cliente →
      propiedad → lotes, para select dependiente del formulario (tarea
      "contratos-lotes", estrategia 'a': datos embebidos en HTML). Cada lote
      trae `ocupado_en_campanias` (tarea "contrato-lotes-conflicto",
      18/9/2026): campañas donde ya está comprometido por OTRO contrato
      vigente — el modal de selección lo usa para no ofrecerlo si coincide
      con la campaña elegida en este formulario.
    - $loteIdsConOrdenRegistrada (list<int>): lotes de ESTE contrato que ya
      tienen una orden de aplicación registrada — el botón "Quitar" de
      `_lotes-tabla.blade.php` se deshabilita para ellos (sin vista "show" de
      contrato, es la única forma de proteger un lote con historial real).
    - $conflictosPorLote (array<int, array>): lotes de ESTE contrato que
      TAMBIÉN están en otro contrato vigente de la misma campaña — datos del
      otro contrato para el modal informativo de conflicto
      (`_modal-conflicto-lote.blade.php`), armados por
      `ContratosController::formatearConflictos()`. Si el propio contrato está
      «En conflicto», además alimentan el aviso de arriba de la ficha.
    - $campaniaIdPredeterminada (int|null): la primera campaña activa
      (`abierta`), que el select de campaña ofrece ya elegida cuando el
      contrato todavía no trae una (alta) — ver
      `ContratosController::campaniaActivaPredeterminada()`.
    - $pasosEstado (list<array>|null), $ayudaEstado (string|null): solo en
      edición, los pasos de `molecules/step-arrow` y el párrafo que los
      acompaña (`PasosDeContrato`). Los modales que abren viven en
      `_cambio-estado.blade.php`, fuera de este `<form>`.

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
    // $urlActual/$tituloPagina (memento de navegación, 17/9/2026): la URL de
    // ESTA pantalla (alta o edición) y su título, para que los 3 accesos
    // directos de abajo (crear cliente/propiedad/lote) le digan a
    // `RecordarOrigenNavegacion` adónde volver — antes `?volver_a=` estaba
    // fijo a `route('panel.contratos.create')` acá abajo, así que un acceso
    // directo abierto DESDE la edición de un contrato ya existente volvía
    // igual al formulario de ALTA (bug real, corregido acá).
    $tituloPagina = $esEdicion ? __('comercial.contratos.titulo_editar') : __('comercial.contratos.titulo_crear');
    $urlActual = $esEdicion ? route('panel.contratos.edit', $contrato) : route('panel.contratos.create');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $contrato?->{$campo} ?? $porDefecto);
    // $clienteIdPreseleccionado (tarea "resumen de cliente"): solo llega en
    // alta, desde el atajo del aside de `panel.clientes.edit` — `?? null`
    // porque `edit()` no lo pasa (no aplica editando un contrato existente).
    $clienteId = old('cliente_id', $contrato?->cliente_id ?? $clienteIdPreseleccionado ?? '');
    // La campaña del contrato manda; sin una (alta), la primera campaña activa.
    // `old()` gana sobre las dos: tras un guardado fallido se conserva lo elegido.
    $campaniaId = old('campania_id', $contrato?->campania_id ?? $campaniaIdPredeterminada ?? '');
    $fechaInicio = old('fecha_inicio', $contrato?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $contrato?->fecha_fin?->toDateString() ?? '');
    $brindaAlimentacion = (bool) $valor('brinda_alimentacion', false);
    $brindaHospedaje = (bool) $valor('brinda_hospedaje', false);
    $brindaCombustible = (bool) $valor('brinda_combustible', false);

    // Lotes iniciales: dos orígenes posibles, cada uno con su propio índice
    // por fila (`indice`, usado por `_lotes-tabla.blade.php` para el
    // `name="lotes[N][lote_id]"`).
    //
    // 1) Redisplay tras una validación fallida (create O edit): reconstruye
    //    el agrupado por propiedad desde `old('lotes')` — el array CRUDO que
    //    manda el formulario (`lotes[N][lote_id]`), NO
    //    desde `old('lotes_data')` (bug real, 16/9/2026 → corregido acá): esa
    //    clave nunca la llena ningún campo del formulario, así que la tabla
    //    de lotes quedaba SIEMPRE vacía tras cualquier error de validación.
    //    Cruza cada `lote_id` recibido contra
    //    `$propiedadesYLotesPorCliente` (ya cargado para el cliente elegido)
    //    para recuperar código/hectáreas/propiedad — el POST no los manda.
    //    Conserva el índice ORIGINAL (la clave de `old('lotes')`).
    //
    // 2) Sin fallo de validación: en edición, el estado guardado
    //    (`$contrato->lotes`, con `->lote` ya cargado); en alta, vacío.
    $lotesPorDefecto = [];
    $lotesEnviados = old('lotes');

    if ($lotesEnviados !== null) {
        $propiedadesDelCliente = $propiedadesYLotesPorCliente[(int) $clienteId] ?? [];

        foreach ($lotesEnviados as $indice => $loteEnviado) {
            $loteId = (int) ($loteEnviado['lote_id'] ?? 0);

            foreach ($propiedadesDelCliente as $propiedadId => $propiedad) {
                $loteData = collect($propiedad['lotes'])->firstWhere('id', $loteId);

                if ($loteData === null) {
                    continue;
                }

                if (!isset($lotesPorDefecto[$propiedadId])) {
                    $lotesPorDefecto[$propiedadId] = [
                        'propiedad_nombre' => $propiedad['nombre'],
                        'lotes' => [],
                    ];
                }

                $lotesPorDefecto[$propiedadId]['lotes'][] = [
                    'indice' => $indice,
                    'lote_id' => $loteId,
                    'codigo' => $loteData['codigo'],
                    'hectareas' => $loteData['hectareas'],
                ];
                break;
            }
        }
    } elseif ($esEdicion && $contrato->lotes->isNotEmpty()) {
        foreach ($contrato->lotes as $indice => $contratoLote) {
            $lote = $contratoLote->lote;
            $propiedadId = $lote->propiedad_id;
            if (!isset($lotesPorDefecto[$propiedadId])) {
                $lotesPorDefecto[$propiedadId] = [
                    'propiedad_nombre' => $lote->propiedad->nombre,
                    'lotes' => [],
                ];
            }
            $lotesPorDefecto[$propiedadId]['lotes'][] = [
                'indice' => $indice,
                'lote_id' => $lote->id,
                'codigo' => $lote->codigo,
                'hectareas' => (string) $lote->hectareas,
            ];
        }
    }

    // Dentro de cada propiedad, orden natural por código (L1, L2, … L10), el
    // mismo del listado de lotes. El `indice` de cada fila no cambia al ordenar.
    foreach ($lotesPorDefecto as $propiedadId => $grupo) {
        usort($grupo['lotes'], fn (array $a, array $b): int => \App\Dominios\Comercial\Dominio\OrdenCodigoLote::comparar($a['codigo'], $b['codigo']));
        $lotesPorDefecto[$propiedadId]['lotes'] = $grupo['lotes'];
    }

    $lotesIniciales = $lotesPorDefecto;
@endphp

<form
    method="POST"
    action="{{ $accion }}"
    class="ag-contratos-form"
    novalidate
    data-ag-contratos-form
    data-ag-borrador="propio"
    data-url-origen="{{ $urlActual }}"
    data-etiqueta-origen="{{ $tituloPagina }}"
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$tituloPagina"
        :subtitle="__('comercial.contratos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.contratos.index')" :label="__('comercial.contratos.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    @if ($errors->has('error'))
        {{-- Falla no prevista del servidor (ver catch-all de
             ContratosController::store()/update()) — nunca un campo
             específico, por eso no vive en la sección de datos como los
             demás `$errors->first()` de este formulario. --}}
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('error') }}
        </x-molecules.alert-strip>
    @endif

    @if ($esEdicion)
        @if ($errors->has('estado'))
            <x-molecules.alert-strip variant="danger" icon="error">
                {{ $errors->first('estado') }}
            </x-molecules.alert-strip>
        @endif

        <x-molecules.step-arrow
            :steps="$pasosEstado"
            :label="__('comercial.contratos.estado_pasos_aria')"
            :help="$ayudaEstado"
        />

        @if ($contrato->estado->value === 'conflicto')
            {{-- Contrato «En conflicto» (ADR 0021): comparte lotes con otro que ya
                 está en ejecución. Se remarca arriba —no solo en la fila de cada
                 lote— para que se decida ahora: cancelarlo, o quitarle esos lotes
                 y que vuelva solo a aprobación. Un lote choca con un solo
                 contrato, así que el otro contrato se repite por cada lote
                 compartido y acá se junta por contrato. --}}
            @php
                $contratosEnConflicto = [];
                foreach ($conflictosPorLote ?? [] as $conflicto) {
                    $contratosEnConflicto[$conflicto['contrato_id']] ??= $conflicto;
                }
                // El modal de cancelar lo trae `_cambio-estado.blade.php`, con el
                // mismo id que el paso «Cancelado» de la fila de arriba.
                $modalIdCancelar = \App\Dominios\Comercial\Infraestructura\Http\PasosDeContrato::PREFIJO_MODAL.'-cancelado';
            @endphp

            <x-molecules.alert-strip variant="warning" icon="warning" class="ag-contratos-estado__conflicto">
                <p class="ag-contratos-estado__conflicto-titulo">{{ __('comercial.contratos.conflicto_aviso_titulo') }}</p>
                <p class="ag-contratos-estado__nota">{{ __('comercial.contratos.conflicto_aviso_detalle') }}</p>

                @if ($contratosEnConflicto !== [])
                    <ul class="ag-contratos-estado__lista">
                        @foreach ($contratosEnConflicto as $otro)
                            <li class="ag-contratos-estado__item">
                                <span>{{ __('comercial.contratos.conflicto_aviso_con', [
                                    'cliente' => $otro['cliente'],
                                    'estado' => $otro['estado_label'],
                                    'lotes' => collect($otro['lotes_en_conflicto'])->pluck('codigo')->implode(', '),
                                ]) }}</span>
                                <a class="ag-contratos-estado__enlace" href="{{ $otro['editar_url'] }}">{{ __('comercial.contratos.conflicto_aviso_ver') }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-slot:action>
                    <x-atoms.button :href="'#contrato-lotes'" variant="outline" size="sm" icon="list">
                        {{ __('comercial.contratos.conflicto_aviso_revisar') }}
                    </x-atoms.button>
                    @puede('comercial.contrato.cambiar_estado')
                        <x-atoms.button type="button" variant="danger-outline" size="sm" icon="cancel" data-bs-toggle="modal" data-bs-target="#{{ $modalIdCancelar }}">
                            {{ __('comercial.contratos.conflicto_aviso_cancelar') }}
                        </x-atoms.button>
                    @endpuede
                </x-slot:action>
            </x-molecules.alert-strip>
        @endif
    @endif

    <x-molecules.form-layout>
    {{-- Sección 1: Datos del contrato (8 campos, sin tocar) --}}
    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_datos')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 8])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            :label="__('comercial.contratos.campo_cliente')"
            :placeholder="__('comercial.contratos.campo_cliente_placeholder')"
            :options="$clientesDisponibles"
            :value="$clienteId"
            required
            :error="$errors->first('cliente_id')"
            action-icon="add"
            :action-href="route('panel.clientes.create', ['volver_a' => $urlActual, 'volver_texto' => $tituloPagina])"
            :action-label="__('comercial.contratos.crear_cliente')"
            :action-text="__('comercial.contratos.crear_cliente_corto')"
        />


        <x-atoms.select
            name="campania_id"
            :label="__('comercial.contratos.campo_campania')"
            :placeholder="__('comercial.contratos.campo_campania_placeholder')"
            :options="$campaniasDisponibles"
            :value="$campaniaId"
            required
            :help="__('comercial.contratos.campo_campania_ayuda')"
            :error="$errors->first('campania_id')"
        />

        <x-atoms.input
            type="number"
            name="hectareas_contratadas"
            :label="__('comercial.contratos.campo_hectareas_contratadas')"
            :value="$valor('hectareas_contratadas')"
            min="0.01"
            step="0.01"
            required
            :error="$errors->first('hectareas_contratadas')"
        />

        <x-atoms.input
            type="number"
            name="aplicaciones_previstas"
            :label="__('comercial.contratos.campo_aplicaciones_previstas')"
            :value="$valor('aplicaciones_previstas')"
            min="1"
            step="1"
            required
            :error="$errors->first('aplicaciones_previstas')"
        />

        <x-atoms.input
            type="number"
            name="precio_ha"
            :label="__('comercial.contratos.campo_precio_ha')"
            :value="$valor('precio_ha')"
            min="0"
            step="0.01"
            required
            :help="__('comercial.contratos.campo_monto_total_ayuda')"
            :error="$errors->first('precio_ha')"
        />

        {{-- Valor estimado a cobrar (tarea "adelanto-calculado", 18/9/2026):
             solo lectura, sin `name` (no se postea — mismo patrón que el
             campo de lectura de `/panel/perfil`, documentado en
             `atoms/input.blade.php`). `monto_total` sigue sin ser un campo
             de este formulario: esto es un preview en vivo calculado por
             `resources/js/pages/contratos-form.js`, el valor real que se
             guarda lo recalcula siempre `Aplicacion/CrearContrato`/
             `ActualizarContrato` con `Brick\Math\BigDecimal` (invariante 6). --}}
        <x-atoms.input
            :name="null"
            id="valor_estimado_cobrar"
            :label="__('comercial.contratos.campo_valor_estimado_label')"
            value="0,00"
            readonly
            data-ag-valor-estimado
        />

        <x-atoms.input
            type="number"
            name="adelanto_monto"
            :label="__('comercial.contratos.campo_adelanto_monto')"
            :value="$valor('adelanto_monto')"
            min="0"
            step="0.01"
            :help="__('comercial.contratos.campo_adelanto_monto_ayuda')"
            :error="$errors->first('adelanto_monto')"
            data-plantilla-ayuda="{{ __('comercial.contratos.campo_adelanto_monto_ayuda') }}"
            data-plantilla-ayuda-maximo="{{ __('comercial.contratos.campo_adelanto_monto_ayuda_maximo') }}"
        />

        <x-atoms.date
            name="fecha_inicio"
            :label="__('comercial.contratos.campo_fecha_inicio')"
            :value="$fechaInicio"
            required
            :error="$errors->first('fecha_inicio')"
        />

        <x-atoms.date
            name="fecha_fin"
            :label="__('comercial.contratos.campo_fecha_fin')"
            :value="$fechaFin"
            :help="__('comercial.contratos.campo_fecha_fin_ayuda')"
            :error="$errors->first('fecha_fin')"
        />
    </x-molecules.form-section>

    {{-- Sección 2: Logística (reordenada, antes iba al final) --}}
    <x-molecules.form-section
        :title="__('comercial.contratos.seccion_logistica')"
        :count="__('comercial.contratos.campos_contador', ['cantidad' => 4])"
    >
        <div class="ag-form-section__field--full ag-contratos-form__logistica-switches">
            {{-- Los tres switches necesitan el hidden `value="0"` + `value="1"`
                 propio (mismo patrón que `personas/_formulario.blade.php` y
                 `lotes/_lote-terreno.blade.php`): sin esto, un switch tildado
                 manda el valor nativo del checkbox ("on"), que la regla
                 `boolean` de `CrearContratoRequest`/`ActualizarContratoRequest`
                 rechaza — "Este campo solo admite sí o no." — y como ningún
                 `<x-atoms.switch>` tiene prop de `error`, esa falla de
                 validación no se veía en ningún lado: el formulario se
                 quedaba quieto, sin aviso (bug real, 17/9/2026 → corregido). --}}
            <input type="hidden" name="brinda_alimentacion" value="0">
            <x-atoms.switch
                name="brinda_alimentacion"
                value="1"
                :label="__('comercial.contratos.campo_brinda_alimentacion')"
                :checked="$brindaAlimentacion"
                :error="$errors->first('brinda_alimentacion')"
            />

            <input type="hidden" name="brinda_hospedaje" value="0">
            <x-atoms.switch
                name="brinda_hospedaje"
                value="1"
                :label="__('comercial.contratos.campo_brinda_hospedaje')"
                :checked="$brindaHospedaje"
                :error="$errors->first('brinda_hospedaje')"
            />

            <input type="hidden" name="brinda_combustible" value="0">
            <x-atoms.switch
                name="brinda_combustible"
                value="1"
                :label="__('comercial.contratos.campo_brinda_combustible')"
                :checked="$brindaCombustible"
                :error="$errors->first('brinda_combustible')"
            />
        </div>

        <x-atoms.textarea
            class="ag-form-section__field--full"
            name="observaciones_logistica"
            :label="__('comercial.contratos.campo_observaciones_logistica')"
            :placeholder="__('comercial.contratos.campo_observaciones_logistica_placeholder')"
            :value="$valor('observaciones_logistica')"
            :error="$errors->first('observaciones_logistica')"
        />
    </x-molecules.form-section>

    {{-- Sección 3: Propiedad y lotes (nueva, tarea "contratos-lotes") --}}
    <x-molecules.form-section
        id="contrato-lotes"
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
            :label="__('comercial.contratos.campo_propiedad')"
            :placeholder="__('comercial.contratos.campo_propiedad_placeholder')"
            :help="__('comercial.contratos.campo_propiedad_ayuda')"
            :options="[]"
            data-ag-propiedades-select
            disabled
            action-icon="add"
            :action-href="route('panel.propiedades.create')"
            :action-label="__('comercial.contratos.crear_propiedad')"
            :action-text="__('comercial.contratos.crear_propiedad_corto')"
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

        {{-- Datos JSON embebidos: el otro contrato en conflicto por lote
             (tarea "contrato-lotes-conflicto") — el JS los usa para pintar
             el modal informativo sin pedirle nada al servidor. --}}
        <script type="application/json" data-ag-conflictos-lotes>
            {!! json_encode($conflictosPorLote) !!}
        </script>

        {{-- Lista apilada de lotes ya agregados (agrupada por propiedad) —
             partial propio, ver `_lotes-tabla.blade.php`. --}}
        @include('comercial::pages.contratos._lotes-tabla', [
            'lotesIniciales' => $lotesIniciales,
            'loteIdsConOrdenRegistrada' => $loteIdsConOrdenRegistrada,
            'conflictosPorLote' => $conflictosPorLote,
        ])
    </x-molecules.form-section>

    {{-- Modal único de selección de lotes por propiedad — partial propio,
         ver `_modal-lotes.blade.php`. --}}
    @include('comercial::pages.contratos._modal-lotes')

    {{-- Modal informativo del contrato en conflicto por un lote compartido —
         partial propio, ver `_modal-conflicto-lote.blade.php`. --}}
    @include('comercial::pages.contratos._modal-conflicto-lote')

    <x-organisms.form-actions-bar :status="__('comercial.contratos.estado_form')">
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.contratos.index')" cancelar />
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

        @if ($esEdicion && $resumenContrato !== null)
            {{-- Resumen (tarea "resumen de contrato"): sin datos de
                 aplicación todavía, empty-state con atajo a crear una orden
                 (mismo patrón que el aside de clientes); con datos, dos
                 tarjetas de solo lectura (facturación / aplicación) armadas
                 100% server-side en ContratosController::resumenContrato(). --}}
            <x-slot:aside>
                @if ($resumenContrato['tieneDatos'])
                    @foreach ($resumenContrato['tarjetas'] as $tarjeta)
                        <x-molecules.summary-card :title="$tarjeta['titulo']" :items="$tarjeta['items']">
                            @php
                                $accionesTarjeta = $tarjeta['acciones'] ?? (isset($tarjeta['accion']) ? [$tarjeta['accion']] : []);
                            @endphp
                            @if ($accionesTarjeta !== [])
                                <x-slot:action>
                                    {{-- Cada acción es un enlace real (con `href`) o un botón
                                         deshabilitado con `tooltip` que dice por qué. La tarjeta
                                         de órdenes enlaza al listado filtrado por contrato y a
                                         "Nueva orden" (ADR 0022); el "Ver más" de facturación
                                         sigue sin funcionalidad todavía: esa pantalla no filtra
                                         por `contrato_id`. La de trabajos no lleva acción. --}}
                                    @foreach ($accionesTarjeta as $accion)
                                        @if (! empty($accion['href']))
                                            <x-atoms.button :href="$accion['href']" variant="outline" size="sm" :icon="$accion['icon'] ?? 'open_in_new'">
                                                {{ $accion['label'] }}
                                            </x-atoms.button>
                                        @else
                                            <x-atoms.button type="button" variant="outline" size="sm" :icon="$accion['icon'] ?? 'open_in_new'" disabled :title="$accion['tooltip'] ?? null">
                                                {{ $accion['label'] }}
                                            </x-atoms.button>
                                        @endif
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @endforeach
                @else
                    <x-molecules.empty-state
                        :icon="$resumenContrato['icono']"
                        :title="$resumenContrato['titulo']"
                        :detail="$resumenContrato['detalle']"
                    >
                        @if ($resumenContrato['mostrarAccion'])
                            <x-slot:action>
                                @if (! empty($resumenContrato['accion']['href']))
                                    <x-atoms.button :href="$resumenContrato['accion']['href']" variant="outline" icon="add">
                                        {{ $resumenContrato['accion']['label'] }}
                                    </x-atoms.button>
                                @else
                                    <x-atoms.button type="button" variant="outline" icon="add" disabled :title="$resumenContrato['accion']['tooltip'] ?? null">
                                        {{ $resumenContrato['accion']['label'] }}
                                    </x-atoms.button>
                                @endif
                            </x-slot:action>
                        @endif
                    </x-molecules.empty-state>
                @endif
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
