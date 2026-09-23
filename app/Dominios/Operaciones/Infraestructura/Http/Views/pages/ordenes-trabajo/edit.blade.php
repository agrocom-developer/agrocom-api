{{--
    Page: ordenes-trabajo/edit (GET/PUT /panel/trabajos/{ordenTrabajo}/editar,
    panel.trabajos.edit/update). Edición de la cabecera de una Orden de
    Trabajo —indicaciones compartidas: calda, límites climáticos, parámetros
    de vuelo— y, por equipo, su condición de pago (tarea 127, ADR 0023).

    El REPARTO (equipos, lotes, hectáreas, turno) NO se edita acá: sigue
    siendo por `Trabajo`, desde la ficha («Editar» de cada fila). Un aviso
    corto en la sección de equipos lo dice y enlaza a la ficha.

    Comparte las secciones de indicaciones con `create.blade.php` vía
    `_indicaciones.blade.php` —mismas reglas de validación
    (`Concerns\ValidaIndicacionesOrdenTrabajo`)— y la condición de pago de
    cada equipo con `_equipo-condicion.blade.php`.

    Datos esperados (ver OrdenesTrabajoController::edit()): la cáscara de
    CascaraPanel, más:
    - $ordenTrabajo (OrdenTrabajo, con `orden.categoriaInsumo`,
      `trabajos.sesiones`, `equipos`).
    - $equiposParaFormulario (list<array{equipo_trabajo_id, etiqueta,
      admiteCondicion, condicion}>): la condición se edita solo si TODOS los
      trabajos de ese equipo siguen `abierto` (`PoliticaEdicionOrdenTrabajo::admiteCondicion()`);
      para los demás se muestra de solo lectura con el porqué.
    - $exigeMotivo (bool): algún trabajo ya `cerrado` — la corrección pide
      motivo (`PoliticaEdicionOrdenTrabajo::exigeMotivo()`).
    - $esLiquido (bool): tipo de insumo de la orden, para la sección de calda.
    - $tarifasDisponibles, $modalidadesPago: catálogo de Finanzas (ADR 0023).
    - $resumenRelacionado (list): tarjetas del aside (trabajos por estado,
      orden de aplicación, cuadrillas).

    Gateada por `operaciones.trabajo.editar` — el mismo permiso que ya edita
    un `Trabajo` puntual, sin permiso nuevo. Que la Orden de Trabajo siga
    siendo editable lo exige `Aplicacion/ActualizarOrdenTrabajo`
    (`PoliticaEdicionOrdenTrabajo`): con algún trabajo validado, `edit()`
    redirige a la ficha con el aviso — nunca llega a dibujar este formulario.
    `orden_id` y `nro_aplicacion` no se editan (invariante de la tarea 127).

    Estilos en resources/css/pages/ordenes-trabajo.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $parametrosAntiguos = old('parametros', [
        'humedad_min_pct' => $ordenTrabajo->humedad_min_pct,
        'viento_max_kmh' => $ordenTrabajo->viento_max_kmh,
        'temperatura_max_c' => $ordenTrabajo->temperatura_max_c,
        'humedad_max_pct' => $ordenTrabajo->humedad_max_pct,
        'altura_vuelo_m' => $ordenTrabajo->altura_vuelo_m,
        'velocidad_vuelo_kmh' => $ordenTrabajo->velocidad_vuelo_kmh,
        'ancho_pasada_m' => $ordenTrabajo->ancho_pasada_m,
        'ph_agua' => $ordenTrabajo->ph_agua,
        'ph_calda' => $ordenTrabajo->ph_calda,
        'litros_ha' => $ordenTrabajo->litros_ha,
        'kilos_ha' => $ordenTrabajo->kilos_ha,
        'calda_productos' => $ordenTrabajo->calda_productos ?? [],
    ]);
    $tituloPagina = __('operaciones.ordenes_trabajo.titulo_editar', ['id' => $ordenTrabajo->id]);
@endphp
<x-templates.panel-shell :title="$tituloPagina" :tema="$tema">
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
        :vista-actual="$tituloPagina"
    >
        <form
            method="POST"
            action="{{ route('panel.trabajos.update', $ordenTrabajo) }}"
            class="ag-ordenes-trabajo-form"
            novalidate
        >
            @csrf
            @method('PUT')

            <x-organisms.page-header :title="$tituloPagina" :subtitle="__('operaciones.ordenes_trabajo.editar_subtitulo')">
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.trabajos.show', $ordenTrabajo)" :label="__('operaciones.ordenes_trabajo.volver')" />
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

            @if ($exigeMotivo)
                <x-molecules.alert-strip variant="warning" icon="edit_note">
                    {{ __('operaciones.ordenes_trabajo.correccion_aviso') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.ordenes_trabajo.seccion_orden')">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes_trabajo.campo_orden') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('operaciones.ordenes_trabajo.detalle_orden', ['id' => $ordenTrabajo->orden_id, 'aplicacion' => $ordenTrabajo->nro_aplicacion]) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes.campo_tipo_insumo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $esLiquido ? __('operaciones.tipo_insumo.liquido') : __('operaciones.tipo_insumo.solido') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes.campo_categoria_insumo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $ordenTrabajo->orden?->categoriaInsumo?->nombre ?? '—' }}</p>
                    </div>
                </x-molecules.form-section>

                @include('operaciones::pages.ordenes-trabajo._indicaciones', [
                    'esLiquido' => $esLiquido,
                    'parametrosAntiguos' => $parametrosAntiguos,
                ])

                <x-molecules.form-section
                    accent="warning"
                    :title="__('operaciones.ordenes_trabajo.seccion_equipos')"
                    :count="trans_choice('operaciones.ordenes_trabajo.equipos_contador', count($equiposParaFormulario), ['cantidad' => count($equiposParaFormulario)])"
                >
                    <div class="ag-form-section__field--full">
                        <x-molecules.alert-strip variant="info" icon="info">
                            {{ __('operaciones.ordenes_trabajo.reparto_no_editable_aviso') }}
                        </x-molecules.alert-strip>
                    </div>

                    <div class="ag-form-section__field--full ag-ordenes-trabajo-form__equipos">
                        @foreach ($equiposParaFormulario as $equipo)
                            @include('operaciones::pages.ordenes-trabajo._equipo-condicion', [
                                'equipo' => $equipo,
                                'tarifasDisponibles' => $tarifasDisponibles,
                                'modalidadesPago' => $modalidadesPago,
                            ])
                        @endforeach
                    </div>
                </x-molecules.form-section>

                @if ($exigeMotivo)
                    <x-molecules.form-section :title="__('operaciones.ordenes_trabajo.campo_motivo_correccion')">
                        <div class="ag-form-section__field--full">
                            <x-atoms.textarea
                                name="motivo_correccion"
                                :label="__('operaciones.ordenes_trabajo.campo_motivo_correccion')"
                                :placeholder="__('operaciones.ordenes_trabajo.campo_motivo_correccion_placeholder')"
                                :value="old('motivo_correccion')"
                                :rows="3"
                                required
                                :error="$errors->first('motivo_correccion')"
                            />
                        </div>
                    </x-molecules.form-section>
                @endif

                <x-organisms.form-actions-bar :status="__('operaciones.ordenes_trabajo.estado_form_edicion')">
                    <x-slot:actions>
                        <x-molecules.boton-volver :href="route('panel.trabajos.show', $ordenTrabajo)" cancelar />
                        <x-atoms.button type="submit" variant="primary" icon="check">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>

                <x-slot:aside>
                    @foreach ($resumenRelacionado as $resumen)
                        @if ($resumen['tieneDatos'])
                            <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                                @if ($resumen['mostrarAccion'])
                                    <x-slot:action>
                                        <x-atoms.button :href="$resumen['accion']['href']" variant="outline" :icon="$resumen['accion']['icono']" block>
                                            {{ $resumen['accion']['label'] }}
                                        </x-atoms.button>
                                    </x-slot:action>
                                @endif
                            </x-molecules.summary-card>
                        @else
                            <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                                @if ($resumen['mostrarAccion'])
                                    <x-slot:action>
                                        <x-atoms.button :href="$resumen['accion']['href']" variant="outline" :icon="$resumen['accion']['icono']">
                                            {{ $resumen['accion']['label'] }}
                                        </x-atoms.button>
                                    </x-slot:action>
                                @endif
                            </x-molecules.empty-state>
                        @endif
                    @endforeach
                </x-slot:aside>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
