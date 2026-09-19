{{--
    Partial: formulario de campaña, compartido por create.blade.php y
    edit.blade.php (ADR 0015 punto 1, tarea 69) — arquetipo Formulario, §6.3
    de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `personal/bases/_formulario.blade.php`. Sin selector de cliente desde la
    corrección del 15/9/2026: la campaña es un catálogo compartido, el
    vínculo con el cliente lo pone el contrato.

    Espera:
    - $campania (Campania|null): null en alta; el modelo en edición.
    - $resumenCampania (list<array{...}>|null): solo en edición, ver
      CampaniasController::resumenCampania(). `null`/ausente en alta, y
      también `null` en edición mientras la campaña sigue `planificada` —ahí
      el aside muestra un empty-state en vez de tarjetas en cero.
    - $pasosEstado (list<array{...}>|null): solo en edición, los pasos de
      `molecules/step-arrow` (`PasosDeEstado::armar()`). `null`/ausente en alta.
    - $ayudaEstado (string|null): solo en edición, el párrafo bajo los pasos
      (qué se puede hacer ahora). `null`/ausente en alta.

    `estado` NUNCA es un campo de este formulario: lo cambia
    `panel.campanias.cambiar-estado` (otra pantalla, otra responsabilidad —
    invariante 7) — ver docblock de `CampaniasController`. Los pasos de
    `step-arrow` solo MUESTRAN el estado y abren el modal de confirmación;
    los `<form>` de ese cambio y sus modales viven en
    `_cambio-estado.blade.php`, FUERA de este `<form>` (un `<form>` no puede
    anidarse en otro).

    `estacion` (HU-77, tarea 93) sí es un campo, catálogo cerrado
    invierno/verano. `nombre` queda opcional con ayuda: vacío autogenera al
    alta (`CrearCampania`) y se preserva tal cual al editar
    (`ActualizarCampania`) — nunca se pisa solo.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    Aside pegajoso (§6.3.1 de la guía de pantalla, 15/9/2026): SOLO en
    edición — una campaña recién creada no puede tener contratos, gastos ni
    trabajos todavía. Dos `summary-card` de solo lectura (Financiero,
    Trabajo), sin alternar con `empty-state`: a diferencia del resumen
    relacionado de cliente, acá el "dato" es una magnitud que siempre existe
    (aunque sea cero), no un listado con atajo de alta.
--}}
@php
    $esEdicion = $campania !== null;
    $accion = $esEdicion ? route('panel.campanias.update', $campania) : route('panel.campanias.store');
    $codigo = old('codigo', $campania?->codigo ?? '');
    $nombre = old('nombre', $campania?->nombre ?? '');
    $estacion = old('estacion', $campania?->estacion ?? '');
    $fechaInicio = old('fecha_inicio', $campania?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $campania?->fecha_fin?->toDateString() ?? '');
    $opcionesEstacion = [
        'invierno' => __('campania.campania.estacion.invierno'),
        'verano' => __('campania.campania.estacion.verano'),
    ];
@endphp

<form method="POST" action="{{ $accion }}" class="ag-campanias-form" novalidate data-ag-campanias-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('campania.campanias.titulo_editar') : __('campania.campanias.titulo_crear')"
        :subtitle="__('campania.campanias.subtitulo_form')"
    >
        @if ($esEdicion)
            <x-slot:chip>
                <span class="ag-campanias-form__estado-chip {{ $campania->esActiva() ? 'ag-campanias-form__estado-chip--activa' : 'ag-campanias-form__estado-chip--inactiva' }}">
                    <x-atoms.icon :name="$campania->esActiva() ? 'check_circle' : 'radio_button_unchecked'" size="sm" />
                    <span>{{ __($campania->esActiva() ? 'campania.campanias.actividad_activa' : 'campania.campanias.actividad_inactiva') }}</span>
                </span>
            </x-slot:chip>
        @endif

        <x-slot:actions>
            <x-atoms.button :href="route('panel.campanias.index')" variant="outline" icon="arrow_back">
                {{ __('campania.campanias.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
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
            :label="__('campania.campanias.estado_pasos_aria')"
            :help="$ayudaEstado"
        />
    @endif

    <x-molecules.form-layout>
    <x-molecules.form-section
        :title="__('campania.campanias.seccion_datos')"
        :count="__('campania.campanias.campos_contador', ['cantidad' => 5])"
    >
        <x-atoms.input
            type="text"
            name="codigo"
            :label="__('campania.campanias.campo_codigo')"
            :value="$codigo"
            required
            maxlength="20"
            :error="$errors->first('codigo')"
        />

        <x-atoms.select
            name="estacion"
            id="estacion"
            :label="__('campania.campanias.campo_estacion')"
            :placeholder="__('campania.campanias.campo_estacion_placeholder')"
            :options="$opcionesEstacion"
            :value="$estacion"
            required
            :error="$errors->first('estacion')"
        />

        <x-atoms.input
            type="text"
            name="nombre"
            :label="__('campania.campanias.campo_nombre')"
            :value="$nombre"
            :help="__('campania.campanias.campo_nombre_ayuda')"
            :error="$errors->first('nombre')"
        />

        <x-atoms.date
            name="fecha_inicio"
            :label="__('campania.campanias.campo_fecha_inicio')"
            :value="$fechaInicio"
            required
            :error="$errors->first('fecha_inicio')"
        />

        <x-atoms.date
            name="fecha_fin"
            :label="__('campania.campanias.campo_fecha_fin')"
            :value="$fechaFin"
            required
            :error="$errors->first('fecha_fin')"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('campania.campanias.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.campanias.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @if ($resumenCampania !== null)
                    @foreach ($resumenCampania as $resumen)
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="arrow_forward" block>
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        </x-molecules.summary-card>
                    @endforeach
                @else
                    <x-molecules.empty-state
                        icon="calendar_month"
                        :title="__('campania.campanias.aside_no_abierta_titulo')"
                        :detail="__('campania.campanias.aside_no_abierta_detalle')"
                    />
                @endif
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
