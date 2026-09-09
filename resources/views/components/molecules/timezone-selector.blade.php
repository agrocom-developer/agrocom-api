{{--
    Molecule: timezone-selector (tarea 63 — bitácora + zona horaria del usuario)
    Selector de zona horaria en el topbar: dropdown con opciones de IANA timezone
    identifiers (pasadas por `DateTimeZone::listIdentifiers()`), con búsqueda
    automática vía el átomo `select` (si hay 400 opciones, el combobox con
    búsqueda aparece).

    Contrato de persistencia: idéntico a `theme-toggle.blade.php` — leer la URL
    desde `<meta name="ag-preferencias-zona-horaria-url">`, POST JSON
    `{zona_horaria: valor}` con CSRF-Token y Accept json, fire-and-forget.

    Props:
    - value (nullable): zona horaria actual (IANA identifier como string, p. ej.
      "America/La_Paz"), o null si no hay preferencia guardada.

    Slot: (sin usar — solo la vista del select sin label visible)
--}}
@props([
    'value' => null,
])

@php
    $opciones = collect(\DateTimeZone::listIdentifiers())
        ->mapWithKeys(fn($id) => [$id => str_replace('_', ' ', $id)])
        ->all();
@endphp

<div class="ag-timezone-selector">
    <x-atoms.select
        name="zona_horaria"
        :options="$opciones"
        :value="$value"
        placeholder="{{ __('ui.timezone.placeholder') }}"
        data-ag-timezone-selector
    />
</div>
