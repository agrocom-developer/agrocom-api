{{--
    Partial: formulario de persona, compartido por create.blade.php y
    edit.blade.php (HU-26, tarea 37) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin sub-entidad repetible: dos
    selects (rol, base) + tres campos planos (nombre, tarifa_ha, activo).

    Homogeneizado con el patrón de Clientes y Cuadrillas (tarea 112): el
    cuerpo va en `molecules/form-layout` y, SOLO en edición, el aside con el
    resumen relacionado (§6.3.1) — una persona recién creada no puede tener
    todavía cuadrillas, usuario, sesiones ni anticipos.

    Espera:
    - $persona (PerPersona|null): null en alta; el modelo en edición.
    - $rolesOperativos (list<RolOperativoPersona>): opciones del select de rol
      (ver PersonasController) — la vista no conoce el enum de dominio más
      allá de sus `value` para armar el <option>. No se llama `roles`: ese
      nombre es de la cáscara (los roles del usuario) y lo usa el layout.
    - $basesDisponibles (Collection<int, string>): id => nombre (ver
      PersonasController::basesActivas()).
    - $baseIdInicial (string|null): solo alta; `?base_id=` del atajo «Nueva
      persona» de la ficha de una base.
    - $volverA (string|null): URL de un formulario de origen (alta rápida desde
      otro formulario, ver PersonasController::origenLocal()). En el alta viaja
      en un hidden; en edición, tras el alta, ofrece «Volver al formulario de
      origen» con esta persona ya elegida.
    - $resumenRelacionado (list<array{...}>|null): solo en edición, ver
      PersonasController::resumenRelacionado(). `null`/ausente en alta.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    `activo` sigue siendo un campo del formulario (con su `hidden` para que un
    switch apagado viaje como 0): es la única forma de dejar a una persona
    inactiva desde el panel, y `PersonasController::update()` lo lee tal cual —
    quitarlo la dejaría inactiva en cada edición. Ver runs/112.md.
--}}
@php
    $esEdicion = $persona !== null;
    $accion = $esEdicion ? route('panel.personas.update', $persona) : route('panel.personas.store');
    $nombre = old('nombre', $persona?->nombre ?? '');
    $rol = old('rol', $persona?->rol?->value ?? '');
    // En el alta, `?base_id=` (atajo «Nueva persona» de la ficha de una base) deja esa base elegida.
    $baseId = old('base_id', $persona?->base_id ?? ($baseIdInicial ?? ''));
    $tarifaHa = old('tarifa_ha', $persona?->tarifa_ha ?? '');
    $activo = old('activo', $persona?->activo ?? true);
    $opcionesRol = collect($rolesOperativos)->mapWithKeys(
        fn ($opcionRol) => [$opcionRol->value => __('personal.roles.'.$opcionRol->value)]
    );
    // Vuelta al formulario que pidió el alta rápida, con esta persona ya elegida (`persona_id`).
    $hrefOrigen = $esEdicion && ! empty($volverA)
        ? $volverA.(str_contains($volverA, '?') ? '&' : '?').'persona_id='.$persona->id
        : null;
@endphp

<form method="POST" action="{{ $accion }}" class="ag-personas-form" novalidate data-ag-personas-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    {{-- Alta rápida desde otro formulario: el origen viaja en el POST y `store()`
         lo deja en sesión para ofrecer el botón de vuelta en la ficha de edición. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('personal.personas.titulo_editar') : __('personal.personas.titulo_crear')"
        :subtitle="__('personal.personas.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.personas.index')"
                :label="__('personal.personas.volver')"
                :retorno="$esEdicion ? ['persona_id' => $persona->id] : []"
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
            :title="__('personal.personas.seccion_datos')"
            :count="__('personal.personas.campos_contador', ['cantidad' => 5])"
        >
            <x-atoms.input
                type="text"
                name="nombre"
                :label="__('personal.personas.campo_nombre')"
                :value="$nombre"
                required
                :error="$errors->first('nombre')"
            />

            <x-atoms.select
                name="rol"
                id="rol"
                :label="__('personal.personas.campo_rol')"
                :options="$opcionesRol"
                :value="$rol"
                :placeholder="__('personal.personas.campo_rol_placeholder')"
                :error="$errors->first('rol')"
                required
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('personal.personas.campo_base')"
                :options="$basesDisponibles"
                :value="$baseId"
                :placeholder="__('personal.personas.campo_base_placeholder')"
                :error="$errors->first('base_id')"
            />

            <x-atoms.input
                type="number"
                name="tarifa_ha"
                :label="__('personal.personas.campo_tarifa')"
                :value="$tarifaHa"
                :help="__('personal.personas.campo_tarifa_ayuda')"
                :error="$errors->first('tarifa_ha')"
                min="0"
                step="0.01"
            />

            <div class="ag-form-section__field--full">
                <input type="hidden" name="activo" value="0">
                <x-atoms.switch
                    name="activo"
                    value="1"
                    :label="__('personal.personas.campo_activo')"
                    :checked="(bool) $activo"
                    :help="__('personal.personas.campo_activo_ayuda')"
                />
            </div>
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('personal.personas.estado_form')">
            <x-slot:actions>
                @if ($hrefOrigen !== null)
                    <x-atoms.button :href="$hrefOrigen" variant="outline" icon="arrow_back">
                        {{ __('personal.personas.volver_a_formulario_origen') }}
                    </x-atoms.button>
                @endif
                <x-atoms.button :href="route('panel.personas.index')" variant="outline">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @foreach ($resumenRelacionado ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'arrow_forward'" block>
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'add'">
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.empty-state>
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
