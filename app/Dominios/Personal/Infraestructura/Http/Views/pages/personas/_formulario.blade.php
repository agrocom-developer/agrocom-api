{{--
    Partial: formulario de persona, compartido por create.blade.php y
    edit.blade.php (HU-26, tarea 37) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin sub-entidad repetible. Tres
    secciones (21/9/2026, pedido del dueño): Datos personales (nombres,
    apellidos y cédula), Datos de referencia (celular, correo y dirección) y
    Trabajo en campo (puesto y base). El nombre completo que
    muestra el resto del sistema lo compone el servidor.

    «Puesto» es `per_personas.rol`, la clasificación de campo que decide en
    qué select de la cuadrilla aparece la persona. No es el acceso al sistema:
    eso es el usuario vinculado, que se ve en el resumen de la derecha.

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

    `activo` NO es un campo (guía §6.3.3; Personas era la que faltaba, corregida
    el 21/9/2026 a pedido del dueño). Una persona nace activa y editarla no
    toca ese dato.
--}}
@php
    $esEdicion = $persona !== null;
    $accion = $esEdicion ? route('panel.personas.update', $persona) : route('panel.personas.store');
    $nombres = old('nombres', $persona?->nombres ?? '');
    $apellidoPaterno = old('apellido_paterno', $persona?->apellido_paterno ?? '');
    $apellidoMaterno = old('apellido_materno', $persona?->apellido_materno ?? '');
    $ci = old('ci', $persona?->ci ?? '');
    $celular = old('celular', $persona?->celular ?? '');
    $correo = old('correo', $persona?->correo ?? '');
    $direccion = old('direccion', $persona?->direccion ?? '');
    $rol = old('rol', $persona?->rol?->value ?? '');
    // En el alta, `?base_id=` (atajo «Nueva persona» de la ficha de una base) deja esa base elegida.
    $baseId = old('base_id', $persona?->base_id ?? ($baseIdInicial ?? ''));
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
            :count="__('personal.personas.campos_contador', ['cantidad' => 4])"
        >
            <div class="ag-form-section__field--full">
                <x-atoms.input
                    type="text"
                    name="nombres"
                    :label="__('personal.personas.campo_nombres')"
                    :value="$nombres"
                    required
                    maxlength="80"
                    autocomplete="off"
                    :error="$errors->first('nombres')"
                />
            </div>

            <x-atoms.input
                type="text"
                name="apellido_paterno"
                :label="__('personal.personas.campo_apellido_paterno')"
                :value="$apellidoPaterno"
                required
                maxlength="80"
                autocomplete="off"
                :error="$errors->first('apellido_paterno')"
            />

            <x-atoms.input
                type="text"
                name="apellido_materno"
                :label="__('personal.personas.campo_apellido_materno')"
                :value="$apellidoMaterno"
                maxlength="80"
                autocomplete="off"
                :error="$errors->first('apellido_materno')"
            />

            <x-atoms.input
                type="text"
                name="ci"
                :label="__('personal.personas.campo_ci')"
                :value="$ci"
                :help="__('personal.personas.campo_ci_ayuda')"
                required
                maxlength="20"
                autocomplete="off"
                :error="$errors->first('ci')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('personal.personas.seccion_referencia')"
            :count="__('personal.personas.campos_contador', ['cantidad' => 3])"
        >
            <x-atoms.input
                type="tel"
                name="celular"
                :label="__('personal.personas.campo_celular')"
                :value="$celular"
                required
                maxlength="20"
                autocomplete="off"
                :error="$errors->first('celular')"
            />

            <x-atoms.input
                type="email"
                name="correo"
                :label="__('personal.personas.campo_correo')"
                :value="$correo"
                maxlength="150"
                autocomplete="off"
                :error="$errors->first('correo')"
            />

            <div class="ag-form-section__field--full">
                <x-atoms.input
                    type="text"
                    name="direccion"
                    :label="__('personal.personas.campo_direccion')"
                    :value="$direccion"
                    :help="__('personal.personas.campo_direccion_ayuda')"
                    maxlength="255"
                    autocomplete="off"
                    :error="$errors->first('direccion')"
                />
            </div>
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('personal.personas.seccion_trabajo')"
            :count="__('personal.personas.campos_contador', ['cantidad' => 3])"
        >
            <x-atoms.select
                name="rol"
                id="rol"
                :label="__('personal.personas.campo_rol')"
                :options="$opcionesRol"
                :value="$rol"
                :placeholder="__('personal.personas.campo_rol_placeholder')"
                :help="__('personal.personas.campo_rol_ayuda')"
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
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('personal.personas.estado_form')">
            <x-slot:actions>
                @if ($hrefOrigen !== null)
                    <x-atoms.button :href="$hrefOrigen" variant="outline" icon="arrow_back">
                        {{ __('personal.personas.volver_a_formulario_origen') }}
                    </x-atoms.button>
                @endif
                <x-molecules.boton-volver :href="route('panel.personas.index')" :retorno="$esEdicion ? ['persona_id' => $persona->id] : []" cancelar />
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
