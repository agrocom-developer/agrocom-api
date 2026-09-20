{{--
    Partial: formulario de una orden de mantenimiento, compartido por
    create.blade.php y edit.blade.php (tarea 116) — arquetipo Formulario, §6.3
    de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `comercial::pages.contratos._formulario`, con una diferencia que ordena
    toda la vista.

    **La orden no tiene edición de sus datos descriptivos.** No existe
    `update()` en `OrdenesMantenimientoController` (ver su docblock): una vez
    abierta, equipo, tipo y descripción quedan como se registraron. Así que
    el `<form>` postea a `store()` en el alta y a `cerrar()` en la ficha —el
    único cambio que la orden admite es su transición de estado—, y en la
    ficha los datos de apertura se dibujan como campos de LECTURA, no se
    esconden (§6.3.5: un formulario no esconde secciones).

    Por lo mismo, el modal de `_cambio-estado.blade.php` NO trae un `<form>`
    propio como el de contratos: el `<form>` que envía es este, porque el
    cierre viaja con la descripción final y los repuestos elegidos acá. El
    modal solo confirma y lo envía por su `id`.

    Espera:
    - $orden (OrdenMantenimiento|null): null en alta.
    - $dronesDisponibles / $vehiculosDisponibles (Collection<int, string>):
      solo en alta, id => identificador.
    - $etiquetaEquipo (string): solo en la ficha, el identificador ya
      resuelto por el controlador (OrdenMantenimiento no tiene relación
      Eloquent hacia Dron/Vehiculo, son de módulos distintos sin FK real).
    - $repuestosDisponibles / $basesDisponibles (Collection<int, string>):
      opciones del selector del cierre.
    - $stockPorRepuesto (array<int, array<int, string>>): `repuesto_id =>
      [base_id => cantidad]`, solo para pintar la disponibilidad en el
      cliente — el servidor revalida igual en
      `MaquinaEstadosOrdenMantenimiento::cerrar()`.
    - $consumos (LengthAwarePaginator<int, DatosConsumoOrden>|null): las
      líneas que el cierre descontó, por el contrato de lectura de Inventario.
    - $nombresBase (array<int, string>): base_id => nombre, para la tabla de
      consumos.
    - $puedeCerrar (bool): gatea el selector y el botón de cierre
      (presentación, no autorización — el servidor revalida).
    - $pasosEstado (list<array>|null), $ayudaEstado (string|null): solo en la
      ficha (`PasosDeOrdenMantenimiento`).
    - $resumenRelacionado (list<array>|null): solo en la ficha de una orden
      CERRADA (ver el aside más abajo).

    Selector de repuestos como tabla de detalle (tarea 116, reemplaza al
    `checkbox-group` con campos inyectados por `extraPorOpcion`): una fila por
    repuesto del catálogo, con su casilla, la disponibilidad en la base
    elegida, la cantidad y la base de esa línea. **No pagina a propósito**:
    cambiar de página recarga y perdería lo ya marcado; la tabla scrollea
    dentro de su propio contenedor. La de repuestos ya consumidos (orden
    cerrada) sí pagina — es solo lectura y no hay nada que perder.

    Los nombres de campo del cierre no cambian:
    `repuestos[N][repuesto_id|base_id|cantidad]`, con N el id del propio
    repuesto (array asociativo — el controlador lo reindexa a lista antes de
    pasarlo a la máquina de estados, que no se toca).
--}}
@php
    $esAbiertaEnum = \App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento::Abierta;
    $cerradaEnum = \App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento::Cerrada;
    $presentadorPasos = \App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento::class;

    $esEdicion = $orden !== null;
    $esAbierta = $esEdicion && $orden->estado === $esAbiertaEnum;
    $puedeElegirRepuestos = $esAbierta && $puedeCerrar;
    $accion = $esEdicion
        ? route('panel.ordenes-mantenimiento.cerrar', $orden)
        : route('panel.ordenes-mantenimiento.store');
    $tituloPagina = $esEdicion
        ? __('mantenimiento.ordenes.titulo_ficha')
        : __('mantenimiento.ordenes.titulo_crear');

    $equipoTipo = old('equipo_tipo', '');
    $equipoId = old('equipo_id', '');

    // old('repuestos') llega keyed por repuesto_id (mismo esquema que envía
    // el formulario) — se arma un mapa repuesto_id => línea para repintar
    // cantidad y base tras un error de validación.
    $lineasOld = collect(old('repuestos', []))
        ->filter(fn ($linea) => is_array($linea) && ! empty($linea['repuesto_id'] ?? null))
        ->mapWithKeys(fn (array $linea) => [(int) $linea['repuesto_id'] => [
            'base_id' => $linea['base_id'] ?? null,
            'cantidad' => $linea['cantidad'] ?? null,
        ]]);
    $baseGlobalId = old('base_global') ?? $lineasOld->first()['base_id'] ?? null;
    $repuestosMarcados = $lineasOld->keys()->all();

    $modalCierre = $presentadorPasos::modal($cerradaEnum);
    $tonoPorEstado = $presentadorPasos::TONO_POR_ESTADO;
