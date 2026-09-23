{{--
    Parcial: pestaña "Mis devengos" del piloto y del ayudante (tarea 137) —
    su liquidación del mes: lo devengado, los anticipos ya cobrados a cuenta
    y el saldo. Es la misma sección que en "Resumen" ve cualquier rol con
    `persona_id`; acá solo se agrupa, no hay contenido nuevo.

    Cada `@isset` no es un permiso disfrazado — `ArmarDashboard` ya decidió
    qué claves existen; acá solo se pinta lo que llegó.

    Espera: $secciones (array<string, mixed>).
--}}
<div class="ag-dash__stack">
    @isset($secciones['mi_liquidacion'])
        @include('seguridad::pages.dashboard._seccion-mi-liquidacion', ['liquidacion' => $secciones['mi_liquidacion']])
    @endisset
</div>
