{{--
    Page: busqueda/index (GET /panel/buscar, panel.buscar)
    Pantalla de resultados del buscador del header (9/9/2026). Arquetipo
    Listado de docs/diseno/guia_pantalla_panel.md §6.2, con una diferencia:
    no hay una tabla sino un BLOQUE POR ENTIDAD, que es como lo pidió el
    dueño — "que en la página de resultados nos salga bloques de las otras
    páginas según las coincidencias".

    Cada bloque trae unas pocas filas y, si hay más, un enlace "ver todos"
    al listado de ese módulo con la búsqueda ya aplicada: esa pantalla ya
    sabe filtrar, paginar y gatear por permiso, así que el buscador no la
    reimplementa.

    Sin permiso de pantalla: lo que se ve lo decide `BuscarEnElPanel` bloque
    por bloque contra el ROL ACTIVO (ver BusquedaController).

    Datos esperados: la cáscara de CascaraPanel, más:
    - $terminos (TerminosBusqueda): lo escrito, ya partido en palabras.
    - $bloques (list<BloqueBusqueda>): solo los que tienen coincidencias.
    - $total (int): suma de coincidencias de todos los bloques.

    Estilos en resources/css/pages/busqueda.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('busqueda.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('busqueda.titulo')"
    >
        <div class="ag-busqueda">
            <x-organisms.page-header
                :title="$terminos->vacia() ? __('busqueda.titulo_sin_consulta') : __('busqueda.titulo')"
                :subtitle="__('busqueda.subtitulo')"
            />

            @if ($terminos->vacia())
                {{-- Dos estados iniciales distintos: no escribió nada, o
                     escribió solo letras sueltas que no acotan nada. --}}
                <x-molecules.alert-strip variant="info" icon="search">
                    <strong>{{ __((string) $terminos === '' ? 'busqueda.inicial_titulo' : 'busqueda.corta_titulo') }}</strong>
                    <span class="ag-busqueda__ayuda">{{ __((string) $terminos === '' ? 'busqueda.inicial_ayuda' : 'busqueda.corta_ayuda') }}</span>
                </x-molecules.alert-strip>
            @elseif ($bloques === [])
                <x-molecules.alert-strip variant="info" icon="search_off">
                    <strong>{{ __('busqueda.vacio_titulo', ['consulta' => (string) $terminos]) }}</strong>
                    <span class="ag-busqueda__ayuda">{{ __('busqueda.vacio_ayuda') }}</span>
                </x-molecules.alert-strip>
            @else
                <p class="ag-busqueda__resumen">
                    {{ __($total === 1 ? 'busqueda.resumen_uno' : 'busqueda.resumen', ['total' => $total, 'consulta' => (string) $terminos]) }}
                </p>

                <div class="ag-busqueda__bloques">
                    @foreach ($bloques as $bloque)
                        <section class="ag-busqueda__bloque" aria-labelledby="bloque-{{ $bloque->clave }}">
                            <header class="ag-busqueda__bloque-head">
                                <h2 id="bloque-{{ $bloque->clave }}" class="ag-busqueda__bloque-titulo">
                                    <x-atoms.icon :name="$bloque->icono" size="sm" />
                                    {{ $bloque->titulo }}
                                </h2>
                                <span class="ag-busqueda__bloque-total">{{ $bloque->total }}</span>
                            </header>

                            <ul class="ag-busqueda__filas">
                                @foreach ($bloque->resultados as $resultado)
                                    <li class="ag-busqueda__fila">
                                        @if ($resultado->href)
                                            <a href="{{ $resultado->href }}" class="ag-busqueda__enlace">
                                                <span class="ag-busqueda__titulo">{{ $resultado->titulo }}</span>
                                                @if ($resultado->detalle)
                                                    <span class="ag-busqueda__detalle">{{ $resultado->detalle }}</span>
                                                @endif
                                            </a>
                                        @else
                                            <span class="ag-busqueda__enlace">
                                                <span class="ag-busqueda__titulo">{{ $resultado->titulo }}</span>
                                                @if ($resultado->detalle)
                                                    <span class="ag-busqueda__detalle">{{ $resultado->detalle }}</span>
                                                @endif
                                            </span>
                                        @endif

                                        @if ($resultado->estado)
                                            <x-atoms.badge variant="neutral">{{ $resultado->estado }}</x-atoms.badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @if ($bloque->hayMasQueNoSeMuestran() && $bloque->verTodosHref)
                                <a href="{{ $bloque->verTodosHref }}" class="ag-busqueda__ver-todos">
                                    {{ __('busqueda.ver_todos', ['total' => $bloque->total, 'bloque' => $bloque->titulo]) }}
                                    <x-atoms.icon name="chevron_right" size="sm" />
                                </a>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
