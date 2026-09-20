{{--
    Partial: formulario de estadía en hacienda, compartido por create.blade.php
    y edit.blade.php — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `comercial::pages.propiedades._formulario` (secciones, barra de acciones,
    resumen relacionado) y que `campania::pages.campanias._formulario` (pasos
    de estado).

    Tres secciones, cada una con un propósito: QUIÉN y DÓNDE (cuadrilla y
    propiedad), CUÁNDO (entrada, y salida solo en el alta) y CÓMO (alojamiento,
    vehículo, observación).

    La salida NO es un campo de la edición: una estadía en curso se cierra con
    el paso «Finalizada» de `molecules/step-arrow`, que abre el modal de
    `_cambio-estado.blade.php` (FUERA de este `<form>`: un `<form>` no puede
    anidarse en otro, §6.3.4). En el alta sí se puede cargar, para registrar
    de una vez una estadía que ya terminó.

    Espera:
    - $estadia (EstadiaHacienda|null): null en alta; el modelo, en edición.
    - $equiposDisponibles (array<int, string>): cuadrillas para elegir; en
      edición trae solo la de la estadía (no se cambia).
    - $propiedadesDisponibles, $vehiculosDisponibles (array<int, string>).
    - $tiposAlojamiento (list<TipoAlojamiento>).
    - $equipoTrabajoIdPreseleccionado, $propiedadIdPreseleccionado (int|null,
      solo alta): accesos directos desde la ficha de la cuadrilla o de la
      propiedad.
    - $puedeCrearCuadrilla, $puedeCrearPropiedad (bool, solo alta): si el rol
      activo puede dar de alta cada una — sin el permiso, el acceso rápido
      «Nueva» del select no se dibuja. Lo resuelve el controlador.
    - Solo edición: $pasosEstado, $ayudaEstado (`PasosDeEstado`),
      $soloLectura (bool: una estadía finalizada ya no se edita) y
      $resumenRelacionado (tarjetas del aside, §6.3.1).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos.
--}}
@php
    $esEdicion = $estadia !== null;
    $soloLectura = $soloLectura ?? false;
    $accion = $esEdicion ? route('panel.estadias.update', $estadia) : route('panel.estadias.store');
    $tituloPagina = $esEdicion ? __('operaciones.estadias.titulo_editar') : __('operaciones.estadias.titulo_crear');
    $urlActual = url()->full();

    $equipoTrabajoId = old('equipo_trabajo_id', $estadia?->equipo_trabajo_id ?? $equipoTrabajoIdPreseleccionado ?? '');
    $propiedadId = old('propiedad_id', $estadia?->propiedad_id ?? $propiedadIdPreseleccionado ?? '');
    $entrada = old('entrada', $estadia?->entrada?->format('Y-m-d\TH:i') ?? '');
    $salida = old('salida', '');
    $tipoAlojamiento = old('tipo_alojamiento', $estadia?->tipo_alojamiento?->value ?? '');
    $vehiculoId = old('vehiculo_id', $estadia?->vehiculo_id ?? '');
    $observacion = old('observacion', $estadia?->observacion ?? '');

    $opcionesAlojamiento = collect($tiposAlojamiento)->mapWithKeys(fn ($tipo) => [
        $tipo->value => __('operaciones.estadias.alojamiento.'.$tipo->value)
    ])->all();

    // El acceso rápido «Nueva…» de cada select solo se ofrece en el alta y si
    // el rol activo puede crear ese objeto; navega con el memento de retorno.
    $retorno = ['volver_a' => $urlActual, 'volver_texto' => $tituloPagina];
    $puedeCrearCuadrilla = ! $esEdicion && ($puedeCrearCuadrilla ?? false);
    $puedeCrearPropiedad = ! $esEdicion && ($puedeCrearPropiedad ?? false);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-estadias-form" novalidate data-ag-estadias-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header :title="$tituloPagina" :subtitle="__('operaciones.estadias.subtitulo_form')">
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.estadias.index')"
                :label="__('operaciones.estadias.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    @if ($errors->has('estado'))
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('estado') }}
        </x-molecules.alert-strip>
    @endif

    {{-- El modal de finalizar vive fuera de este formulario: si su fecha de
         salida no valida, el aviso se pinta acá para que no pase inadvertido. --}}
    @if ($esEdicion && $errors->has('salida'))
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('salida') }}
        </x-molecules.alert-strip>
    @endif

    @if ($soloLectura)
        <x-molecules.alert-strip variant="info" icon="lock">
            {{ __('operaciones.estadias.solo_lectura_detalle') }}
        </x-molecules.alert-strip>
    @endif

    @if ($esEdicion)
        <x-molecules.step-arrow
            :steps="$pasosEstado"
            :label="__('operaciones.estadias.estado_pasos_aria')"
            :help="$ayudaEstado"
        />
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('operaciones.estadias.seccion_cuadrilla_lugar')"
            :count="__('operaciones.estadias.campos_contador', ['cantidad' => 2])"
        >
            <x-atoms.select
                name="equipo_trabajo_id"
                id="equipo_trabajo_id"
                icon="groups"
                :label="__('operaciones.estadias.campo_cuadrilla')"
                :options="$equiposDisponibles"
                :value="$equipoTrabajoId"
                :placeholder="__('operaciones.estadias.campo_cuadrilla_placeholder')"
                :help="$esEdicion ? __('operaciones.estadias.campo_cuadrilla_deshabilitada_ayuda') : __('operaciones.estadias.campo_cuadrilla_ayuda')"
                :required="! $esEdicion"
                :disabled="$esEdicion"
                :error="$errors->first('equipo_trabajo_id')"
                :action-icon="$puedeCrearCuadrilla ? 'add' : null"
                :action-href="$puedeCrearCuadrilla ? route('panel.cuadrillas.create', $retorno) : null"
                :action-label="__('operaciones.estadias.campo_cuadrilla_nueva')"
                :action-text="__('operaciones.estadias.accion_nueva_corto')"
            />

            <x-atoms.select
                name="propiedad_id"
                id="propiedad_id"
                icon="map"
                :label="__('operaciones.estadias.campo_propiedad')"
                :options="$propiedadesDisponibles"
                :value="$propiedadId"
                :placeholder="__('operaciones.estadias.campo_propiedad_placeholder')"
                :help="__('operaciones.estadias.campo_propiedad_ayuda')"
                required
                :disabled="$soloLectura"
                :error="$errors->first('propiedad_id')"
                :action-icon="$puedeCrearPropiedad ? 'add' : null"
                :action-href="$puedeCrearPropiedad ? route('panel.propiedades.create', $retorno) : null"
                :action-label="__('operaciones.estadias.campo_propiedad_nueva')"
                :action-text="__('operaciones.estadias.accion_nueva_corto')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('operaciones.estadias.seccion_fechas')"
            :count="__('operaciones.estadias.campos_contador', ['cantidad' => $esEdicion ? 1 : 2])"
        >
            <x-atoms.datetime
                name="entrada"
                :label="__('operaciones.estadias.campo_entrada')"
                :value="$entrada"
                :help="__('operaciones.estadias.campo_entrada_ayuda')"
                required
                :disabled="$soloLectura"
                :error="$errors->first('entrada')"
            />

            @if (! $esEdicion)
                <x-atoms.datetime
                    name="salida"
                    :label="__('operaciones.estadias.campo_salida')"
                    :value="$salida"
                    :help="__('operaciones.estadias.campo_salida_ayuda')"
                    :error="$errors->first('salida')"
                />
            @endif
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('operaciones.estadias.seccion_alojamiento')"
            :count="__('operaciones.estadias.campos_contador', ['cantidad' => 3])"
        >
            <x-atoms.select
                name="tipo_alojamiento"
                id="tipo_alojamiento"
                icon="holiday_village"
                :label="__('operaciones.estadias.campo_alojamiento')"
                :options="$opcionesAlojamiento"
                :value="$tipoAlojamiento"
                :placeholder="__('operaciones.estadias.campo_alojamiento_placeholder')"
                :help="__('operaciones.estadias.campo_alojamiento_ayuda')"
                required
                :disabled="$soloLectura"
                :error="$errors->first('tipo_alojamiento')"
            />

            <x-atoms.select
                name="vehiculo_id"
                id="vehiculo_id"
                icon="local_shipping"
                :label="__('operaciones.estadias.campo_vehiculo')"
                :options="$vehiculosDisponibles"
                :value="$vehiculoId"
                :placeholder="__('operaciones.estadias.campo_vehiculo_placeholder')"
                :help="__('operaciones.estadias.campo_vehiculo_ayuda')"
                :disabled="$soloLectura"
                :error="$errors->first('vehiculo_id')"
            />

            <div class="ag-form-section__field--full">
                <x-atoms.textarea
                    name="observacion"
                    :label="__('operaciones.estadias.campo_observacion')"
                    :value="$observacion"
                    :placeholder="__('operaciones.estadias.campo_observacion_placeholder')"
                    :help="__('operaciones.estadias.campo_observacion_ayuda')"
                    :rows="3"
                    :disabled="$soloLectura"
                    :error="$errors->first('observacion')"
                />
            </div>
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="$soloLectura ? __('operaciones.estadias.estado_form_solo_lectura') : __('operaciones.estadias.estado_form')">
            <x-slot:actions>
                <x-atoms.button :href="route('panel.estadias.index')" variant="outline">
                    {{ $soloLectura ? __('operaciones.estadias.volver') : __('ui.action.cancel') }}
                </x-atoms.button>
                @if (! $soloLectura)
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                @endif
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
                        <x-molecules.empty-state
                            :icon="$resumen['icono']"
                            :title="$resumen['vacioTitulo']"
                            :detail="$resumen['vacioDetalle']"
                        >
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