@endphp

<form
    id="orden-mantenimiento-form"
    method="POST"
    action="{{ $accion }}"
    class="ag-ordenes-mantenimiento-form"
    novalidate
    data-ag-orden-form
>
    @csrf

    <x-organisms.page-header
        :title="$tituloPagina"
        :subtitle="$esEdicion ? __('mantenimiento.ordenes.subtitulo_ficha') : __('mantenimiento.ordenes.subtitulo_form')"
    >
        @if ($esEdicion)
            <x-slot:chip>
                <x-atoms.badge :variant="$tonoPorEstado[$orden->estado->value]">
                    {{ __('mantenimiento.orden.estado.'.$orden->estado->value) }}
                </x-atoms.badge>
            </x-slot:chip>
        @endif

        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.ordenes-mantenimiento.index')" :label="__('mantenimiento.ordenes.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    @if ($errors->has('repuestos'))
        {{-- Stock insuficiente o transición rechazada: vuelven acá como error
             de sesión sobre `repuestos` (ver
             OrdenesMantenimientoController::cerrar()). No es de un campo
             puntual, por eso va arriba y no dentro de la sección. --}}
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('repuestos') }}
        </x-molecules.alert-strip>
    @endif

    @if ($esEdicion)
        <x-molecules.step-arrow
            :steps="$pasosEstado"
            :label="__('mantenimiento.ordenes.estado_pasos_aria')"
            :help="$ayudaEstado"
        />
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('mantenimiento.ordenes.seccion_datos')"
            :count="__('mantenimiento.ordenes.campos_contador', ['cantidad' => $esEdicion ? 4 : 4])"
        >
            @if ($esEdicion)
                {{-- Campos de LECTURA (`:name="null"` + `readonly`, el patrón
                     que documenta `atoms/input`): la orden no admite editar
                     sus datos de apertura, y esconder la sección sería peor
                     que mostrarla sin poder tocarla (§6.3.5). --}}
                <x-atoms.input
                    :name="null"
                    id="orden_equipo"
                    :label="__('mantenimiento.ordenes.campo_equipo')"
                    :value="__('mantenimiento.equipo_tipo.'.$orden->equipo_tipo).' · '.$etiquetaEquipo"
                    readonly
                />

                <x-atoms.input
                    :name="null"
                    id="orden_tipo"
                    :label="__('mantenimiento.ordenes.campo_tipo')"
                    :value="__('mantenimiento.tipo_orden.'.$orden->tipo)"
                    readonly
                />

                <x-atoms.input
                    :name="null"
                    id="orden_fecha_apertura"
                    :label="__('mantenimiento.ordenes.detalle_fecha_apertura')"
                    :value="$orden->fecha_apertura->format('d/m/Y H:i')"
                    readonly
                />

                <x-atoms.input
                    :name="null"
                    id="orden_fecha_cierre"
                    :label="__('mantenimiento.ordenes.detalle_fecha_cierre')"
                    :value="$orden->fecha_cierre?->format('d/m/Y H:i') ?? __('mantenimiento.ordenes.sin_fecha_cierre')"
                    readonly
                />

                <x-atoms.textarea
                    :name="null"
                    id="orden_descripcion"
                    class="ag-form-section__field--full"
                    :label="__('mantenimiento.ordenes.campo_descripcion')"
                    :value="$orden->descripcion"
                    :help="__('mantenimiento.ordenes.campo_descripcion_lectura_ayuda')"
                    rows="3"
                    readonly
                />
            @else
                @php
                    $opcionesEquipoTipo = [
                        'dron' => __('mantenimiento.equipo_tipo.dron'),
                        'vehiculo' => __('mantenimiento.equipo_tipo.vehiculo'),
                    ];
                    $opcionesTipo = [
                        'preventivo' => __('mantenimiento.tipo_orden.preventivo'),
                        'correctivo' => __('mantenimiento.tipo_orden.correctivo'),
                    ];
                @endphp

                <x-atoms.select
                    name="equipo_tipo"
                    id="equipo_tipo"
                    :label="__('mantenimiento.ordenes.campo_equipo_tipo')"
                    :options="$opcionesEquipoTipo"
                    :value="$equipoTipo"
                    :placeholder="__('mantenimiento.ordenes.campo_equipo_tipo_placeholder')"
                    required
                    :error="$errors->first('equipo_tipo')"
                    data-ag-orden-equipo-tipo
                />

                <div data-ag-orden-campo="dron">
                    <x-atoms.select
                        name="equipo_id"
                        id="equipo_id_dron"
                        :label="__('mantenimiento.ordenes.campo_equipo_dron')"
                        :options="$dronesDisponibles"
                        :value="$equipoTipo === 'dron' ? $equipoId : ''"
                        :placeholder="__('mantenimiento.ordenes.campo_equipo_dron_placeholder')"
                    />
                </div>

                <div data-ag-orden-campo="vehiculo">
                    <x-atoms.select
                        name="equipo_id"
                        id="equipo_id_vehiculo"
                        :label="__('mantenimiento.ordenes.campo_equipo_vehiculo')"
                        :options="$vehiculosDisponibles"
                        :value="$equipoTipo === 'vehiculo' ? $equipoId : ''"
                        :placeholder="__('mantenimiento.ordenes.campo_equipo_vehiculo_placeholder')"
                        :error="$errors->first('equipo_id')"
                    />
                </div>

                <x-atoms.select
                    name="tipo"
                    id="tipo"
                    :label="__('mantenimiento.ordenes.campo_tipo')"
                    :options="$opcionesTipo"
                    :value="old('tipo', '')"
                    :placeholder="__('mantenimiento.ordenes.campo_tipo_placeholder')"
                    required
                    :error="$errors->first('tipo')"
                />

                <x-atoms.textarea
                    name="descripcion"
                    class="ag-form-section__field--full"
                    :label="__('mantenimiento.ordenes.campo_descripcion')"
                    :value="old('descripcion', '')"
                    :help="__('mantenimiento.ordenes.campo_descripcion_ayuda')"
                    rows="3"
                    required
                    :error="$errors->first('descripcion')"
                />
            @endif
        </x-molecules.form-section>

        @if ($esEdicion)
            @include('mantenimiento::pages.ordenes._repuestos-seccion', [
                'orden' => $orden,
                'puedeElegirRepuestos' => $puedeElegirRepuestos,
                'esAbierta' => $esAbierta,
                'repuestosDisponibles' => $repuestosDisponibles,
                'basesDisponibles' => $basesDisponibles,
                'stockPorRepuesto' => $stockPorRepuesto,
                'repuestosMarcados' => $repuestosMarcados,
                'lineasOld' => $lineasOld,
                'baseGlobalId' => $baseGlobalId,
                'consumos' => $consumos,
                'nombresBase' => $nombresBase,
            ])

            <x-molecules.form-section
                :title="__('mantenimiento.ordenes.seccion_cierre')"
                :count="__('mantenimiento.ordenes.campos_contador', ['cantidad' => 1])"
            >
                @if ($puedeElegirRepuestos)
                    <p class="ag-form-section__field--full ag-ordenes-mantenimiento-form__ayuda">
                        {{ __('mantenimiento.ordenes.seccion_cierre_ayuda') }}
                    </p>

                    <x-atoms.textarea
                        name="descripcion_final"
                        class="ag-form-section__field--full"
                        :label="__('mantenimiento.ordenes.campo_descripcion_final')"
                        :help="__('mantenimiento.ordenes.campo_descripcion_final_ayuda')"
                        :value="old('descripcion_final')"
                        required
                        :error="$errors->first('descripcion_final')"
                    />
                @elseif ($esAbierta)
                    <div class="ag-form-section__field--full">
                        <x-molecules.empty-state
                            icon="lock"
                            :title="__('mantenimiento.ordenes.cierre_sin_permiso_titulo')"
                            :detail="__('mantenimiento.ordenes.cierre_sin_permiso_detalle')"
                        />
                    </div>
                @else
                    <x-atoms.textarea
                        :name="null"
                        id="orden_descripcion_final"
                        class="ag-form-section__field--full"
                        :label="__('mantenimiento.ordenes.campo_descripcion_final')"
                        :value="$orden->descripcion_final"
                        rows="3"
                        readonly
                    />
                @endif
            </x-molecules.form-section>
        @endif

        <x-organisms.form-actions-bar :status="$esEdicion ? __('mantenimiento.ordenes.estado_ficha') : __('mantenimiento.ordenes.estado_form')">
            <x-slot:actions>
                <x-atoms.button :href="route('panel.ordenes-mantenimiento.index')" variant="outline">
                    {{ $esEdicion ? __('ui.action.close') : __('ui.action.cancel') }}
                </x-atoms.button>

                @if ($esEdicion)
                    @if ($puedeElegirRepuestos)
                        {{-- Segunda puerta al MISMO modal que abre el paso
                             «Cerrada» de arriba (`confirm-modal` acepta varios
                             disparadores): quien terminó de completar el
                             cierre lo tiene acá abajo, sin volver a subir. --}}
                        <x-atoms.button type="button" variant="primary" icon="build" data-bs-toggle="modal" :data-bs-target="'#'.$modalCierre">
                            {{ __('mantenimiento.ordenes.boton_cerrar') }}
                        </x-atoms.button>
                    @endif
                @else
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                @endif
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @if ($esAbierta)
                    {{-- Plan §3.5: mientras la orden no llegó al estado en el
                         que hay algo que resumir, el aside no muestra tarjetas
                         vacías — una sola sección informativa dice qué falta.
                         Acá los repuestos consumidos y el gasto no existen
                         hasta que se cierra: los escribe el propio cierre. --}}
                    <x-molecules.empty-state
                        icon="pending_actions"
                        :title="__('mantenimiento.ordenes.aside_abierta_titulo')"
                        :detail="__('mantenimiento.ordenes.aside_abierta_detalle')"
                    />
                @else
                    @include('mantenimiento::pages._resumen-relacionado', ['resumenRelacionado' => $resumenRelacionado])
                @endif
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>

@if ($esEdicion)
    @include('mantenimiento::pages.ordenes._cambio-estado', [
        'orden' => $orden,
        'pasosEstado' => $pasosEstado,
    ])
@endif
