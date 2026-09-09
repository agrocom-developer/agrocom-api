{{--
    Molecule: timezone-badge (tarea 63 — bitácora + zona horaria del usuario;
    reemplaza a `timezone-selector` el 9/9/2026)

    Badge de LECTURA en el pie del panel y del portal con la zona horaria bajo
    la que se está mostrando todo (la que la bitácora usa para fechar cada
    entrada). No es un control: no se elige a mano.

    Antes esto era un `<select>` con las ~400 zonas IANA metido en el topbar,
    al lado del toggle de tema. El dueño lo cortó mirando el panel andando
    (9/9/2026): *"eso se supone que lo captura internamente, no que podamos
    ver o elegir la zona horaria"*. Y tenía razón por dos lados — el navegador
    ya sabe la respuesta (`Intl.DateTimeFormat().resolvedOptions().timeZone`,
    que el login viene mandando desde el vamos), y un combobox de 400 opciones
    en el header pesaba como si fuera una decisión, cuando es un dato.

    Queda como badge para que el dato siga visible: si la bitácora dice "hoy
    14:32", conviene poder ver desde dónde se está leyendo esa hora.

    La detección y la persistencia las hace `timezone-badge.js` (POST a
    `<meta name="ag-preferencias-zona-horaria-url">`, mismo endpoint que usaba
    el select). El `data-ag-timezone` de acá abajo es lo que ese JS compara
    contra el navegador: vacío = todavía no hay preferencia guardada.

    Props:
    - value (nullable): zona horaria persistida (IANA, p. ej.
      "America/La_Paz"), o null si el usuario todavía no tiene ninguna — en
      ese caso el badge nace vacío y el JS lo completa apenas carga.
--}}
@props([
    'value' => null,
])

<span
    {{ $attributes->class(['ag-timezone-badge']) }}
    title="{{ __('ui.timezone.badge_title') }}"
    data-ag-timezone-badge
    data-ag-timezone="{{ $value }}"
>
    <x-atoms.icon name="schedule" size="sm" class="ag-timezone-badge__icon" />
    <span class="visually-hidden">{{ __('ui.timezone.badge_title') }}:</span>
    <span class="ag-timezone-badge__value" data-ag-timezone-value>{{ str_replace('_', ' ', (string) $value) }}</span>
</span>
