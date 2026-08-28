{{--
    Parcial: panel lateral de detalle de una sesión (Fase 6 — drill-down del
    tab Sesiones). Offcanvas NATIVO de Bootstrap 5.3 (`offcanvas-end`, JS ya
    cargado) — cero JS propio, mismo criterio que organisms/module-drawer.
    Cumple la promesa de `seguridad.dashboard.sesiones_nota`: orden, mezcla
    y quién la preparó, condiciones, pausas y la captura del RC.

    Espera:
    - $sesion (array): una fila de DatosDemoPanel::sesiones() (con
      `rcEstado`/`detalle`).
    - $detalleId (string): id único del offcanvas, ancla del trigger en
      _tabla-sesiones.blade.php.
--}}
<div
    class="ag-session-detail offcanvas offcanvas-end"
    tabindex="-1"
    id="{{ $detalleId }}"
    aria-labelledby="{{ $detalleId }}-label"
>
    <div class="ag-session-detail__header">
        <div>
            <h2 class="ag-session-detail__title" id="{{ $detalleId }}-label">{{ __('seguridad.dashboard.detalle_titulo') }}</h2>
            <p class="ag-session-detail__subtitle">{{ $sesion['lote'] }} · {{ $sesion['hora'] }}</p>
        </div>
        <button
            type="button"
            class="ag-session-detail__close"
            data-bs-dismiss="offcanvas"
            aria-label="{{ __('seguridad.dashboard.detalle_cerrar') }}"
        >
            <x-atoms.icon name="close" size="sm" />
        </button>
    </div>

    <div class="ag-session-detail__body">
        <x-atoms.badge :variant="$sesion['variante']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>

        <dl class="ag-session-detail__list">
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.col_piloto') }}</dt>
                <dd>{{ $sesion['piloto'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.col_dron') }}</dt>
                <dd>{{ $sesion['dron'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.col_ha') }}</dt>
                <dd>{{ $sesion['ha'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.detalle_orden') }}</dt>
                <dd>{{ $sesion['detalle']['orden'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.detalle_mezcla') }}</dt>
                <dd>{{ $sesion['detalle']['mezcla'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.detalle_preparado_por') }}</dt>
                <dd>{{ $sesion['detalle']['preparadoPor'] }}</dd>
            </div>
            <div class="ag-session-detail__row">
                <dt>{{ __('seguridad.dashboard.detalle_condiciones') }}</dt>
                <dd>{{ $sesion['detalle']['condiciones'] }}</dd>
            </div>
        </dl>

        <div class="ag-session-detail__section">
            <h3 class="ag-session-detail__section-title">{{ __('seguridad.dashboard.detalle_pausas') }}</h3>
            @if (count($sesion['detalle']['pausas']) === 0)
                <p class="ag-session-detail__empty">{{ __('seguridad.dashboard.detalle_sin_pausas') }}</p>
            @else
                <ul class="ag-session-detail__pausas">
                    @foreach ($sesion['detalle']['pausas'] as $pausa)
                        <li>
                            <span>{{ $pausa['causa'] }}</span>
                            <span class="ag-session-detail__pausas-duracion">{{ $pausa['duracion'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="ag-session-detail__section">
            <h3 class="ag-session-detail__section-title">{{ __('seguridad.dashboard.detalle_rc') }}</h3>
            <p class="ag-session-detail__rc ag-session-detail__rc--{{ $sesion['rcEstado'] }}">
                <x-atoms.icon name="photo_camera" size="sm" />
                {{ __('seguridad.dashboard.detalle_rc_'.$sesion['rcEstado']) }}
            </p>
        </div>
    </div>
</div>
