{{--
    Page: cuadrillas/show (GET /panel/cuadrillas/{equipoTrabajo}, panel.cuadrillas.show)
    Ficha de un equipo de trabajo (tarea 72, HU-49, ADR 0015 punto 3),
    homogeneizada al arquetipo Detalle en la tarea 125 (§6.4 de
    docs/diseno/guia_pantalla_panel.md, mismo molde que
    `operaciones::pages.trabajos.show`): integrantes y recursos VIGENTES A
    UNA FECHA elegida (filtro, default hoy) — tiene que poder responder
    "quiénes lo integraban el 14 de marzo", no solo "quiénes lo integran
    hoy". SOLO LECTURA desde esta tarea: los formularios de alta/finalizar
    (HU-101 punto 4) viven en `cuadrillas/edit` — acá se enlaza a Editar, sin
    duplicarlos.

    Datos esperados (ver CuadrillasController::show()): la cáscara de
    CascaraPanel, más:
    - $equipo (EquipoTrabajo), $nombreBase (string).
    - $fecha (string, ISO), $fechaEsHoy (bool): la fecha consultada.
    - $integrantes (list<DatosIntegranteEquipo>), $recursos (list<DatosRecursoEquipo>):
      vigentes a `$fecha` (contrato `LecturaEquipoTrabajo`).
    - $integrantesHistoricos (Collection<EquipoIntegrante>): TODOS los que
      alguna vez integraron la cuadrilla, sin filtrar por `$fecha`.
    - $accesorios (Collection<EquipoAccesorio>): sin vigencia, lista plana.
    - $etiquetasRecurso (array<string, string>): clave "{tipo}:{id}" =>
      identificador, ya resuelto por el controlador (ADR 0003 regla 3 — un
      recurso cruza a `Operaciones`/`Mantenimiento`).
    - $tonoPorEstado (array<string, string>): estado → tono del badge.
    - $diasVigencia (int), $estadiasEnCurso (?int, null si no llega por
      permiso), $creadaPor (?string).
    - $vinculos (list), $actividad (list).
    - $puedeEditar (bool): gatea el botón "Editar" de la cabecera.

    Gateada por `personal.equipo_trabajo.ver`. Estilos en
    resources/css/pages/cuadrillas.css y resources/css/pages/detalle.css
    (arquetipo Detalle) — cero color hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $vigenciaTexto = $equipo->desde->format('d/m/Y').' – '.($equipo->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente'));
@endphp
<x-templates.panel-shell :title="__('personal.equipos_trabajo.ficha_titulo', ['codigo' => $equipo->codigo])" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('personal.equipos_trabajo.ficha_titulo', ['codigo' => $equipo->codigo])"
                :subtitle="__('personal.equipos_trabajo.ficha_subtitulo')"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$tonoPorEstado[$equipo->estado->value] ?? 'neutral'">
                        {{ __('personal.equipos_trabajo.estado.'.$equipo->estado->value) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver
                        :href="route('panel.cuadrillas.index')"
                        :label="__('personal.equipos_trabajo.ficha_volver')"
                    />

                    @if ($puedeEditar)
                        <x-atoms.button :href="route('panel.cuadrillas.edit', $equipo)" variant="warning-outline" icon="edit">
                            {{ __('personal.equipos_trabajo.editar') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('personal.equipos_trabajo.kpi_integrantes')"
                    icon="groups"
                    :value="count($integrantes)"
                />
                <x-molecules.stat-card
                    :label="__('personal.equipos_trabajo.kpi_recursos')"
                    icon="precision_manufacturing"
                    :value="count($recursos)"
                />
                <x-molecules.stat-card
                    :label="__('personal.equipos_trabajo.kpi_vigencia')"
                    icon="event"
                    :value="$diasVigencia"
                    :value-suffix="__('personal.equipos_trabajo.kpi_vigencia_sufijo')"
                    :foot="$vigenciaTexto"
                />
                @if ($estadiasEnCurso !== null)
                    <x-molecules.stat-card
                        :label="__('personal.equipos_trabajo.kpi_estadias')"
                        icon="holiday_village"
                        :value="$estadiasEnCurso"
                        :state="$estadiasEnCurso > 0 ? 'success' : null"
                    />
                @endif
            </div>

            <div class="ag-table-toolbar">
                <x-organisms.filter-panel
                    :action="route('panel.cuadrillas.show', $equipo)"
                    :active-count="$fechaEsHoy ? 0 : 1"
                    :trigger-label="__('personal.equipos_trabajo.ficha_selector_fecha')"
                >
                    <x-atoms.date
                        name="fecha"
                        :label="__('personal.equipos_trabajo.ficha_selector_fecha')"
                        :value="$fecha"
                        required
                    />
                </x-organisms.filter-panel>
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="info" :title="__('personal.equipos_trabajo.ficha_seccion_integrantes')" :count="(string) count($integrantes)">
                    <div class="ag-form-section__field--full">
                        @if (count($integrantes) === 0)
                            <x-molecules.empty-state
                                icon="groups"
                                :title="__('personal.equipos_trabajo.ficha_integrantes_vacio_titulo')"
                                :detail="__('personal.equipos_trabajo.ficha_integrantes_vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="1.6fr 1fr 7rem 7rem">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_persona') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_rol') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_desde') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_hasta') }}</span>
                                </x-slot:head>

                                @foreach ($integrantes as $integrante)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $integrante->nombrePersona }}</span>
                                        <span role="cell">
                                            <x-atoms.badge :variant="$integrante->rolEquipo === 'piloto' ? 'success' : 'neutral'">
                                                {{ __('personal.rol_equipo.'.$integrante->rolEquipo) }}
                                            </x-atoms.badge>
                                        </span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ \Illuminate\Support\Carbon::parse($integrante->desde)->format('d/m/Y') }}</span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $integrante->hasta ? \Illuminate\Support\Carbon::parse($integrante->hasta)->format('d/m/Y') : __('personal.equipos_trabajo.vigente') }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-1" :title="__('personal.equipos_trabajo.ficha_historico_titulo')" :count="(string) $integrantesHistoricos->count()">
                    <div class="ag-form-section__field--full">
                        @if ($integrantesHistoricos->isEmpty())
                            <x-molecules.empty-state
                                icon="history"
                                :title="__('personal.equipos_trabajo.ficha_historico_vacio_titulo')"
                                :detail="__('personal.equipos_trabajo.ficha_historico_vacio_detalle')"
                            />
                        @else
                            <x-molecules.index-table columns="1.6fr 1fr 7rem 7rem">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_persona') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_rol') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_desde') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_hasta') }}</span>
                                </x-slot:head>

                                @foreach ($integrantesHistoricos as $integrante)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $integrante->persona?->nombre ?? '—' }}</span>
                                        <span role="cell">
                                            <x-atoms.badge :variant="$integrante->rol_equipo->value === 'piloto' ? 'success' : 'neutral'">
                                                {{ __('personal.rol_equipo.'.$integrante->rol_equipo->value) }}
                                            </x-atoms.badge>
                                        </span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $integrante->desde->format('d/m/Y') }}</span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $integrante->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente') }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('personal.equipos_trabajo.ficha_seccion_recursos')" :count="(string) count($recursos)">
                    <div class="ag-form-section__field--full">
                        @if (count($recursos) === 0)
                            <x-molecules.empty-state
                                icon="precision_manufacturing"
                                :title="__('personal.equipos_trabajo.ficha_recursos_vacio_titulo')"
                                :detail="__('personal.equipos_trabajo.ficha_recursos_vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="1fr 1.6fr 7rem 7rem">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_tipo') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.detalle_col_recurso') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_desde') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.campo_hasta') }}</span>
                                </x-slot:head>

                                @foreach ($recursos as $recurso)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">
                                            <x-atoms.badge variant="neutral">{{ __('personal.recurso_tipo.'.$recurso->recursoTipo) }}</x-atoms.badge>
                                        </span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $etiquetasRecurso["{$recurso->recursoTipo}:{$recurso->recursoId}"] ?? "#{$recurso->recursoId}" }}</span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ \Illuminate\Support\Carbon::parse($recurso->desde)->format('d/m/Y') }}</span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $recurso->hasta ? \Illuminate\Support\Carbon::parse($recurso->hasta)->format('d/m/Y') : __('personal.equipos_trabajo.vigente') }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-2" :title="__('personal.equipos_trabajo.ficha_seccion_accesorios')" :count="(string) $accesorios->count()">
                    <div class="ag-form-section__field--full">
                        @if ($accesorios->isEmpty())
                            <x-molecules.empty-state
                                icon="inventory_2"
                                :title="__('personal.equipos_trabajo.detalle_accesorios_vacio_titulo')"
                                :detail="__('personal.equipos_trabajo.detalle_accesorios_vacio_detalle')"
                            />
                        @else
                            <x-molecules.index-table columns="1.6fr 6rem 1fr">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_accesorio') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_cantidad') }}</span>
                                    <span role="columnheader">{{ __('personal.equipos_trabajo.ficha_campo_observacion') }}</span>
                                </x-slot:head>

                                @foreach ($accesorios as $accesorio)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $accesorio->accesorio?->nombre ?? '—' }}</span>
                                        <span role="cell" class="ag-cuadrillas__mono">{{ $accesorio->cantidad }}</span>
                                        <span role="cell">{{ $accesorio->observacion ?? '—' }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.summary-card
                        :title="__('personal.equipos_trabajo.aside_datos_titulo')"
                        :items="[
                            ['label' => __('personal.equipos_trabajo.aside_datos_base'), 'value' => $nombreBase],
                            ['label' => __('personal.equipos_trabajo.aside_datos_vigencia'), 'value' => $vigenciaTexto, 'mono' => true],
                            ['label' => __('personal.equipos_trabajo.aside_datos_creada_por'), 'value' => $creadaPor ?? '—'],
                        ]"
                    />

                    @if (count($vinculos))
                        <x-molecules.form-section accent="alert" :title="__('personal.equipos_trabajo.aside_relacionado_titulo')">
                            <div class="ag-form-section__field--full ag-detalle__vinculos">
                                @foreach ($vinculos as $vinculo)
                                    <x-molecules.link-row
                                        :href="$vinculo['href']"
                                        :icon="$vinculo['icon']"
                                        :title="$vinculo['title']"
                                        :meta="$vinculo['meta']"
                                        :tone="$vinculo['tone']"
                                    />
                                @endforeach
                            </div>
                        </x-molecules.form-section>
                    @endif

                    <x-molecules.form-section accent="distintivo-2" :title="__('personal.equipos_trabajo.aside_actividad_titulo')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
