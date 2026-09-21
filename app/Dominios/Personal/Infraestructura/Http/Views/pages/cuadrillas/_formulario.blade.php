{{--
    Partial: formulario de cuadrilla, compartido por create.blade.php y
    edit.blade.php — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `comercial::pages.propiedades._formulario` (secciones, barra de acciones,
    resumen relacionado) y que `campania::pages.campanias._formulario` (pasos
    de estado).

    Cuatro secciones, cada una con un propósito: Identificación, Vigencia,
    Integrantes y Equipamiento. Las dos primeras son campos en alta y en
    edición. Las otras dos cambian de forma:

    (Accesorios salió del formulario el 21/9/2026, pedido del dueño: hoy solo
    interesa quiénes integran la cuadrilla y con qué dron trabajan. Tablas,
    rutas y casos de uso de accesorios quedan, sin pantalla que los use.)

    - ALTA: la cuadrilla se arma de una sola vez (`ArmarCuadrilla`). Piloto,
      ayudante y dron son obligatorios; segundo ayudante, camioneta, generador
      y baterías, opcionales. Cada select de persona o de recurso es un
      input-group: lleva pegado el acceso rápido «Nuevo» (props `action*` de
      `atoms/select`) para dar de alta lo que falte sin perder lo ya escrito
      (`shared/borrador-formulario.js` guarda el borrador). Las baterías son
      casillas en fila, como «¿Qué lleva la calda?» de la Orden de Trabajo.
    - EDICIÓN: son tablas de detalle paginadas (`integrantes_page`,
      `equipamiento_page`), cada una con su botón
      «Agregar» en la cabecera de la sección, que abre un diálogo. Los
      `<form>` y los diálogos de esas acciones viven en
      `_detalle-modales.blade.php`, DESPUÉS de este formulario: un `<form>`
      no puede anidarse en otro. Por eso acá todo botón de fila es
      `type="button"` y solo abre su diálogo.

    `estado` NUNCA es un campo: lo cambian los pasos de `step-arrow`
    (`_cambio-estado.blade.php`, invariante 7).

    Espera (ver CuadrillasController::create()/edit()):
    - $equipo (EquipoTrabajo|null): null en alta; el modelo, en edición.
    - $basesDisponibles (Collection<int, string>).
    - Alta: $pilotosDisponibles, $ayudantesDisponibles, $dronesDisponibles,
      $vehiculosDisponibles, $generadoresDisponibles, $bateriasDisponibles,
      $puedeCrearPersona/Dron/Vehiculo/Generador, $volverA.
    - Edición: $pasosEstado, $ayudaEstado, paginadores $integrantes y
      $equipamiento, contadores, $tonoPorEstado y
      $resumenRelacionado (aside, §6.3.1).
    - $puedeCrearBase (bool): acceso rápido «Nueva» del select de base.

    La última columna de las tablas de detalle mide `9rem` y no
    `var(--ag-row-actions-width)` (16rem, pensado para las 3 acciones de un
    listado): acá cada fila tiene UNA acción corta y la tabla vive en la
    columna principal del formulario, no a todo el ancho — con 16rem las
    columnas de datos quedaban aplastadas.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos.
--}}
@php
    $esEdicion = $equipo !== null;
    $accion = $esEdicion ? route('panel.cuadrillas.update', $equipo) : route('panel.cuadrillas.store');
    $tituloPagina = $esEdicion ? __('personal.equipos_trabajo.titulo_editar') : __('personal.equipos_trabajo.titulo_crear');

    $codigo = old('codigo', $equipo?->codigo ?? '');
    $nombre = old('nombre', $equipo?->nombre ?? '');
    $baseId = old('base_id', $equipo?->base_id ?? '');
    $desde = old('desde', $equipo?->desde?->toDateString() ?? '');
    $hasta = old('hasta', $equipo?->hasta?->toDateString() ?? '');

    // Memento de retorno de los accesos rápidos «Nuevo…»: vuelven a ESTA pantalla.
    $retorno = ['volver_a' => url()->full(), 'volver_texto' => $tituloPagina];
    $puedeCrearBase = $puedeCrearBase ?? false;
    $puedeCrearPersona = $puedeCrearPersona ?? false;

    $iconoPorTipo = ['dron' => 'flight', 'bateria' => 'battery_charging_full', 'generador' => 'bolt', 'vehiculo' => 'local_shipping'];
@endphp

