{{--
    Partial: formulario de batería, compartido por create.blade.php y
    edit.blade.php (HU-39, tarea 51) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md.

    Homogeneizado con el patrón de Propiedades y Drones (tarea 115): el cuerpo
    va en `molecules/form-layout` y, SOLO en edición, el aside con el resumen
    relacionado (§6.3.1) — una batería recién creada no puede tener todavía
    cuadrillas ni recargas. Dos secciones: los datos de la batería y sus
    ciclos, con la unidad («ciclos») como sufijo del campo.

    Espera:
    - $bateria (Bateria|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver BateriasController::basesDisponibles()).
    - $estados (list<EstadoBateria>): opciones del select de estado (ver
      BateriasController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$estados` en
      `vehiculos/_formulario.blade.php`.
    - $resumenRelacionado (list<array{...}>|null): solo en edición, ver
      BateriasController::resumenRelacionado(). `null`/ausente en alta.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición. `ciclos_acumulados` arranca en 0
    en alta (no vacío): el campo es requerido y numérico, un placeholder
    vacío invitaría a dejarlo en blanco.

    `ciclos_inicial` (HU-83, tarea 98) es editable SOLO en alta: es el punto
    de partida del historial, fijado una vez e inmutable después —
    `ActualizarBateria` no lo recibe (ver su docblock). En edición se
    muestra de solo lectura con `x-atoms.input` sin `name` (mismo patrón
    que el campo "Nombre" de `seguridad/perfil/index.blade.php`): no viaja
    en el POST, así que `ActualizarBateriaRequest` ni siquiera necesita
    ignorarlo.

    `motivo_correccion` (HU-87, tarea 102) solo aparece en edición: es la
    única puerta para bajar `ciclos_acumulados` a mano (ver el docblock de
    `ActualizarBateria`) — en el alta no hay un valor previo que bajar, así
    que el campo no tiene sentido ahí. Siempre visible en vez de aparecer
    condicionalmente al detectar una baja: mostrarlo/ocultarlo con JS según
    lo que el usuario tipea en otro campo es más frágil que dejarlo fijo y
    opcional, y la ayuda ya aclara cuándo es obligatorio.
--}}
@php
    $esEdicion = $bateria !== null;
    $accion = $esEdicion ? route('panel.baterias.update', $bateria) : route('panel.baterias.store');
    $identificador = old('identificador', $bateria?->identificador ?? '');
    $ciclosInicial = old('ciclos_inicial', $bateria?->ciclos_inicial ?? 0);
    $ciclosAcumulados = old('ciclos_acumulados', $bateria?->ciclos_acumulados ?? 0);
    $baseId = old('base_id', $bateria?->base_id ?? '');
    $estado = old('estado', $bateria?->estado ?? 'activa');
    $motivoCorreccion = old('motivo_correccion', '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-baterias-form" novalidate data-ag-baterias-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.baterias.titulo_editar') : __('mantenimiento.baterias.titulo_crear')"
        :subtitle="__('mantenimiento.baterias.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.baterias.index')"
                :label="__('mantenimiento.baterias.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('mantenimiento.baterias.seccion_datos')"
            :count="__('mantenimiento.baterias.campos_contador', ['cantidad' => 3])"
        >
            <x-atoms.input
                type="text"
                name="identificador"
                :label="__('mantenimiento.baterias.campo_identificador')"
                :value="$identificador"
                required
                :error="$errors->first('identificador')"
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('mantenimiento.baterias.campo_base')"
                :options="$basesDisponibles"
                :value="$baseId"
                :placeholder="__('mantenimiento.baterias.campo_base_placeholder')"
                :error="$errors->first('base_id')"
            />

            @php
                $opcionesEstado = collect($estados)->mapWithKeys(fn ($opcion) => [
                    $opcion->value => __('mantenimiento.estado_bateria.'.$opcion->value),
                ])->all();
            @endphp
            <x-atoms.select
                name="estado"
                id="estado"
                :label="__('mantenimiento.baterias.campo_estado')"
                :options="$opcionesEstado"
                :value="$estado"
                required
                :error="$errors->first('estado')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('mantenimiento.baterias.seccion_ciclos')"
            :count="__('mantenimiento.baterias.campos_contador', ['cantidad' => $esEdicion ? 3 : 2])"
        >
            @if ($esEdicion)
                {{-- Sin `name`: no viaja en el POST, mismo criterio que el
                     campo "Nombre" de seguridad/perfil/index.blade.php.
                     `readonly` y no `disabled` para que siga siendo enfocable,
                     copiable y legible por un lector de pantalla. --}}
                <x-atoms.input
                    type="number"
                    id="ciclos_inicial"
                    :label="__('mantenimiento.baterias.campo_ciclos_inicial')"
                    :value="$ciclosInicial"
                    :suffix="__('mantenimiento.baterias.unidad_ciclos')"
                    :help="__('mantenimiento.baterias.campo_ciclos_inicial_ayuda')"
                    readonly
                />
            @else
                <x-atoms.input
                    type="number"
                    name="ciclos_inicial"
                    :label="__('mantenimiento.baterias.campo_ciclos_inicial')"
                    :value="$ciclosInicial"
                    :suffix="__('mantenimiento.baterias.unidad_ciclos')"
                    :help="__('mantenimiento.baterias.campo_ciclos_inicial_ayuda')"
                    min="0"
                    required
                    :error="$errors->first('ciclos_inicial')"
                />
            @endif

            <x-atoms.input
                type="number"
                name="ciclos_acumulados"
                :label="__('mantenimiento.baterias.campo_ciclos')"
                :value="$ciclosAcumulados"
                :suffix="__('mantenimiento.baterias.unidad_ciclos')"
                :help="$esEdicion ? __('mantenimiento.baterias.campo_ciclos_correccion_ayuda') : null"
                min="0"
                required
                :error="$errors->first('ciclos_acumulados')"
            />

            @if ($esEdicion)
                <x-atoms.input
                    type="text"
                    name="motivo_correccion"
                    class="ag-form-section__field--full"
                    :label="__('mantenimiento.baterias.campo_motivo_correccion')"
                    :value="$motivoCorreccion"
                    :help="__('mantenimiento.baterias.campo_motivo_correccion_ayuda')"
                    :error="$errors->first('motivo_correccion')"
                />
            @endif
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('mantenimiento.baterias.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.baterias.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @include('mantenimiento::pages._resumen-relacionado', ['resumenRelacionado' => $resumenRelacionado ?? []])
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
