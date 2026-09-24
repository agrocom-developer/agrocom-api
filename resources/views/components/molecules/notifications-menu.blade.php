{{--
    Molecule: notifications-menu (11/9/2026 — extraída de organisms/topbar)
    Campana con badge + popover de notificaciones. Se extrae a molecule
    porque el mismo bloque se necesita DOS veces (topbar de escritorio y
    mobile-topbar) — regla de gobernanza de la guía de pantalla: "un patrón
    que aparece dos veces ya no es markup, es un componente del catálogo".

    Antes, en mobile-topbar la campana era un `<span>` decorativo sin
    `data-bs-toggle`: no abría nada al tocarla. Con esta molecule, las dos
    campanas comparten el mismo dropdown de Bootstrap real.

    Tarea 141 (motor de notificaciones): cada aviso puede llevar `href` y,
    si nace del motor, `id`. Con `href` la fila es un enlace real (el destino
    lo resuelve el servidor contra el rol activo, ver `panel.notificaciones.
    abrir`); sin él sigue siendo texto plano, como antes. Las alertas técnicas
    de `ope_alertas` llevan `alerta_id` en vez de `id`: se atienden en su
    propia pantalla, pero cada cuenta tiene su estado de lectura y de limpieza
    sobre ellas (`panel.notificaciones.abrir-alerta`). Un aviso con `id` o con
    `alerta_id` se puede marcar leído, y por eso el pie «Marcar todas como
    leídas» solo aparece si hay alguno sin leer.

    «Limpiar» vacía la campana de la cuenta y aparece siempre que haya algo.
    Los dos formularios del pie llevan las alertas que esta campana mostraba
    (`alertas[]`): el servidor las revalida, pero sabe cuáles eran las que
    tenía delante quien apretó.

    Props:
    - notifications (list, default []): `{icon, title, time, unread, href?,
      id?, alerta_id?}` ya resueltos por el llamador.
    - triggerClass (string, default "ag-topbar__icon-btn"): la clase del
      botón disparador — el llamador la cambia para el estilo de mobile
      (`ag-mobile-topbar__bell-btn`, tokens constantes del riel).

    `class` en el componente (vía `$attributes`) va al `<div class="dropdown">`
    raíz — así el topbar de escritorio sigue pudiendo pedir
    `ag-topbar__notifications` sin que esta molecule necesite saberlo.

    El botón/badge lo instancia `resources/js/organisms/topbar.js`, que
    busca `[data-bs-toggle="dropdown"]` tanto dentro de `.ag-topbar` como de
    `.ag-mobile-topbar` (fix del 11/9/2026 — antes solo miraba `.ag-topbar`,
    así que en mobile el popover se abría recortado por el `overflow:
    hidden` de sus ancestros en vez de posicionarse `fixed`).
--}}
@props([
    'notifications' => [],
    'triggerClass' => 'ag-topbar__icon-btn',
])

@php
    $notificacionesSinLeer = collect($notifications)->filter(fn ($n) => (bool) data_get($n, 'unread', false))->count();
    $hayMarcablesSinLeer = collect($notifications)->contains(
        fn ($n) => (bool) data_get($n, 'unread', false)
            && (data_get($n, 'id') !== null || data_get($n, 'alerta_id') !== null),
    );
    $idsDeAlerta = collect($notifications)
        ->map(fn ($n) => data_get($n, 'alerta_id'))
        ->filter(fn ($id) => $id !== null)
        ->values();
@endphp

<div {{ $attributes->class(['dropdown']) }}>
    <button
        type="button"
        class="{{ $triggerClass }}"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="{{ __('ui.topbar.notifications') }}"
    >
        <x-atoms.icon name="notifications" size="sm" />
        @if ($notificacionesSinLeer > 0)
            <span class="ag-notification-badge" aria-hidden="true">{{ $notificacionesSinLeer > 9 ? '9+' : $notificacionesSinLeer }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end ag-notifications-popover">
        <h2 class="ag-popover__title">{{ __('ui.topbar.notifications') }}</h2>

        @if (count($notifications) === 0)
            <p class="ag-notifications-popover__empty">{{ __('ui.topbar.no_notifications') }}</p>
        @else
            <ul class="ag-notification-list">
                @foreach ($notifications as $notification)
                    {{-- `<a>` sin `href` es un marcador de posición válido: la
                         fila sin destino conserva el mismo marcado y estilo. --}}
                    <li>
                        <a
                            class="ag-notification-item {{ data_get($notification, 'unread') ? 'is-unread' : '' }}"
                            @if (data_get($notification, 'href')) href="{{ data_get($notification, 'href') }}" @endif
                        >
                            <span class="ag-notification-item__icon" aria-hidden="true">
                                <x-atoms.icon :name="data_get($notification, 'icon', 'notifications')" size="sm" />
                            </span>
                            <span class="ag-notification-item__body">
                                <span class="ag-notification-item__title">{{ data_get($notification, 'title') }}</span>
                                <span class="ag-notification-item__time">{{ data_get($notification, 'time') }}</span>
                            </span>
                            @if (data_get($notification, 'unread'))
                                <span class="ag-notification-item__dot" aria-hidden="true"></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="ag-notifications-popover__footer">
                @if ($hayMarcablesSinLeer)
                    <form method="POST" action="{{ route('panel.notificaciones.marcar-todas') }}">
                        @csrf
                        @foreach ($idsDeAlerta as $idAlerta)
                            <input type="hidden" name="alertas[]" value="{{ $idAlerta }}">
                        @endforeach
                        <button type="submit" class="ag-notifications-popover__mark-all">{{ __('ui.topbar.mark_all_read') }}</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('panel.notificaciones.limpiar') }}" class="ag-notifications-popover__limpiar">
                    @csrf
                    @foreach ($idsDeAlerta as $idAlerta)
                        <input type="hidden" name="alertas[]" value="{{ $idAlerta }}">
                    @endforeach
                    <button
                        type="submit"
                        class="ag-notifications-popover__mark-all"
                        aria-label="{{ __('ui.topbar.clear_all_label') }}"
                    >{{ __('ui.topbar.clear_all') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>
