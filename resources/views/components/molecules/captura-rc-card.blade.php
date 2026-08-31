{{--
    Molecule: captura-rc-card (Fase 8 — vista galería del tab Multimedia).
    Tarjeta de una sesión de vuelo: tira de miniaturas + identidad (lote,
    fecha, piloto, dron) + cifras de rendimiento. Sin lógica de negocio —
    todo ya viene formateado del llamador.

    Props:
    - fecha, piloto, lote, dron (requeridos, ya formateados).
    - hectareas, tiempoVuelo, pesticidaLitros (requeridos, ya formateados).
    - capturas (requerido): list<{archivo, descripcion}> — `archivo` es el
      nombre de archivo dentro de public/demo/capturas-rc/ (y su miniatura
      en .../thumbs/), `descripcion` ya traducida/resuelta.
--}}
@props([
    'fecha',
    'piloto',
    'lote',
    'dron',
    'hectareas',
    'tiempoVuelo',
    'pesticidaLitros',
    'capturas',
])

<div {{ $attributes->class(['ag-captura-card']) }}>
    <div class="ag-captura-card__imagenes">
        @foreach ($capturas as $captura)
            <a
                href="{{ asset('demo/capturas-rc/'.$captura['archivo']) }}"
                target="_blank"
                rel="noopener"
                class="ag-captura-card__imagen-link"
                title="{{ $captura['descripcion'] }}"
            >
                <img
                    src="{{ asset('demo/capturas-rc/thumbs/'.$captura['archivo']) }}"
                    alt="{{ $captura['descripcion'] }}"
                    class="ag-captura-card__imagen"
                    loading="lazy"
                >
            </a>
        @endforeach
    </div>

    <div class="ag-captura-card__body">
        <div class="ag-captura-card__head">
            <span class="ag-captura-card__lote">{{ $lote }}</span>
            <span class="ag-dash__mono-note">{{ $fecha }}</span>
        </div>
        <p class="ag-captura-card__meta">{{ $piloto }} · {{ $dron }}</p>
        <p class="ag-captura-card__cifras">{{ $hectareas }} · {{ $tiempoVuelo }} · {{ $pesticidaLitros }}</p>
    </div>
</div>
