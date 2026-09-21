{{--
    Molecule: pagination
    Paginación por número de página, para reemplazar el patrón
    "Anterior · Página X de Y · Siguiente" que hoy repite cada listado (sin
    forma de saltar a una página que no sea la siguiente/anterior).

    Markup de `.pagination`/`.page-item`/`.page-link`, el componente NATIVO
    de Bootstrap (ADR 0002: AdminLTE/Bootstrap para estructura) — no uno
    propio de cero. Sale ya coloreado con la marca sin CSS adicional porque
    `--bs-primary` (y el resto de las variables `--bs-*` que Bootstrap usa
    internamente) ya están mapeadas a los tokens del proyecto en
    tokens/semantic/theme-{light,dark}.css.

    La ventana de páginas (qué números mostrar + dónde van los "…") reusa
    `\Illuminate\Pagination\UrlWindow` — el mismo cálculo que usa la vista
    de paginación por defecto de Laravel — en vez de reinventar esa lógica.

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

    <nav {{ $attributes }} aria-label="{{ $ariaLabel }}">
        <ul class="pagination justify-content-center">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-hidden="true">
                        <x-atoms.icon name="chevron_left" size="sm" />
                    </span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <x-atoms.icon name="chevron_left" size="sm" />
                    </a>
                @endif
            </li>

            @foreach (['first', 'slider', 'last'] as $segmento)
                @if (is_array($ventana[$segmento] ?? null))
                    @if ($huboSegmentoPrevio)
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    @endif

                    @foreach ($ventana[$segmento] as $pagina => $url)
                        @php $esActual = (int) $pagina === $paginator->currentPage(); @endphp
                        <li class="page-item {{ $esActual ? 'active' : '' }}" @if ($esActual) aria-current="page" @endif>
                            @if ($esActual)
                                <span class="page-link">{{ $pagina }}</span>
                            @else
                                <a class="page-link" href="{{ $url }}">{{ $pagina }}</a>
                            @endif
                        </li>
                    @endforeach

                    @php $huboSegmentoPrevio = true; @endphp
                @endif
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <x-atoms.icon name="chevron_right" size="sm" />
                    </a>
                @else
                    <span class="page-link" aria-hidden="true">
                        <x-atoms.icon name="chevron_right" size="sm" />
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
