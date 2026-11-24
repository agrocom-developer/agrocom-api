{{--
    Parcial: la ejecución de una solicitud — lo que el piloto registró (inicio,
    avances y cierre), las fotos que subió y la última ubicación de su app. La
    ubicación solo existe mientras el servicio está en ejecución y se elimina al
    finalizar o cancelar. Las fotos se piden una por una a una ruta del panel
    (disco privado), nunca por una URL pública.

    Espera: $solicitud, $avances (list), $evidencias (list), $ubicacion (array|null).
--}}
@php
    $dinero = fn (?string $n): string => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
@endphp

<div class="ag-solicitudes__ejecucion">
    <section aria-labelledby="ejecucion-avances">
        <h3 class="ag-solicitudes__nombre" id="ejecucion-avances">{{ __('solicitudes.panel.avances_titulo') }}</h3>
        @if (count($avances) === 0)
            <p class="ag-solicitudes__sub">{{ __('solicitudes.panel.avances_vacio') }}</p>
        @else
            <x-molecules.index-table columns="minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 1.6fr) minmax(0, 1fr)">
                <x-slot:head>
                    <span role="columnheader">{{ __('solicitudes.panel.col_registro') }}</span>
                    <span role="columnheader">{{ __('solicitudes.panel.col_hectareas') }}</span>
                    <span role="columnheader">{{ __('solicitudes.panel.col_porcentaje') }}</span>
                    <span role="columnheader">{{ __('solicitudes.panel.col_nota') }}</span>
                    <span role="columnheader">{{ __('solicitudes.panel.col_fecha') }}</span>
                </x-slot:head>
                @foreach ($avances as $avance)
                    <div class="ag-index-table__row" role="row">
                        <span role="cell">{{ __('solicitudes.panel.registro.'.$avance['tipo']) }}</span>
                        <span role="cell" class="ag-solicitudes__cifra">{{ $avance['hectareas'] === null ? '—' : $dinero($avance['hectareas']) }}</span>
                        <span role="cell" class="ag-solicitudes__cifra">{{ $avance['porcentaje'] === null ? '—' : number_format((float) $avance['porcentaje'], 0, ',', '.').' %' }}</span>
                        <span role="cell">
                            {{ $avance['nota'] ?? '—' }}
                            @if ($avance['motivo_correccion'])
                                <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.correccion', ['motivo' => $avance['motivo_correccion']]) }}</span>
                            @endif
                        </span>
                        <span role="cell">{{ $avance['registrado_en'] }}</span>
                    </div>
                @endforeach
            </x-molecules.index-table>
        @endif
    </section>

    <section aria-labelledby="ejecucion-fotos">
        <h3 class="ag-solicitudes__nombre" id="ejecucion-fotos">{{ __('solicitudes.panel.fotos_titulo') }}</h3>
        @if (count($evidencias) === 0)
            <p class="ag-solicitudes__sub">{{ __('solicitudes.panel.fotos_vacio') }}</p>
        @else
            <ul class="ag-solicitudes__fotos">
                @foreach ($evidencias as $foto)
                    <li>
                        <a href="{{ route('panel.solicitudes.evidencia', [$solicitud, $foto['id']]) }}" target="_blank" rel="noopener">
                            <img
                                src="{{ route('panel.solicitudes.evidencia', [$solicitud, $foto['id']]) }}"
                                alt="{{ __('solicitudes.panel.foto_alt', ['tipo' => __('solicitudes.panel.foto_tipo.'.$foto['tipo']), 'numero' => $loop->iteration]) }}"
                                loading="lazy"
                            >
                        </a>
                        <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.foto_tipo.'.$foto['tipo']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="ejecucion-ubicacion">
        <h3 class="ag-solicitudes__nombre" id="ejecucion-ubicacion">{{ __('solicitudes.panel.ubicacion_titulo') }}</h3>
        @if ($ubicacion === null)
            <p class="ag-solicitudes__sub">{{ __('solicitudes.panel.ubicacion_vacia') }}</p>
        @else
            <p class="ag-solicitudes__cifra">{{ $ubicacion['latitud'] }}, {{ $ubicacion['longitud'] }}</p>
            <p class="ag-solicitudes__sub">
                {{ __('solicitudes.panel.ubicacion_capturada', ['fecha' => $ubicacion['capturado_en']]) }}
                @if ($ubicacion['precision_m'] !== null)
                    · {{ __('solicitudes.panel.ubicacion_precision', ['metros' => number_format((float) $ubicacion['precision_m'], 0, ',', '.')]) }}
                @endif
            </p>
            <a class="ag-dash__link" href="https://www.openstreetmap.org/?mlat={{ $ubicacion['latitud'] }}&amp;mlon={{ $ubicacion['longitud'] }}#map=16/{{ $ubicacion['latitud'] }}/{{ $ubicacion['longitud'] }}" target="_blank" rel="noopener">{{ __('solicitudes.panel.ubicacion_mapa') }}</a>
        @endif
    </section>
</div>
