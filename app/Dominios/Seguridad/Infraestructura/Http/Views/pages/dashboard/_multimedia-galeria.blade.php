{{--
    Parcial: vista galería del tab Multimedia (Fase 8) — una tarjeta por
    sesión de vuelo, con sus capturas agrupadas.

    Espera:
    - $sesiones (list): filas de DatosDemoCapturasRc::sesiones().
--}}
<div class="ag-multimedia-grid">
    @foreach ($sesiones as $sesion)
        <x-molecules.captura-rc-card
            :fecha="$sesion['fecha']"
            :piloto="$sesion['piloto']"
            :lote="$sesion['lote']"
            :dron="$sesion['dron']"
            :hectareas="$sesion['hectareas']"
            :tiempo-vuelo="$sesion['tiempoVuelo']"
            :pesticida-litros="$sesion['pesticidaLitros']"
            :capturas="$sesion['capturas']"
        />
    @endforeach
</div>
