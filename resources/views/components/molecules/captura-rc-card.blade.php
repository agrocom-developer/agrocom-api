{{--
    Molecule: captura-rc-card (Fase 8 — vista galería del tab Multimedia).
    Tarjeta de una sesión de vuelo: tira de miniaturas + identidad (lote,
    fecha, piloto, dron) + cifras de rendimiento. Sin lógica de negocio —
    todo ya viene formateado del llamador.

    Props:
    - fecha, piloto, lote, dron (requeridos, ya formateados).
    - hectareas, tiempoVuelo, pesticidaLitros (requeridos, ya formateados).
    - capturas (requerido): list<{url, descripcion}> — `url` es la URL de
      streaming de la evidencia (`panel.evidencias.archivo`), ya resuelta por
      el llamador; `descripcion` ya traducida.

      Antes recibía un NOMBRE DE ARCHIVO que el componente resolvía contra
      public/demo/capturas-rc/ — cuando las capturas eran de maqueta y vivían
      en public/. Las reales son privadas (disco `r2`, fuera de public/) y se
      sirven por una ruta con permiso, así que el componente ya no puede
      construir la ruta: la recibe. Sin miniatura aparte por el mismo motivo
      — el CSS las escala.
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
                href="{{ $captura['url'] }}"
                target="_blank"
                rel="noopener"
                class="ag-captura-card__imagen-link"
                title="{{ $captura['descripcion'] }}"
            >
                <img
                    src="{{ $captura['url'] }}"
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
