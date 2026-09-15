{{--
    Molecule: pagination (`.ag-pagination`)
    Paginación por número de página, para reemplazar el patrón
    "Anterior · Página X de Y · Siguiente" que hoy repite cada listado
    (sin forma de saltar a una página que no sea la siguiente/anterior).

    Reusa `\Illuminate\Pagination\UrlWindow` (el mismo cálculo de ventana
    con "…" que usa la vista de paginación por defecto de Laravel) en vez de
    reinventar la lógica de qué números mostrar — esta molécula solo decide
    el markup/CSS, coherente con el resto del catálogo (tokens, sin Bootstrap
    crudo).

    Props:
    - paginator (LengthAwarePaginator, requerido).
    - ariaLabel (string, requerido): ya traducido por el llamador.
--}}
@props([
    'paginator',
    'ariaLabel',
])

@if ($paginator->hasPages())
    @php
        $ventana = \Illuminate\Pagination\UrlWindow::make($paginator);
        $huboSegmentoPrevio = false;
    @endphp

    <nav {{ $attributes->class(['ag-pagination']) }} aria-label="{{ $ariaLabel }}">
        @if ($paginator->onFirstPage())
            <span class="ag-pagination__arrow ag-pagination__arrow--disabled" aria-hidden="true">
                <x-atoms.icon name="chevron_left" size="sm" />
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="ag-pagination__arrow" rel="prev">
                <x-atoms.icon name="chevron_left" size="sm" />
            </a>
        @endif

        <div class="ag-pagination__pages">
            @foreach (['first', 'slider', 'last'] as $segmento)
                @if (is_array($ventana[$segmento] ?? null))
                    @if ($huboSegmentoPrevio)
                        <span class="ag-pagination__dots" aria-hidden="true">&hellip;</span>
                    @endif

                    @foreach ($ventana[$segmento] as $url => $pagina)
                        @if ((int) $pagina === $paginator->currentPage())
                            <span class="ag-pagination__page ag-pagination__page--current" aria-current="page">
                                {{ $pagina }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="ag-pagination__page">{{ $pagina }}</a>
                        @endif
                    @endforeach

                    @php $huboSegmentoPrevio = true; @endphp
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="ag-pagination__arrow" rel="next">
                <x-atoms.icon name="chevron_right" size="sm" />
            </a>
        @else
            <span class="ag-pagination__arrow ag-pagination__arrow--disabled" aria-hidden="true">
                <x-atoms.icon name="chevron_right" size="sm" />
            </span>
        @endif
    </nav>
@endif
