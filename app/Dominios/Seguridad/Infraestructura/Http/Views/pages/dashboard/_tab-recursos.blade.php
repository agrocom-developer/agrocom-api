{{--
    Parcial: pestaña "Recursos" del jefe de campo (tarea 138) — los recursos
    que se ocupan (las cuadrillas con trabajo abierto y lo que llevan) y los que
    faltan (el stock bajo mínimo).

    Cada bloque llega solo si el rol activo tiene el permiso de su pantalla
    completa (`personal.equipo_trabajo.ver` e `inventario.movimiento.ver`):
    `ArmarDashboard` ya decidió qué claves existen y acá solo se pinta lo que
    llegó. Una sola tarjeta por bloque, en vertical: `.ag-dash__grid` se
    esconde bajo 768 px y con él se iría el stock.

    Espera: $secciones (array<string, mixed>), con `recursos_en_uso` (ver
    ArmarDashboard::recursosEnUso()) y/o `stock` (ver ArmarDashboard::stock()).
--}}
<div class="ag-dash__stack">
    @isset($secciones['recursos_en_uso'])
        @include('seguridad::pages.dashboard._seccion-recursos-en-uso', ['recursos' => $secciones['recursos_en_uso']])
    @endisset

    @isset($secciones['stock'])
        @include('seguridad::pages.dashboard._seccion-stock', ['stock' => $secciones['stock']])
    @endisset
</div>
