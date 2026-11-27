{{--
    Molecule: user-menu (11/9/2026 — extraída de organisms/topbar)
    Avatar + popover con "Mi perfil"/"Cambiar de rol"/"Cerrar sesión". Se
    extrae a molecule por la misma razón que `molecules/notifications-menu`:
    el mismo bloque hace falta en topbar (escritorio) y mobile-topbar.

    Antes, en mobile-topbar el avatar era un `<span>` decorativo sin
    `data-bs-toggle`: no había forma de llegar a "Mi perfil" ni de cerrar
    sesión desde el celular — bug real, no solo estético (reportado
    11/9/2026).

    Props:
    - userName (nullable string). Sin esto, el componente no renderiza nada
      (mismo criterio que antes en topbar.blade.php).
    - activeRoleLabel (nullable string).
    - cambiarRolHref (nullable string): si no se pasa, ese ítem no aparece
      — en mobile no se pasa a propósito, porque `organisms/module-drawer`
      ya tiene su propio link de "Cambiar de rol" en el pie; duplicarlo acá
      sería un segundo camino a lo mismo.
    - compact (bool, default false): true en mobile — el disparador es SOLO
      el círculo de iniciales (sin nombre/rol al lado, no entra en la barra
      angosta) y usa los tokens constantes del riel en vez de los del tema.
    - triggerClass (string, default "ag-topbar__user"): clase del botón
      disparador completo (irrelevante si `compact`, que fija la suya).

    Mismo fix de JS que `notifications-menu`: el popover lo abre
    `resources/js/organisms/topbar.js` (busca dropdowns en `.ag-topbar` Y
    `.ag-mobile-topbar`) y el logout usa `[data-ag-logout]` con
    `querySelectorAll` (antes `querySelector` — con dos botones de logout en
    el DOM a la vez, uno por breakpoint, solo el primero quedaba wireado).
--}}
@props([
    'userName' => null,
    'activeRoleLabel' => null,
    'cambiarRolHref' => null,
    'compact' => false,
    'triggerClass' => 'ag-topbar__user',
])

@php
    $iniciales = collect(preg_split('/\s+/', trim((string) $userName)))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

@if ($userName)
    <div {{ $attributes->class(['dropdown']) }}>
        <button
            type="button"
            class="{{ $compact ? 'ag-mobile-topbar__avatar-btn' : $triggerClass }}"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="{{ $userName }}"
        >
            <span class="{{ $compact ? 'ag-mobile-topbar__avatar' : 'ag-topbar__avatar' }}" aria-hidden="true">{{ $iniciales ?: '?' }}</span>
            @unless ($compact)
                <span class="ag-topbar__user-id">
                    <span class="ag-topbar__user-name">{{ $userName }}</span>
                    @if ($activeRoleLabel)
                        <span class="ag-topbar__user-role">
                            <span class="visually-hidden">{{ __('seguridad.rol.badge_activo') }}:</span>
                            {{ $activeRoleLabel }}
                        </span>
                    @endif
                </span>
            @endunless
        </button>

        <ul class="dropdown-menu dropdown-menu-end ag-user-menu">
            <li>
                <a href="{{ route('panel.perfil.edit') }}" class="dropdown-item ag-user-menu__item">
                    <x-atoms.icon name="person" size="sm" class="ag-user-menu__icon" />
                    {{ __('seguridad.perfil.menu_item') }}
                </a>
            </li>
            @if ($cambiarRolHref)
                <li>
                    <a href="{{ $cambiarRolHref }}" class="dropdown-item ag-user-menu__item">
                        <x-atoms.icon name="swap_horiz" size="md" class="ag-user-menu__icon" />
                        {{ __('seguridad.rol.switch_trigger') }}
                    </a>
                </li>
            @endif
            <li>
                <button
                    type="button"
                    class="dropdown-item ag-user-menu__item ag-user-menu__logout"
                    data-ag-logout
                >
                    <x-atoms.icon name="logout" size="sm" class="ag-user-menu__icon" />
                    {{ __('ui.topbar.logout') }}
                </button>
            </li>
        </ul>
    </div>
@endif
