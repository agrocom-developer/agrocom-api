{{--
    Parcial: vista carrusel del tab Multimedia (Fase 8) — simula el avance
    cronológico de la campaña, una diapositiva por sesión de vuelo.
    `.carousel` nativo de Bootstrap 5 (ya cargado, cero librería nueva);
    `data-bs-ride` se omite a propósito para que NO autoavance — el propio
    Bootstrap ya respeta prefers-reduced-motion en la transición de slide.

    Espera:
    - $sesiones (list): filas de DatosDemoCapturasRc::sesiones().
--}}
<div id="ag-multimedia-carrusel" class="carousel slide ag-multimedia-carousel">
    <div class="carousel-inner">
        @foreach ($sesiones as $index => $sesion)
            <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                <div class="ag-multimedia-carousel__imagenes">
                    @foreach ($sesion['capturas'] as $captura)
                        <img
                            src="{{ asset('demo/capturas-rc/'.$captura['archivo']) }}"
                            alt="{{ $captura['descripcion'] }}"
                            class="ag-multimedia-carousel__imagen"
                            loading="lazy"
                        >
                    @endforeach
                </div>
                <div class="ag-multimedia-carousel__caption">
                    <strong>{{ $sesion['fecha'] }} · {{ $sesion['lote'] }}</strong>
                    <span>{{ $sesion['piloto'] }} · {{ $sesion['dron'] }} · {{ $sesion['hectareas'] }} · {{ $sesion['tiempoVuelo'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#ag-multimedia-carrusel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">{{ __('seguridad.dashboard.multimedia_anterior') }}</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#ag-multimedia-carrusel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">{{ __('seguridad.dashboard.multimedia_siguiente') }}</span>
    </button>

    <div class="carousel-indicators">
        @foreach ($sesiones as $index => $sesion)
            <button
                type="button"
                data-bs-target="#ag-multimedia-carrusel"
                data-bs-slide-to="{{ $index }}"
                class="{{ $index === 0 ? 'active' : '' }}"
                aria-label="{{ $sesion['fecha'] }}"
                @if ($index === 0) aria-current="true" @endif
            ></button>
        @endforeach
    </div>
</div>
