{{--
    Parcial: órdenes de aplicación por estado (tab "Estados de las
    aplicaciones" del encargado de operaciones, tarea 136). Donut a la
    izquierda y, a la derecha, cada estado con su cifra: el donut da la
    proporción, la lista da el número exacto (incluidos los estados en cero,
    que en el donut no se ven).

    NO es la distribución de SESIONES de vuelo (`_seccion-distribucion`):
    son las órdenes, con su propia máquina de estados (ADR 0022).

    El mapeo tono → token vive acá y no en el JS porque `apex-chart` recibe
    NOMBRES de token, nunca colores (invariante 11).

    Espera: $ordenes = {total: int, estados: list<{estado, tono, valor}>}.
--}}
@php
    $tokenPorTono = [
        'success' => '--ag-color-success',
        'warning' => '--ag-color-warning',
        'info' => '--ag-color-info',
        'danger' => '--ag-color-danger',
        'neutral' => '--ag-color-text-faint',
    ];
@endphp

<section>
    <div class="ag-card ag-card--padded">
        <div class="ag-card__head ag-card__head--flush">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.seccion_ordenes_estado') }}</h2>
            <a class="ag-dash__link" href="{{ route('panel.ordenes.index') }}">
                {{ __('seguridad.dashboard.ordenes_estado_ver') }}
            </a>
        </div>

        <div class="ag-dash__estados">
            <x-molecules.apex-chart
                type="donut"
                :series="array_column($ordenes['estados'], 'valor')"
                :labels="array_map(fn ($e) => __('operaciones.estado.'.$e['estado']), $ordenes['estados'])"
                :color-tokens="array_map(fn ($e) => $tokenPorTono[$e['tono']] ?? $tokenPorTono['neutral'], $ordenes['estados'])"
                :height="320"
            />

            <ul class="ag-dash__estados-lista">
                @foreach ($ordenes['estados'] as $estado)
                    <li class="ag-dash__estados-item">
                        <span class="ag-dash__estados-punto ag-dash__estados-punto--{{ $estado['tono'] }}" aria-hidden="true"></span>
                        <span class="ag-dash__estados-nombre">{{ __('operaciones.estado.'.$estado['estado']) }}</span>
                        <span class="ag-dash__estados-valor">{{ $estado['valor'] }}</span>
                    </li>
                @endforeach
                <li class="ag-dash__estados-item ag-dash__estados-item--total">
                    <span class="ag-dash__estados-nombre">{{ __('seguridad.dashboard.ordenes_estado_total') }}</span>
                    <span class="ag-dash__estados-valor">{{ $ordenes['total'] }}</span>
                </li>
            </ul>
        </div>
    </div>
</section>
