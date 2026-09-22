{{--
    Parcial: liquidación del mes de la persona autenticada — lo devengado, lo
    ya cobrado a cuenta (anticipos) y el saldo entre ambos.

    Las dos mitades van en la misma tarjeta a propósito: el devengado solo no
    es lo que la persona va a recibir, y mostrarlo suelto invita a un reclamo
    que el saldo responde de antemano.

    Todos los montos llegan como string decimal desde `BigDecimal`
    (invariante 6); acá solo se les da formato de miles. Son los mismos
    números del recibo de planilla, así que no pueden redondear distinto.

    Espera: $liquidacion = {devengos, total, periodo, anticipos: {anticipos, total, saldo}}.
--}}
@php($anticipos = $liquidacion['anticipos'])

<section>
    <div class="ag-card">
        <div class="ag-card__head">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.liquidacion_titulo', ['periodo' => $liquidacion['periodo']]) }}</h2>
        </div>

        <div class="ag-liquidacion__resumen">
            <div class="ag-liquidacion__cifra">
                <dt>{{ __('seguridad.dashboard.liquidacion_devengado') }}</dt>
                <dd>{{ __('seguridad.dashboard.liquidacion_total', ['monto' => number_format((float) $liquidacion['total'], 2, ',', '.')]) }}</dd>
            </div>
            <div class="ag-liquidacion__cifra">
                <dt>{{ __('seguridad.dashboard.liquidacion_anticipos') }}</dt>
                <dd>{{ __('seguridad.dashboard.liquidacion_total', ['monto' => number_format((float) $anticipos['total'], 2, ',', '.')]) }}</dd>
            </div>
            <div class="ag-liquidacion__cifra ag-liquidacion__cifra--saldo">
                <dt>{{ __('seguridad.dashboard.liquidacion_saldo') }}</dt>
                <dd>{{ __('seguridad.dashboard.liquidacion_total', ['monto' => number_format((float) $anticipos['saldo'], 2, ',', '.')]) }}</dd>
            </div>
        </div>

        @if ($liquidacion['devengos'] !== [])
            <div class="ag-table ag-table--liquidacion" role="table">
                <div class="ag-table__head" role="row">
                    <span role="columnheader">{{ __('seguridad.dashboard.liquidacion_col_fecha') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_ha') }}</span>
                    <span role="columnheader">{{ __('seguridad.dashboard.liquidacion_col_modalidad') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.liquidacion_col_tarifa') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.liquidacion_col_monto') }}</span>
                </div>
                @foreach ($liquidacion['devengos'] as $devengo)
                    <div class="ag-table__row" role="row">
                        <span class="ag-table__mono" role="cell">{{ \Illuminate\Support\Carbon::parse($devengo->fecha)->format('d/m/Y') }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $devengo->hectareas, 2, ',', '.') }}</span>
                        <span role="cell">{{ $devengo->absorbido ? __('seguridad.dashboard.liquidacion_absorbido') : $devengo->modalidad }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $devengo->tarifa, 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__ha ag-table__strong">{{ $devengo->absorbido ? '—' : number_format((float) $devengo->monto, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($anticipos['anticipos'] !== [])
            <div class="ag-liquidacion__anticipos">
                <h3 class="ag-card__subtitle">{{ __('seguridad.dashboard.liquidacion_anticipos_detalle') }}</h3>
                @foreach ($anticipos['anticipos'] as $anticipo)
                    <div class="ag-liquidacion__anticipo">
                        <span class="ag-table__mono">{{ \Illuminate\Support\Carbon::parse($anticipo->fecha)->format('d/m/Y') }}</span>
                        <span>{{ $anticipo->motivo ?? '—' }}</span>
                        <span class="ag-table__ha ag-table__strong">{{ number_format((float) $anticipo->monto, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
