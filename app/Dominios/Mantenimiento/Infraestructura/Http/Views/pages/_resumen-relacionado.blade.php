{{--
    Partial: resumen relacionado del aside de una ficha de edición de
    Mantenimiento (batería, generador, vehículo, ficha de dron) — §6.3.1 de
    docs/diseno/guia_pantalla_panel.md.

    Recorre las tarjetas que arma el controlador (ver
    `ResumenRelacionadoDeEquipo` y el `resumenRelacionado()` de cada
    controlador): una tarjeta por objeto relacionado, resuelta del lado del
    servidor y ya gateada por el permiso de lo que muestra. Con datos, una
    `molecules/summary-card`; sin datos, un `molecules/empty-state` compacto
    con su acceso directo; nunca las dos a la vez.

    Espera:
    - $resumenRelacionado (list<array{titulo, icono, tieneDatos, items,
      vacioTitulo, vacioDetalle, acciones}>|null): `null`/ausente en alta.
      `acciones` es una lista de `['label', 'href', 'icono']`; cada botón se
      gatea por el permiso de la cosa que hace, del lado del controlador.
--}}
@foreach ($resumenRelacionado ?? [] as $resumen)
    @if ($resumen['tieneDatos'])
        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
            @if ($resumen['acciones'] !== [])
                <x-slot:action>
                    @foreach ($resumen['acciones'] as $accionResumen)
                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'arrow_forward'" block>
                            {{ $accionResumen['label'] }}
                        </x-atoms.button>
                    @endforeach
                </x-slot:action>
            @endif
        </x-molecules.summary-card>
    @else
        <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
            @if ($resumen['acciones'] !== [])
                <x-slot:action>
                    @foreach ($resumen['acciones'] as $accionResumen)
                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'add'">
                            {{ $accionResumen['label'] }}
                        </x-atoms.button>
                    @endforeach
                </x-slot:action>
            @endif
        </x-molecules.empty-state>
    @endif
@endforeach
