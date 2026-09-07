{{--
    Parcial: cola de validación — sesiones cerradas esperando aprobación, la
    más vieja primero. Es el trabajo pendiente del jefe de campo, no un
    informe: por eso lleva enlace directo a la pantalla donde se resuelve.

    Espera: $sesiones (list).
--}}
<section>
    <div class="ag-card">
        <div class="ag-card__head">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.cola_validacion_titulo') }}</h2>
            <a class="ag-dash__link" href="{{ route('panel.sesiones.validacion.index') }}">
                {{ __('seguridad.dashboard.ver_todas') }}
            </a>
        </div>
        @include('seguridad::pages.dashboard._tabla-sesiones', ['sesiones' => $sesiones, 'limiteLista' => 4])
    </div>
</section>