<form method="POST" action="{{ $accion }}" class="ag-cuadrillas-form" novalidate data-ag-cuadrillas-form
    data-error-persona-repetida="{{ __('personal.equipos_trabajo.error_persona_repetida') }}">
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    {{-- Alta rápida desde otro formulario («Crear cuadrilla» del alta de Orden de
         Trabajo o de una estadía): solo hace falta reenviarlo en el alta. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    <x-organisms.page-header :title="$tituloPagina" :subtitle="__('personal.equipos_trabajo.subtitulo_form')">
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="route('panel.cuadrillas.index')"
                :label="__('personal.equipos_trabajo.volver')"
                :retorno="$esEdicion ? ['equipo_trabajo_id' => $equipo->id] : []"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    {{-- Una persona o un recurso vigente en OTRA cuadrilla se guarda igual, con aviso. --}}
    @if (session('aviso'))
        <x-molecules.alert-strip variant="warning" icon="warning">
            {{ session('aviso') }}
        </x-molecules.alert-strip>
    @endif

    {{-- Los diálogos de detalle viven fuera de este formulario: si lo que se
         cargó en uno no valida, el aviso se pinta acá para que no pase inadvertido. --}}
    @if ($esEdicion)
        @foreach (['estado', 'persona_id', 'rol_equipo', 'recurso_tipo', 'recurso_id'] as $campoDetalle)
            @if ($errors->has($campoDetalle))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first($campoDetalle) }}
                </x-molecules.alert-strip>
                @break
            @endif
        @endforeach
    @endif

    @if ($esEdicion)
        <x-molecules.step-arrow
            :steps="$pasosEstado"
            :label="__('personal.equipos_trabajo.estado_pasos_aria')"
            :help="$ayudaEstado"
        />
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('personal.equipos_trabajo.seccion_identificacion')"
            :count="__('personal.equipos_trabajo.campos_contador', ['cantidad' => 3])"
        >
            <x-atoms.input
                type="text"
                name="codigo"
                :label="__('personal.equipos_trabajo.campo_codigo')"
                :value="$codigo"
                :help="__('personal.equipos_trabajo.campo_codigo_ayuda')"
                required
                maxlength="20"
                :error="$errors->first('codigo')"
            />

            <x-atoms.input
                type="text"
                name="nombre"
                :label="__('personal.equipos_trabajo.campo_nombre')"
                :value="$nombre"
                :help="__('personal.equipos_trabajo.campo_nombre_ayuda')"
                :error="$errors->first('nombre')"
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                icon="home_work"
                :label="__('personal.equipos_trabajo.campo_base')"
                :options="$basesDisponibles"
                :value="$baseId"
                :placeholder="__('personal.equipos_trabajo.campo_base_placeholder')"
                :help="__('personal.equipos_trabajo.campo_base_ayuda')"
                required
                :error="$errors->first('base_id')"
                :action-icon="$puedeCrearBase ? 'add' : null"
                :action-href="$puedeCrearBase ? route('panel.bases.create', $retorno) : null"
                :action-label="__('personal.equipos_trabajo.accion_nueva_base')"
                :action-text="__('personal.equipos_trabajo.accion_nueva_corto')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('personal.equipos_trabajo.seccion_vigencia')"
            :count="__('personal.equipos_trabajo.campos_contador', ['cantidad' => 2])"
        >
            <x-atoms.date
                name="desde"
                :label="__('personal.equipos_trabajo.campo_desde')"
                :value="$desde"
                :help="__('personal.equipos_trabajo.campo_desde_ayuda')"
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

        @if (! $esEdicion)
            <x-molecules.form-section
                :title="__('personal.equipos_trabajo.seccion_integrantes')"
                :count="__('personal.equipos_trabajo.campos_contador', ['cantidad' => 3])"
            >
                @foreach ([
                    ['piloto_id', 'piloto', $pilotosDisponibles, true],
                    ['ayudante_id', 'ayudante', $ayudantesDisponibles, true],
                    ['ayudante2_id', 'ayudante2', $ayudantesDisponibles, false],
                ] as [$campo, $puesto, $opciones, $obligatorio])
                    <x-atoms.select
                        :name="$campo"
                        :id="$campo"
                        icon="person"
                        :label="__('personal.equipos_trabajo.campo_'.$puesto)"
                        :options="$opciones"
                        :value="old($campo, '')"
                        :placeholder="__('personal.equipos_trabajo.campo_'.$puesto.'_placeholder')"
                        :help="__('personal.equipos_trabajo.campo_'.$puesto.'_ayuda')"
                        :required="$obligatorio"
                        :error="$errors->first($campo)"
                        :action-icon="$puedeCrearPersona ? 'person_add' : null"
                        :action-href="$puedeCrearPersona ? route('panel.personas.create', $retorno) : null"
                        :action-label="__('personal.equipos_trabajo.accion_nuevo_personal')"
                        :action-text="__('personal.equipos_trabajo.accion_nuevo_corto')"
                        :data-ag-select-integrante="$puesto"
                    />
                @endforeach
            </x-molecules.form-section>

            <x-molecules.form-section
                :title="__('personal.equipos_trabajo.seccion_equipamiento')"
                :count="__('personal.equipos_trabajo.campos_contador', ['cantidad' => 4])"
            >
                @foreach ([
                    ['dron_id', 'dron', $dronesDisponibles, true, $puedeCrearDron ?? false, 'panel.drones.create'],
                    ['vehiculo_id', 'vehiculo', $vehiculosDisponibles, false, $puedeCrearVehiculo ?? false, 'panel.vehiculos.create'],
                    ['generador_id', 'generador', $generadoresDisponibles, false, $puedeCrearGenerador ?? false, 'panel.generadores.create'],
                ] as [$campo, $tipo, $opciones, $obligatorio, $puedeCrearRecurso, $rutaCrear])
                    <x-atoms.select
                        :name="$campo"
                        :id="$campo"
                        :icon="$iconoPorTipo[$tipo]"
                        :label="__('personal.equipos_trabajo.campo_'.$tipo)"
                        :options="$opciones"
                        :value="old($campo, '')"
                        :placeholder="__('personal.equipos_trabajo.campo_'.$tipo.'_placeholder')"
                        :help="__('personal.equipos_trabajo.campo_'.$tipo.'_ayuda')"
                        :required="$obligatorio"
                        :error="$errors->first($campo)"
                        :action-icon="$puedeCrearRecurso ? 'add' : null"
                        :action-href="$puedeCrearRecurso ? route($rutaCrear, $retorno) : null"
                        :action-label="__('personal.equipos_trabajo.accion_nuevo_'.$tipo)"
                        :action-text="__('personal.equipos_trabajo.accion_nuevo_corto')"
                    />
                @endforeach

                <div class="ag-form-section__field--full">
                    @if ($bateriasDisponibles->isEmpty())
                        <x-molecules.empty-state
                            icon="battery_charging_full"
                            :title="__('personal.equipos_trabajo.baterias_vacio_titulo')"
                            :detail="__('personal.equipos_trabajo.baterias_vacio_detalle')"
                        />
                    @else
                        <x-atoms.checkbox-group
                            name="bateria_ids"
                            class="ag-cuadrillas-form__baterias"
                            :label="__('personal.equipos_trabajo.campo_baterias')"
                            :options="$bateriasDisponibles"
                            :value="old('bateria_ids', [])"
                            :help="__('personal.equipos_trabajo.campo_baterias_ayuda')"
                            :error="$errors->first('bateria_ids')"
                        />
                    @endif
                </div>
            </x-molecules.form-section>
        @else
            {{-- INTEGRANTES: quién es piloto y quién ayudante, con su vigencia. --}}
            <x-molecules.form-section
                :title="__('personal.equipos_trabajo.seccion_integrantes')"
                :count="trans_choice('personal.equipos_trabajo.contador_vigentes', $contadorIntegrantesVigentes, ['cantidad' => $contadorIntegrantesVigentes])"
            >
                <x-slot:actions>
                    <x-atoms.button variant="outline" size="sm" icon="person_add" data-bs-toggle="modal" data-bs-target="#cuadrilla-integrante-modal">
                        {{ __('personal.equipos_trabajo.detalle_agregar_integrante') }}
                    </x-atoms.button>
                </x-slot:actions>

                <div class="ag-form-section__field--full">
                    @if ($integrantes->isEmpty())
                        <x-molecules.empty-state
                            icon="groups"
                            :title="__('personal.equipos_trabajo.detalle_integrantes_vacio_titulo')"
                            :detail="__('personal.equipos_trabajo.detalle_integrantes_vacio_detalle')"
                        />
                    @else
                        <x-molecules.index-table columns="3rem minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 9rem">
                            <x-slot:head>
                                <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_persona') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_rol') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.campo_desde') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.campo_hasta') }}</span>
                                <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                            </x-slot:head>

                            @foreach ($integrantes as $integrante)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell" class="ag-index-table__indice">
                                        {{ ($integrantes->currentPage() - 1) * $integrantes->perPage() + $loop->iteration }}
                                    </span>
                                    <span role="cell">{{ $integrante->persona?->nombre ?? '—' }}</span>
                                    <span role="cell">
                                        <x-atoms.badge :variant="$integrante->rol_equipo->value === 'piloto' ? 'success' : 'neutral'">
                                            {{ __('personal.rol_equipo.'.$integrante->rol_equipo->value) }}
                                        </x-atoms.badge>
                                    </span>
                                    <span role="cell" class="ag-cuadrillas__mono">{{ $integrante->desde->format('d/m/Y') }}</span>
                                    <span role="cell" class="ag-cuadrillas__mono">{{ $integrante->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente') }}</span>
                                    <span role="cell" class="ag-index-table__acciones">
                                        @if ($integrante->hasta === null)
                                            <x-organisms.row-actions>
                                                <x-atoms.button variant="warning-outline" size="sm" icon="event_busy" data-bs-toggle="modal" :data-bs-target="'#cuadrilla-integrante-finalizar-'.$integrante->id">
                                                    {{ __('personal.equipos_trabajo.detalle_finalizar') }}
                                                </x-atoms.button>
                                            </x-organisms.row-actions>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>

                        <x-molecules.pagination :paginator="$integrantes" :aria-label="__('personal.equipos_trabajo.detalle_integrantes_paginacion')" />
                    @endif
                </div>
            </x-molecules.form-section>

            {{-- EQUIPAMIENTO: dron, baterías, generador y camioneta, con su vigencia. --}}
            <x-molecules.form-section
                :title="__('personal.equipos_trabajo.seccion_equipamiento')"
                :count="trans_choice('personal.equipos_trabajo.contador_baterias', $contadorBateriasVigentes, ['cantidad' => $contadorBateriasVigentes])"
            >
                <x-slot:actions>
                    <x-atoms.button variant="outline" size="sm" icon="add" data-bs-toggle="modal" data-bs-target="#cuadrilla-equipamiento-modal">
                        {{ __('personal.equipos_trabajo.detalle_agregar_equipamiento') }}
                    </x-atoms.button>
                </x-slot:actions>

                <div class="ag-form-section__field--full">
                    @if ($equipamiento->isEmpty())
                        <x-molecules.empty-state
                            icon="flight"
                            :title="__('personal.equipos_trabajo.detalle_equipamiento_vacio_titulo')"
                            :detail="__('personal.equipos_trabajo.detalle_equipamiento_vacio_detalle')"
                        />
                    @else
                        <x-molecules.index-table columns="3rem minmax(0, 1.1fr) minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) 9rem">
                            <x-slot:head>
                                <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_tipo') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.detalle_col_recurso') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.campo_desde') }}</span>
                                <span role="columnheader">{{ __('personal.equipos_trabajo.campo_hasta') }}</span>
                                <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                            </x-slot:head>

                            @foreach ($equipamiento as $recurso)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell" class="ag-index-table__indice">
                                        {{ ($equipamiento->currentPage() - 1) * $equipamiento->perPage() + $loop->iteration }}
                                    </span>
                                    <span role="cell">
                                        <x-atoms.badge variant="neutral" :icon="$iconoPorTipo[$recurso['tipo']] ?? null">
                                            {{ __('personal.recurso_tipo.'.$recurso['tipo']) }}
                                        </x-atoms.badge>
                                    </span>
                                    <span role="cell" class="ag-cuadrillas__mono">{{ $recurso['etiqueta'] }}</span>
                                    <span role="cell" class="ag-cuadrillas__mono">{{ \Illuminate\Support\Carbon::parse($recurso['desde'])->format('d/m/Y') }}</span>
                                    <span role="cell" class="ag-cuadrillas__mono">{{ $recurso['hasta'] ? \Illuminate\Support\Carbon::parse($recurso['hasta'])->format('d/m/Y') : __('personal.equipos_trabajo.vigente') }}</span>
                                    <span role="cell" class="ag-index-table__acciones">
                                        @if ($recurso['hasta'] === null)
                                            <x-organisms.row-actions>
                                                <x-atoms.button variant="warning-outline" size="sm" icon="event_busy" data-bs-toggle="modal" :data-bs-target="'#cuadrilla-recurso-finalizar-'.$recurso['id']">
                                                    {{ __('personal.equipos_trabajo.detalle_finalizar') }}
                                                </x-atoms.button>
                                            </x-organisms.row-actions>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>

                        <x-molecules.pagination :paginator="$equipamiento" :aria-label="__('personal.equipos_trabajo.detalle_equipamiento_paginacion')" />
                    @endif
                </div>
            </x-molecules.form-section>
        @endif

        <x-organisms.form-actions-bar :status="__('personal.equipos_trabajo.estado_form')">
            <x-slot:actions>
                @if ($esEdicion && ! empty($volverA))
                    <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}equipo_trabajo_id={{ $equipo->id }}" variant="outline" icon="arrow_back">
                        {{ __('personal.equipos_trabajo.volver_a_formulario_origen') }}
                    </x-atoms.button>
                @endif
                <x-molecules.boton-volver :href="route('panel.cuadrillas.index')" :retorno="$esEdicion ? ['equipo_trabajo_id' => $equipo->id] : []" cancelar />
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
