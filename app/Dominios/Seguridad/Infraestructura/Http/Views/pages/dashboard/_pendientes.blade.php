{{-- Cola de pendientes del tablero. Variables: $pendientes, $totalPendientes. --}}
@if ($pendientes !== [])
    <section class="ag-card ag-card--padded">
        <header class="ag-card__head">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.pendientes_titulo') }}</h2>
        </header>

        @if ($totalPendientes === 0)
            <p class="ag-tablero__ayuda">{{ __('seguridad.dashboard.pendientes_vacio') }}</p>
        @else
            <ul class="ag-tablero__pendientes">
                @foreach ($pendientes as $pendiente)
                    @continue($pendiente['cantidad'] === 0)
                    <li class="ag-tablero__pendiente">
                        <span class="ag-tablero__pendiente-cifra">{{ $pendiente['cantidad'] }}</span>
                        <span class="ag-tablero__pendiente-texto">{{ __('seguridad.dashboard.pendiente.'.$pendiente['clave']) }}</span>
                        <a class="ag-dash__link" href="{{ route($pendiente['ruta']) }}">{{ __('seguridad.dashboard.pendientes_ver') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
