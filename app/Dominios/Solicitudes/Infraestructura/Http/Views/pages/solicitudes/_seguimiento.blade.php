{{--
    Parcial: seguimiento del servicio — avance que marcó el piloto, fotos y
    última ubicación. La ubicación existe solo mientras la solicitud está en
    ejecución y se elimina al finalizar o cancelar (ADR 0040): si no hay fila,
    el panel no muestra nada que inventar.

    Espera: $avances, $fotos, $ubicacion (ver LecturaNegociacionParaPanel) y
    $fecha (Closure que formatea un instante en la zona horaria de quien mira).
--}}
@if ($avances === [] && $fotos === [] && $ubicacion === null)
    <p class="ag-solicitudes__vacio">{{ __('solicitudes.panel.seg_vacio') }}</p>
@else
    @if ($ubicacion !== null)
        <p class="ag-solicitudes__ubicacion">
            <x-atoms.icon name="location_on" size="sm" />
            <span>{{ __('solicitudes.panel.seg_ubicacion', ['cuando' => $fecha($ubicacion['capturado_en'])]) }}</span>
            <a class="ag-dash__link" href="https://www.google.com/maps?q={{ $ubicacion['latitud'] }},{{ $ubicacion['longitud'] }}" target="_blank" rel="noopener noreferrer">{{ __('solicitudes.panel.seg_ver_mapa') }}</a>
        </p>
    @endif

    @if ($avances !== [])
        <div class="ag-tablero__scroll">
            <table class="ag-tablero__tabla">
                <thead>
                    <tr>
                        <th scope="col">{{ __('solicitudes.panel.seg_col_momento') }}</th>
                        <th scope="col">{{ __('solicitudes.panel.seg_col_tipo') }}</th>
                        <th scope="col" class="ag-tablero__num">{{ __('solicitudes.panel.seg_col_hectareas') }}</th>
                        <th scope="col" class="ag-tablero__num">{{ __('solicitudes.panel.seg_col_porcentaje') }}</th>
                        <th scope="col">{{ __('solicitudes.panel.seg_col_nota') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($avances as $avance)
                        <tr>
                            <th scope="row">{{ $fecha($avance['registrado_en']) }}</th>
                            <td>{{ __('solicitudes.panel.seg_tipo.'.$avance['tipo']) }}</td>
                            <td class="ag-tablero__num">{{ $avance['hectareas_ejecutadas'] === null ? '—' : number_format((float) $avance['hectareas_ejecutadas'], 2, ',', '.') }}</td>
                            <td class="ag-tablero__num">{{ $avance['porcentaje'] === null ? '—' : number_format((float) $avance['porcentaje'], 0, ',', '.').' %' }}</td>
                            <td>{{ $avance['nota'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($fotos !== [])
        <ul class="ag-solicitudes__fotos">
            @foreach ($fotos as $foto)
                <li class="ag-solicitudes__foto">
                    <a href="{{ route('panel.evidencias.archivo', $foto['id']) }}" target="_blank" rel="noopener">
                        <img
                            src="{{ route('panel.evidencias.archivo', $foto['id']) }}"
                            alt="{{ __('solicitudes.panel.seg_foto_alt', ['tipo' => __('solicitudes.panel.seg_foto_tipo.'.$foto['tipo']), 'cuando' => $fecha($foto['subida_en'])]) }}"
                            loading="lazy"
                        >
                    </a>
                    <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.seg_foto_tipo.'.$foto['tipo']) }} · {{ $fecha($foto['subida_en']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endif
