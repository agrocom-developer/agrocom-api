{{--
    Partial: formulario de equipo de trabajo, compartido por create.blade.php
    y edit.blade.php (tarea 72, HU-49) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `generadores/_formulario.blade.php`, con `base_id` obligatorio (a
    diferencia de generadores) y un par `desde`/`hasta` para la vigencia del
    equipo en sí.

    Espera:
    - $equipo (EquipoTrabajo|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre.
    - $estados (list<EstadoEquipoTrabajo>): opciones del select de estado.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    Integrantes y recursos NO se editan acá: viven en la ficha
    (`show.blade.php`), cada uno con su propia vigencia — ADR 0015 punto 3.
--}}
@php
    $esEdicion = $equipo !== null;
    $accion = $esEdicion ? route('panel.cuadrillas.update', $equipo) : route('panel.cuadrillas.store');
    $codigo = old('codigo', $equipo?->codigo ?? '');
    $nombre = old('nombre', $equipo?->nombre ?? '');
    $baseId = old('base_id', $equipo?->base_id ?? '');
    $estado = old('estado', $equipo?->estado?->value ?? 'activo');
    $desde = old('desde', $equipo?->desde?->toDateString() ?? '');
    $hasta = old('hasta', $equipo?->hasta?->toDateString() ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-cuadrillas-form" novalidate data-ag-cuadrillas-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('personal.equipos_trabajo.titulo_editar') : __('personal.equipos_trabajo.titulo_crear')"
        :subtitle="__('personal.equipos_trabajo.subtitulo_form')"
    >
        <x-slot:actions>
            {{-- Memento de navegación: si se llegó desde el acceso rápido «Crear
                 cuadrilla» del alta de Orden de Trabajo, vuelve ahí. --}}
            <x-molecules.boton-volver
                :href="route('panel.cuadrillas.index')"
                :label="__('personal.equipos_trabajo.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('personal.equipos_trabajo.seccion_datos')"
        :count="__('personal.equipos_trabajo.campos_contador', ['cantidad' => 6])"
    >
        <x-atoms.input
            type="text"
            name="codigo"
            :label="__('personal.equipos_trabajo.campo_codigo')"
            :value="$codigo"
            required
            maxlength="20"
            :error="$errors->first('codigo')"
        />

        <x-atoms.input
            type="text"
            name="nombre"
            :label="__('personal.equipos_trabajo.campo_nombre')"
            :value="$nombre"
            :error="$errors->first('nombre')"
        />

        <x-atoms.select
            name="base_id"
            id="base_id"
            :label="__('personal.equipos_trabajo.campo_base')"
            :options="$basesDisponibles"
            :value="$baseId"
            :placeholder="__('personal.equipos_trabajo.campo_base_placeholder')"
            required
            :error="$errors->first('base_id')"
        />

        @php
            $opcionesEstado = collect($estados)->mapWithKeys(fn ($opcion) => [
                $opcion->value => __('personal.estado.'.$opcion->value)
            ])->all();
        @endphp
        <x-atoms.select
            name="estado"
            id="estado"
            :label="__('personal.equipos_trabajo.campo_estado')"
            :options="$opcionesEstado"
            :value="$estado"
            required
            :error="$errors->first('estado')"
        />

        <x-atoms.date
            name="desde"
            :label="__('personal.equipos_trabajo.campo_desde')"
            :value="$desde"
            required
            :error="$errors->first('desde')"
        />

        <x-atoms.date
            name="hasta"
            :label="__('personal.equipos_trabajo.campo_hasta')"
            :value="$hasta"
            :help="__('personal.equipos_trabajo.campo_hasta_ayuda')"
            :error="$errors->first('hasta')"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('personal.equipos_trabajo.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.cuadrillas.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
