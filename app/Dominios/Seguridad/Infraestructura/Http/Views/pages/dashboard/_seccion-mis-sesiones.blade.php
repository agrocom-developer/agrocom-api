{{--
    Parcial: las últimas sesiones de la persona que está mirando (piloto o
    auxiliar). Misma tabla que la cola de validación — el mismo dato, otro
    filtro: no hay dos componentes de tabla de sesiones que mantener.

    Espera: $sesiones (list).
--}}
<section>
    <div class="ag-card">
        <div class="ag-card__head">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.mis_sesiones_titulo') }}</h2>
        </div>
        @include('seguridad::pages.dashboard._tabla-sesiones', ['sesiones' => $sesiones, 'limiteLista' => 4])
    </div>
</section>
