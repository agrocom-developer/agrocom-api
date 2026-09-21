{{--
    Page: ordenes/index (GET /panel/ordenes, panel.ordenes.index)
    Listado de órdenes de aplicación (HU-25, tarea 38; homogeneizado con
    Comercial el 17/9/2026): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar (filtros +
    buscador) → tabla → paginación. Mismo molde que contratos/index.blade.php
    (tarea 34): una máquina de estados encima, acción de cambio de estado
    (acá "Activar") separada de "Editar".

    Datos esperados (ver OrdenesController::index()): la cáscara de
    CascaraPanel, más:
    - $ordenes (LengthAwarePaginator<OrdenAplicacion>): fecha de emisión
      descendente, `withQueryString()` (preserva filtros/búsqueda entre
      páginas).
    - $etiquetasContrato (array<int, string>): etiqueta legible por id de
      contrato, ya resuelta por el controlador — la vista nunca consulta
      Comercial (ADR 0003 regla 3, ninguna relación Eloquent desde
      OrdenAplicacion). Un id sin etiqueta (contrato borrado después) cae al
      `#id` crudo.
    - $previstasPorContrato (array<int, int>): `aplicaciones_previstas` de
      cada contrato, para mostrar "N de M" en la columna Aplicación.
    - $resumen (array<int, array{lotes, ordenes_trabajo, incidencias,
      pausas, minutos_pausa}>): datos derivados de CADA orden (clave
      `orden->id`, ver `Aplicacion/ResumenDeOrdenes`): cantidad de lotes,
      órdenes de trabajo realizadas e inconvenientes del campo. Con
      incidencias o pausas, la fila lleva el badge "Con inconvenientes" —
      informativo, nunca cambia un estado.
    - $filtros (array{q: string, estado: ?string, tipo_aplicacion: ?string,
      contrato_id: ?int, nro_aplicacion: ?int}): filtros aplicados, para dejar
      los campos con el valor tras el submit. `q` busca por razón social del
      cliente del contrato (resuelto en el controlador vía `DB::table`, sin
      relación Eloquent cruzando módulos).
    - $opcionesContrato (array<int, string>): opciones del filtro «Contrato»
      — los contratos que tienen órdenes, con el cliente y el número.
    - $opcionesAplicacion (array<int, string>): opciones del filtro
      «Aplicación», en palabras («Primera aplicación»…), hasta la mayor cantidad
      de aplicaciones de un contrato con órdenes.

    Memento de navegación: si se llega con `volver_a`/`volver_texto` (el «Ver
    más» de las órdenes en el formulario del contrato), la cabecera ofrece
    «Volver a Contrato #N» (`molecules/boton-volver`, pila del middleware
    `origen.navegacion`); sin origen, no hay botón.
    Los permisos de cada acción (activar, pausar, cerrar, cancelar, editar,
    eliminar) se resuelven en `_orden-acciones.blade.php` con `@puede`.

    Vista lista/grilla (100% client-side desde el 17/9/2026 — la primera
    versión recargaba con `?vista=`, pedido explícito de sacarlo porque esa
    recarga se sentía como un parpadeo): ambos bloques
    (`[data-ag-vista-panel="lista"|"grilla"]`) se renderizan SIEMPRE los dos,
    uno con `hidden` — `molecules/view-toggle` + `resources/js/molecules/view-toggle.js`
    solo mueven ese atributo y guardan la preferencia en `localStorage`
    (`agrocom:ordenes:vista`). El script inline después del contenedor evita
    el parpadeo al CARGAR si el navegador tenía guardada la grilla (mismo
    problema y misma solución que ya documenta `atoms/tema-inicial.blade.php`
    para el tema claro/oscuro: un módulo por `@vite` siempre corre después
    del primer pintado).

    Gateada por `operaciones.orden.ver`. "Ver" (a `panel.ordenes.show`, la
    ficha de detalle) va SIEMPRE primero, para cualquier estado. "Editar",
    "Activar" y "Eliminar" solo para una orden `emitida` (ver
    `Aplicacion/ActualizarOrden` y `Aplicacion/EliminarOrden`); "Pausar",
    "Cerrar" y "Cancelar" para una `vigente`; "Reanudar" y "Cancelar" para una
    `pausada` (ADR 0022). Presentación, no autorización: el servidor
    revalida las reglas.

    Columnas (ADR 0022): número de aplicación "N de M", cantidad de lotes de
    la orden y cantidad de órdenes de trabajo realizadas.

    Acciones (con sus forms+modales) viven en
    `_orden-acciones.blade.php`, compartido por la fila de tabla y la
    tarjeta de grilla (`_orden-card.blade.php`) — no se duplica ese bloque
    en dos lugares. Mismo criterio que `contratos/index.blade.php` sobre por
    qué los `<form>`/`molecules/confirm-modal` viven FUERA de `row-actions`
    (ver el docblock de ese partial).

    Estilos en resources/css/pages/ordenes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstado = \App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden::TONO_POR_ESTADO;
    // Memento de navegación: si se llegó desde otra pantalla (p. ej. «Ver más» de las
    // órdenes en el formulario del contrato), la cabecera ofrece volver a ella.
    $hayOrigen = session('navegacion_pila', []) !== [];
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.titulo')"
    >
        <div class="ag-ordenes">
            <x-organisms.page-header
                :title="__('operaciones.ordenes.titulo')"
                :subtitle="__('operaciones.ordenes.subtitulo')"
            >
                <x-slot:actions>
                    @if ($hayOrigen)
                        <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.titulo')" />
                    @endif

                    @puede('operaciones.orden.crear')
                        <x-atoms.button :href="route('panel.ordenes.create', array_filter(['contrato_id' => $filtros['contrato_id'] ?? null]))" variant="primary" icon="add">
                            {{ __('operaciones.ordenes.nueva') }}
                        </x-atoms.button>
                    @endpuede
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-ordenes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-ordenes__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['contrato_id', 'nro_aplicacion', 'estado', 'tipo_aplicacion'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($variante, $valor) => [
                    $valor => __('operaciones.estado.'.$valor),
                ])->all();
                $opcionesTipoAplicacion = collect(\App\Dominios\Operaciones\Dominio\TipoAplicacion::cases())
                    ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_aplicacion.'.$caso->value)]);
            @endphp

            @if ($hayFiltrosActivos || $ordenes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.ordenes.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        {{-- Contrato (cliente y número) y aplicación en palabras: las opciones salen de
                             todas las órdenes, no de la página, así que no cambian al filtrar. --}}
                        <x-atoms.select
                            name="contrato_id"
                            id="filtro-contrato"
                            :label="__('operaciones.ordenes.filtro_contrato')"
                            :options="$opcionesContrato"
                            :value="$filtros['contrato_id']"
                            :placeholder="__('operaciones.ordenes.filtro_todos')"
                            searchable
                        />

                        <x-atoms.select
                            name="nro_aplicacion"
                            id="filtro-aplicacion"
                            :label="__('operaciones.ordenes.filtro_aplicacion')"
                            :options="$opcionesAplicacion"
                            :value="$filtros['nro_aplicacion']"
                            :placeholder="__('operaciones.ordenes.filtro_todos')"
                        />

                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('operaciones.ordenes.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('operaciones.ordenes.filtro_todos')"
                        />

                        <x-atoms.select
                            name="tipo_aplicacion"
                            id="filtro-tipo-aplicacion"
                            :label="__('operaciones.ordenes.filtro_tipo_aplicacion')"
                            :options="$opcionesTipoAplicacion"
                            :value="$filtros['tipo_aplicacion']"
                            :placeholder="__('operaciones.ordenes.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.ordenes.index')"
                        :value="$filtros['q']"
                        :placeholder="__('operaciones.ordenes.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />

                    <x-molecules.view-toggle
                        results-id="ag-ordenes-resultados"
                        storage-key="agrocom:ordenes:vista"
                    />
                </div>
            @endif

            @if ($ordenes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.ordenes.filtro_vacio_titulo')"
                        :detail="__('operaciones.ordenes.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="assignment"
                        :title="__('operaciones.ordenes.vacio_titulo')"
                        :detail="__('operaciones.ordenes.vacio_detalle')"
                    />
                @endif
            @else
                <div id="ag-ordenes-resultados">
                    <div data-ag-vista-panel="lista">
                        <x-molecules.index-table columns="3rem 1.5fr 0.9fr 0.6fr 0.8fr 0.9fr 0.9fr 0.9fr 1.3fr var(--ag-row-actions-width)">
                            <x-slot:head>
                                <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_contrato') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_aplicacion') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_lotes') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_ordenes_trabajo') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_tipo_aplicacion') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_dosis') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_fecha_emision') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_estado') }}</span>
                                <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                            </x-slot:head>

                            @foreach ($ordenes as $orden)
                                @php
                                    $estadoValor = $orden->estado->value;
                                    $datosOrden = $resumen[$orden->id] ?? ['lotes' => 0, 'ordenes_trabajo' => 0, 'incidencias' => 0, 'pausas' => 0];
                                    $previstas = $previstasPorContrato[$orden->contrato_id] ?? null;
                                    $tieneInconvenientes = ($datosOrden['incidencias'] + $datosOrden['pausas']) > 0;
                                    // HU-79 (tarea 110): la orden guarda uno de los dos según la
                                    // categoría de insumo elegida, nunca ambos — ver
                                    // OrdenesController::normalizarDatos().
                                    $dosisTexto = $orden->kilos_por_vuelo !== null
                                        ? __('operaciones.ordenes.dosis_kilos_por_vuelo', ['cantidad' => number_format((float) $orden->kilos_por_vuelo, 2, ',', '.')])
                                        : ($orden->litros_ha !== null
                                            ? __('operaciones.ordenes.dosis_litros_ha', ['cantidad' => number_format((float) $orden->litros_ha, 2, ',', '.')])
                                            : '—');
                                @endphp
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell" class="ag-index-table__indice">
                                        {{ ($ordenes->currentPage() - 1) * $ordenes->perPage() + $loop->iteration }}
                                    </span>
                                    <span role="cell">{{ $etiquetasContrato[$orden->contrato_id] ?? "#{$orden->contrato_id}" }}</span>
                                    <span role="cell" class="ag-ordenes__mono">
                                        {{ $previstas !== null ? __('operaciones.ordenes.aplicacion_n_de_m', ['nro' => $orden->nro_aplicacion, 'total' => $previstas]) : $orden->nro_aplicacion }}
                                    </span>
                                    <span role="cell" class="ag-ordenes__mono">{{ $datosOrden['lotes'] }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ $datosOrden['ordenes_trabajo'] }}</span>
                                    <span role="cell">{{ __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value) }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ $dosisTexto }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ $orden->fecha_emision->format('d/m/Y') }}</span>
                                    <span role="cell" class="ag-ordenes__estados">
                                        <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                            {{ __('operaciones.estado.'.$estadoValor) }}
                                        </x-atoms.badge>
                                        @if ($tieneInconvenientes)
                                            <x-atoms.badge variant="warning" icon="warning">
                                                {{ __('operaciones.ordenes.badge_inconvenientes') }}
                                            </x-atoms.badge>
                                        @endif
                                    </span>

                                    <span role="cell" class="ag-index-table__acciones">
                                        @include('operaciones::pages.ordenes._orden-acciones', ['orden' => $orden, 'contexto' => 'lista-'])
                                    </span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    </div>

                    <div data-ag-vista-panel="grilla" hidden>
                        <div class="ag-ordenes__grilla">
                            @foreach ($ordenes as $orden)
                                @include('operaciones::pages.ordenes._orden-card', ['orden' => $orden])
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Evita el parpadeo al cargar si `localStorage` guardaba
                     "grilla" (el servidor siempre arranca en "lista", ver
                     docblock de cabecera) — mismo motivo que
                     atoms/tema-inicial.blade.php: un <script type="module">
                     por @vite corre después del primer pintado, así que la
                     única forma de no mostrar "lista" un instante es un
                     script inline bloqueante, acá mismo, justo después del
                     contenedor que ya existe en el DOM. --}}
                <script>
                    (function () {
                        try {
                            if (localStorage.getItem('agrocom:ordenes:vista') !== 'grilla') return;
                        } catch (e) {
                            return;
                        }

                        var contenedor = document.getElementById('ag-ordenes-resultados');
                        if (!contenedor) return;

                        contenedor.querySelectorAll('[data-ag-vista-panel]').forEach(function (panel) {
                            panel.hidden = panel.dataset.agVistaPanel !== 'grilla';
                        });

                        var grupo = document.querySelector('[data-ag-view-toggle][data-resultados="ag-ordenes-resultados"]');
                        if (!grupo) return;

                        grupo.querySelectorAll('[data-ag-vista-btn]').forEach(function (boton) {
                            var activo = boton.dataset.agVistaBtn === 'grilla';
                            boton.classList.toggle('is-active', activo);
                            boton.setAttribute('aria-pressed', String(activo));
                        });
                    })();
                </script>

                <x-molecules.pagination :paginator="$ordenes" :aria-label="__('operaciones.ordenes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
