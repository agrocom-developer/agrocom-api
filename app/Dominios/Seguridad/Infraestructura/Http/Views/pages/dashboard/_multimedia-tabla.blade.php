{{--
    Parcial: vista tabla del tab Multimedia (Fase 8) — una fila por captura
    individual (no por sesión), con miniatura y enlace a la imagen original.

    Espera:
    - $sesiones (list): filas de DatosDemoCapturasRc::sesiones().
--}}
<div class="ag-table-scroll">
    <div class="ag-table ag-table--multimedia" role="table">
        <div class="ag-table__head" role="row">
            <span role="columnheader"></span>
            <span role="columnheader">{{ __('seguridad.dashboard.multimedia_col_fecha') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.multimedia_col_sesion') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.multimedia_col_piloto') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.multimedia_col_lote') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.multimedia_col_descripcion') }}</span>
            <span role="columnheader"></span>
        </div>
        @foreach ($sesiones as $sesion)
            @foreach ($sesion['capturas'] as $captura)
                <div class="ag-table__row" role="row">
                    <span role="cell">
                        <img
                            src="{{ asset('demo/capturas-rc/thumbs/'.$captura['archivo']) }}"
                            alt="{{ $captura['descripcion'] }}"
                            class="ag-table__thumb"
                            loading="lazy"
                        >
                    </span>
                    <span class="ag-table__mono" role="cell">{{ $sesion['fecha'] }}</span>
                    <span class="ag-table__strong" role="cell">{{ $sesion['sesion'] }}</span>
                    <span role="cell">{{ $sesion['piloto'] }}</span>
                    <span role="cell">{{ $sesion['lote'] }}</span>
                    <span role="cell">{{ $captura['descripcion'] }}</span>
                    <span role="cell">
                        <a href="{{ asset('demo/capturas-rc/'.$captura['archivo']) }}" target="_blank" rel="noopener" class="ag-dash__link">
                            {{ __('seguridad.dashboard.multimedia_ver') }}
                        </a>
                    </span>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
