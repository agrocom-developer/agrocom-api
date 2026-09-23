{{--
    Parcial: los drones que la persona operó este mes, del más usado al menos.

    Responde "de qué equipo respondo" sin que exista una tabla de asignación:
    se deriva de las sesiones, que es el único registro que liga persona y
    dron. Ver `LecturaPanelOperaciones::equiposDePersonaDelMes()`.

    Espera: $equipos (list<EquipoPersonaPanel>).
--}}
@php
    $comoDuracion = fn (int $minutos) => sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60);
@endphp

<section>
    <div class="ag-card">
        <div class="ag-card__head">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.mis_equipos_titulo') }}</h2>
        </div>
        <div class="ag-table-scroll">
            <div class="ag-table ag-table--equipos" role="table">
                <div class="ag-table__head" role="row">
                    <span role="columnheader">{{ __('seguridad.dashboard.col_dron') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.equipos_col_sesiones') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_vuelo') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_ha') }}</span>
                    <span role="columnheader">{{ __('seguridad.dashboard.equipos_col_ultimo') }}</span>
                </div>
                @foreach ($equipos as $equipo)
                    <div class="ag-table__row" role="row">
                        <span class="ag-table__strong" role="cell">
                            {{ $equipo->identificador }}
                            @if ($equipo->modelo)
                                <span class="ag-table__sub">{{ $equipo->modelo }}</span>
                            @endif
                        </span>
                        <span role="cell" class="ag-table__ha">{{ $equipo->sesiones }}</span>
                        <span role="cell" class="ag-table__ha ag-table__mono">{{ $comoDuracion($equipo->minutosVuelo) }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $equipo->hectareas, 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__mono">
                            {{ $equipo->ultimoVuelo ? \Illuminate\Support\Carbon::parse($equipo->ultimoVuelo)->format('d/m H:i') : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
