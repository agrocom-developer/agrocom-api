{{--
    Parcial: la negociación de una solicitud, ronda por ronda (ver
    `docs/especificacion/negociacion-contrato.md` §2). Una tabla por proveedor
    con cada oferta y contraoferta en orden: quién la hizo, a qué precio por
    hectárea, cuánto suma y cómo terminó. El panel solo consulta: ofertar,
    contraofertar, aceptar y rechazar son de las partes, desde la app.

    Espera: $negociaciones (list<array{proveedor_id, proveedor, rondas}>),
    $puedeVerProveedor (bool), $solicitud (SolicitudServicio).
--}}
@php
    $tonos = ['vigente' => 'info', 'aceptada' => 'success', 'rechazada' => 'danger', 'vencida' => 'warning', 'retirada' => 'neutral', 'reemplazada' => 'neutral', 'cerrada' => 'neutral'];
    $dinero = fn (?string $n): string => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
@endphp

@if (count($negociaciones) === 0)
    <x-molecules.empty-state
        icon="handshake"
        :title="__('solicitudes.panel.negociacion_vacia_titulo')"
        :detail="__('solicitudes.panel.negociacion_vacia_detalle')"
    />
@else
    <div class="ag-solicitudes__negociaciones">
        @foreach ($negociaciones as $negociacion)
            <section aria-label="{{ $negociacion['proveedor'] }}">
                <h3 class="ag-solicitudes__nombre">
                    @if ($puedeVerProveedor)
                        <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $negociacion['proveedor_id']) }}">{{ $negociacion['proveedor'] }}</a>
                    @else
                        {{ $negociacion['proveedor'] }}
                    @endif
                    @if ($solicitud->proveedor_id === $negociacion['proveedor_id'])
                        <x-atoms.badge variant="success">{{ __('solicitudes.panel.adjudicado') }}</x-atoms.badge>
                    @endif
                </h3>
                <x-molecules.index-table columns="4rem minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr)">
                    <x-slot:head>
                        <span role="columnheader">{{ __('solicitudes.panel.col_ronda') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_autor') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_precio_ha') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_monto') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_resultado') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_plazo') }}</span>
                    </x-slot:head>
                    @foreach ($negociacion['rondas'] as $ronda)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-solicitudes__cifra">{{ $ronda['numero'] }}</span>
                            <span role="cell">
                                {{ __('solicitudes.panel.autor.'.$ronda['autor']) }}
                                @if ($ronda['tipo'] === 'acepta_propuesta')
                                    <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.acepta_propuesta') }}</span>
                                @endif
                                @if ($ronda['es_definitiva'])
                                    <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.definitiva') }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $dinero($ronda['precio_ha']) }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $dinero($ronda['monto_estimado']) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonos[$ronda['estado']] ?? 'neutral'">{{ __('solicitudes.panel.oferta_estado.'.$ronda['estado']) }}</x-atoms.badge>
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__sub">{{ $ronda['respondida_en'] !== null ? __('solicitudes.panel.respondida', ['fecha' => $ronda['respondida_en']]) : __('solicitudes.panel.vence', ['fecha' => $ronda['vence_en']]) }}</span>
                                @if ($ronda['nota'])
                                    <span class="ag-solicitudes__sub">{{ $ronda['nota'] }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            </section>
        @endforeach
    </div>
@endif
