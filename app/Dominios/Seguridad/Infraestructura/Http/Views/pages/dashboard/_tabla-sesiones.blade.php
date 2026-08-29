{{--
    Parcial: tabla de sesiones (quinta vuelta — maquetas 4a/5a; sexta vuelta
    parte 2 — Fase 6: columna RC + drill-down opcional del tab Sesiones).
    Dos representaciones del MISMO dato, conmutadas por breakpoint en
    pages/dashboard.css:
    - `.ag-table`: la grilla de escritorio (≥1200) con columnas
      HORA/LOTE/PILOTO/DRON/HA/ESTADO(/RC).
    - `.ag-session-list`: la lista de dos líneas de tablet (<1200) — hora +
      lote arriba, piloto · dron · ha abajo, chip de estado a la derecha.

    Espera:
    - $sesiones (list): filas de DatosDemoPanel::sesiones().
    - $limiteLista (int|null, opcional): tope de filas SOLO para la lista de
      tablet (la maqueta 5a muestra 4).
    - $conRc (bool, opcional, default false): agrega la columna RC y una
      columna de acción con el drill-down al panel lateral (`rcEstado` +
      `detalle` de DatosDemoPanel::sesiones()) — SOLO el tab Sesiones lo
      activa; la tabla condensada del tab Resumen queda igual que siempre.

    Auditoría Fase 8 (validador, 28/8/2026): la fila entera NO es
    interactiva (queda `<div role="row">` estático, patrón ARIA "table"
    válido) — el drill-down vive en un `<button>` real dentro de su propia
    celda (`role="cell"`), no en la fila. Antes la fila completa se
    convertía en `<button role="row">`, que rompe el patrón "table" de
    WAI-ARIA (esperaría "grid" con roving tabindex si la fila fuera
    interactiva) sin llegar a implementarlo.
--}}
@php
    $limiteLista = $limiteLista ?? null;
    $conRc = $conRc ?? false;

    $varianteRc = [
        'capturado' => 'success',
        'sin_evidencia' => 'warning',
        'no_aplica' => 'neutral',
    ];
@endphp

<div class="ag-table {{ $conRc ? 'ag-table--detallado' : '' }}" role="table">
    <div class="ag-table__head" role="row">
        <span role="columnheader">{{ __('seguridad.dashboard.col_hora') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_lote') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_piloto') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_dron') }}</span>
        <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_ha') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_estado') }}</span>
        @if ($conRc)
            <span role="columnheader">{{ __('seguridad.dashboard.col_rc') }}</span>
            {{-- Columna de acción sin label visible (icon-only, como es
                 convención) — el botón de cada fila ya lleva su propio
                 aria-label. --}}
            <span role="columnheader"></span>
        @endif
    </div>
    @foreach ($sesiones as $index => $sesion)
        @php($detalleId = "ag-sesion-detalle-{$index}")
        <div class="ag-table__row" role="row">
            <span class="ag-table__mono" role="cell">{{ $sesion['hora'] }}</span>
            <span class="ag-table__strong" role="cell">{{ $sesion['lote'] }}</span>
            <span role="cell">{{ $sesion['piloto'] }}</span>
            <span class="ag-table__mono" role="cell">{{ $sesion['dron'] }}</span>
            <span role="cell" class="ag-table__ha">{{ $sesion['ha'] }}</span>
            <span role="cell" class="ag-table__estado">
                <span class="ag-table__dot ag-table__dot--{{ $sesion['variante'] }}" aria-hidden="true"></span>
                {{ __('operaciones.sesion.estado.'.$sesion['estado']) }}
            </span>
            @if ($conRc)
                <span role="cell">
                    <x-atoms.badge :variant="$varianteRc[$sesion['rcEstado']] ?? 'neutral'">{{ __('operaciones.sesion.rc_estado.'.$sesion['rcEstado']) }}</x-atoms.badge>
                </span>
                <span role="cell">
                    <button
                        type="button"
                        class="ag-table__row-action"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#{{ $detalleId }}"
                        aria-controls="{{ $detalleId }}"
                        aria-label="{{ __('seguridad.dashboard.detalle_ver') }}"
                    >
                        <x-atoms.icon name="chevron_right" size="sm" />
                    </button>
                </span>
            @endif
        </div>
    @endforeach
</div>

<div class="ag-session-list">
    @foreach (($limiteLista !== null ? array_slice($sesiones, 0, $limiteLista) : $sesiones) as $sesion)
        <div class="ag-session-list__row">
            <span class="ag-session-list__hora">{{ $sesion['hora'] }}</span>
            <span class="ag-session-list__body">
                <span class="ag-session-list__lote">{{ $sesion['lote'] }}</span>
                <span class="ag-session-list__meta">{{ $sesion['piloto'] }} · {{ $sesion['dron'] }} · {{ $sesion['ha'] }}</span>
            </span>
            <x-atoms.badge :variant="$sesion['variante']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
        </div>
    @endforeach
</div>

@if ($conRc)
    @foreach ($sesiones as $index => $sesion)
        @include('seguridad::pages.dashboard._detalle-sesion', ['sesion' => $sesion, 'detalleId' => "ag-sesion-detalle-{$index}"])
    @endforeach
@endif
