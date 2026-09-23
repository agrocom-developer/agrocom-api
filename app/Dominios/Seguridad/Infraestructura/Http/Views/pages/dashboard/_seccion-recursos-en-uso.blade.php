{{--
    Parcial: recursos en uso ahora (tab "Recursos" del jefe de campo, tarea
    138). Una tarjeta por cuadrilla con algún trabajo abierto: cuánto tiene en
    marcha, quiénes la integran hoy y qué lleva —dron, vehículo, generador,
    baterías— con el estado de cada pieza.

    El estado se dice con punto + texto (no con un chip relleno por pieza: una
    cuadrilla lleva seis y seis chips compiten con lo que sí importa). Solo una
    pieza que NO está operativa gana un badge, para que se vea de un vistazo.

    Solo lectura: el nombre de la cuadrilla y «Ver cuadrillas» llevan a su
    pantalla, y ambos existen solo si el rol tiene el permiso de esa pantalla
    (el mismo que gatea esta sección).

    Espera: $recursos = {cuadrillas: list<{equipoTrabajoId, equipo,
    trabajosAbiertos, hectareasDeclaradas, integrantes: list<{nombre, rol}>,
    equipamiento: list<{tipo, tipoEtiqueta, identificador, estado,
    etiquetaEstado, tono, operativo}>, fueraDeServicio}>, recursos: int,
    fueraDeServicio: int}.
--}}
<section>
    <x-molecules.section-head
        :title="__('seguridad.dashboard.seccion_recursos_en_uso')"
        :count="trans_choice('seguridad.dashboard.recursos_en_uso_total', $recursos['recursos'], ['cantidad' => $recursos['recursos']])"
    >
        <x-slot:actions>
            <a class="ag-dash__link" href="{{ route('panel.cuadrillas.index') }}">{{ __('seguridad.dashboard.recursos_en_uso_ver') }}</a>
        </x-slot:actions>
    </x-molecules.section-head>

    <div class="ag-dash__cuadrillas">
        @foreach ($recursos['cuadrillas'] as $cuadrilla)
            <article class="ag-card ag-card--padded ag-dash__cuadrilla">
                <div class="ag-card__head ag-card__head--flush ag-dash__cuadrilla-cabecera">
                    <h3 class="ag-card__title">
                        <a class="ag-dash__link ag-dash__cuadrilla-nombre" href="{{ route('panel.cuadrillas.show', $cuadrilla['equipoTrabajoId']) }}">{{ $cuadrilla['equipo'] }}</a>
                    </h3>
                    <span class="ag-dash__mono-note">
                        {{ trans_choice('seguridad.dashboard.recursos_en_uso_carga', $cuadrilla['trabajosAbiertos'], [
                            'cantidad' => $cuadrilla['trabajosAbiertos'],
                            'hectareas' => number_format((float) $cuadrilla['hectareasDeclaradas'], 2, ',', '.'),
                        ]) }}
                    </span>
                </div>

                <p class="ag-dash__cuadrilla-integrantes">
                    <span class="ag-dash__cuadrilla-rotulo">{{ __('seguridad.dashboard.recursos_en_uso_integrantes') }}</span>
                    @forelse ($cuadrilla['integrantes'] as $integrante)
                        <span>{{ $integrante['nombre'] }} <span class="ag-table__sub ag-dash__cuadrilla-rol">{{ $integrante['rol'] }}</span></span>
                    @empty
                        <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.recursos_en_uso_sin_integrantes') }}</span>
                    @endforelse
                </p>

                @if ($cuadrilla['equipamiento'] === [])
                    <p class="ag-dash__mono-note">{{ __('seguridad.dashboard.recursos_en_uso_sin_equipamiento') }}</p>
                @else
                    <ul class="ag-dash__equipamiento">
                        @foreach ($cuadrilla['equipamiento'] as $recurso)
                            <li class="ag-dash__equipamiento-item">
                                <span class="ag-dash__equipamiento-tipo">{{ $recurso['tipoEtiqueta'] }}</span>
                                <span class="ag-table__strong ag-dash__equipamiento-id">{{ $recurso['identificador'] }}</span>
                                @if ($recurso['operativo'])
                                    <span class="ag-table__estado">
                                        <span class="ag-table__dot ag-table__dot--{{ $recurso['tono'] }}" aria-hidden="true"></span>{{ $recurso['etiquetaEstado'] }}
                                    </span>
                                @else
                                    <x-atoms.badge :variant="$recurso['tono']">{{ $recurso['etiquetaEstado'] }}</x-atoms.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
</section>
