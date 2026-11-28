{{--
    Organism: cuadricula-medios (`.ag-cuadricula-medios`)
    Las imágenes y los documentos de una ficha como un lienzo de bloques: rectángulos blancos sobre un fondo gris
    claro, con una separación pequeña y uniforme (como los lienzos tipo Business Model Canvas). El primer bloque
    es el grande y el primero pendiente de aprobación se resalta en verde claro. Cada bloque muestra la
    MINIATURA (`url_miniatura`, imagen `cover` centrada), el nombre del tipo, el estado y las marcas de
    «obligatoria» y «pendiente»; para un PDF u otro archivo sin vista previa, un ícono por tipo.

    NO lleva acciones: un bloque es solo un disparador. Revisar (aprobar, rechazar, descargar) ocurre en el modal
    de `organisms/modal-revision-medio`, que este componente incluye y que muestra el contenido completo. Nunca
    se actúa sobre una miniatura.

    Props:
    - id (requerido): identificador único de la cuadrícula en la página; el modal es `{id}-modal`.
    - items (requerido): lista de `ItemDeRevisionDeMedio::aArray()`.
    - label (nullable): nombre accesible de la lista, ya traducido.
--}}
@props([
    'id',
    'items' => [],
    'label' => null,
])

@php
    $modalId = $id.'-modal';
    $iconos = ['pdf' => 'picture_as_pdf', 'otro' => 'description', 'imagen' => 'image_not_supported'];
    $primerPendiente = collect($items)->search(fn (array $item): bool => $item['pendiente']);
    $hayGrande = count($items) >= 3;
@endphp

@if ($items !== [])
    <ul
        id="{{ $id }}"
        class="ag-cuadricula-medios"
        aria-label="{{ $label ?? __('revision_medios.cuadricula_aria', ['cantidad' => count($items)]) }}"
    >
        @foreach ($items as $indice => $item)
            @php
                $medio = $item['medio'];
                $conMiniatura = $medio['tiene_vista_previa'] && $medio['existe'] && $medio['url_miniatura'];
                $icono = ! $medio['existe'] ? 'broken_image' : ($iconos[$medio['tipo']] ?? 'description');
                $textoSinVista = ! $medio['existe']
                    ? __('revision_medios.no_disponible_miniatura')
                    : ($medio['tipo'] === 'pdf' ? __('revision_medios.pdf').' · '.__('revision_medios.sin_vista_previa') : __('revision_medios.otro').' · '.__('revision_medios.sin_vista_previa'));
            @endphp
            <li @class([
                'ag-cuadricula-medios__item',
                'ag-cuadricula-medios__item--grande' => $hayGrande && $indice === 0,
            ])>
                <button
                    type="button"
                    @class([
                        'ag-cuadricula-medios__celda',
                        'ag-cuadricula-medios__celda--resaltada' => $indice === $primerPendiente,
                    ])
                    data-medio-modal="{{ $modalId }}"
                    data-medio-clave="{{ $item['clave'] }}"
                    aria-haspopup="dialog"
                    aria-label="{{ __('revision_medios.revisar_aria', ['titulo' => $item['titulo'], 'estado' => mb_strtolower($item['estado_etiqueta'])]) }}"
                >
                    <span class="ag-cuadricula-medios__imagen">
                        @if ($conMiniatura)
                            <img src="{{ $medio['url_miniatura'] }}" alt="" loading="lazy" decoding="async">
                        @else
                            <span class="ag-cuadricula-medios__sin-vista">
                                <x-atoms.icon :name="$icono" size="lg" />
                                <span>{{ $textoSinVista }}</span>
                            </span>
                        @endif
                    </span>
                    <span class="ag-cuadricula-medios__pie">
                        <span class="ag-cuadricula-medios__titulo">{{ $item['titulo'] }}</span>
                        <x-atoms.badge :variant="$item['tono']">{{ $item['estado_etiqueta'] }}</x-atoms.badge>
                    </span>
                    @if ($item['obligatoria'] || $item['pendiente'] || $item['subtitulo'])
                        <span class="ag-cuadricula-medios__marcas">
                            @if ($item['subtitulo'])
                                <span class="ag-cuadricula-medios__subtitulo">{{ $item['subtitulo'] }}</span>
                            @endif
                            @if ($item['obligatoria'])
                                <span class="ag-cuadricula-medios__marca"><x-atoms.icon name="star" size="sm" />{{ __('revision_medios.obligatoria') }}</span>
                            @endif
                            @if ($item['pendiente'])
                                <span class="ag-cuadricula-medios__marca"><x-atoms.icon name="pending_actions" size="sm" />{{ __('revision_medios.pendiente') }}</span>
                            @endif
                        </span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>

    <x-organisms.modal-revision-medio :id="$modalId" :items="$items" />
@endif
