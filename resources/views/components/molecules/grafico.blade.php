{{--
    Molecule: grafico
    Una gráfica de barras o de líneas en una tarjeta, con la misma información en una
    tabla. Los datos llegan ya resueltos del servidor (el caso de uso de Métricas); acá no
    se calcula nada. La gráfica la dibuja Chart.js (resources/js/molecules/grafico.js, carga
    diferida) con los colores, la tipografía y los radios de los tokens, y sigue al tema
    claro/oscuro. Sin JavaScript, o para quien usa lector de pantalla, la tabla «Ver datos»
    es la alternativa completa: trae exactamente los mismos números.

    Qué tipo usar: `lineas` para una magnitud en el tiempo; `barras` para comparar
    categorías (con `horizontal` si los nombres son largos o son más de cinco); `apilado`
    solo para partes de un mismo total. No hay dona: ninguna medición tiene tan pocas partes.

    Props:
    - titulo (requerido): título de la tarjeta, ya traducido.
    - ayuda (nullable): una línea bajo el título, ya traducida.
    - tipo (barras|lineas, default "barras").
    - horizontal (bool, default false): barras horizontales.
    - apilado (bool, default false): barras apiladas.
    - etiquetas (requerido, list<string>): una por categoría o punto del tiempo, ya traducida.
    - series (requerido, list<array{nombre: string, valores: list<int|float|null>, color: string}>):
      `color` es el nombre de un token semántico (`success`, `info`, `warning`, `danger`,
      `distintivo-1`, `distintivo-2`): se usa como `--ag-color-{color}`. Un valor `null` es «sin dato».
    - formato (array{decimales?: int, sufijo?: string}): cómo se muestran los valores.
    - categoria (requerido): encabezado de la primera columna de la tabla, ya traducido.
    - vacio (nullable): texto cuando no hay nada que graficar; sin él se usa el genérico.
    Slot `actions`: un enlace o botón en la cabecera.
--}}
@props([
    'titulo',
    'ayuda' => null,
    'tipo' => 'barras',
    'horizontal' => false,
    'apilado' => false,
    'etiquetas',
    'series',
    'formato' => [],
    'categoria',
    'vacio' => null,
])

@php
    $id = 'ag-grafico-'.\Illuminate\Support\Str::random(8);
    $decimales = (int) ($formato['decimales'] ?? 0);
    $sufijo = $formato['sufijo'] ?? null;
    $hayDatos = collect($series)->flatMap(fn (array $serie) => $serie['valores'])->contains(fn ($valor) => $valor !== null && (float) $valor !== 0.0);
    $texto = fn ($valor): string => $valor === null
        ? __('ui.grafico.sin_dato')
        : number_format((float) $valor, $decimales, ',', '.').($sufijo ? ' '.$sufijo : '');
    $config = [
        'tipo' => $tipo,
        'horizontal' => (bool) $horizontal,
        'apilado' => (bool) $apilado,
        'etiquetas' => array_values($etiquetas),
        'series' => array_map(fn (array $serie): array => [
            'nombre' => $serie['nombre'],
            'valores' => array_map(fn ($valor) => $valor === null ? null : (float) $valor, array_values($serie['valores'])),
            'color' => $serie['color'],
        ], array_values($series)),
        'formato' => ['decimales' => $decimales, 'sufijo' => $sufijo],
    ];
@endphp

<section {{ $attributes->class(['ag-card', 'ag-card--padded', 'ag-grafico']) }}>
    <header class="ag-card__head">
        <div>
            <h2 class="ag-card__title" id="{{ $id }}-titulo">{{ $titulo }}</h2>
            @if ($ayuda)
                <p class="ag-grafico__ayuda">{{ $ayuda }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="ag-grafico__acciones">{{ $actions }}</div>
        @endisset
    </header>

    @if (! $hayDatos)
        <p class="ag-grafico__vacio">{{ $vacio ?? __('ui.grafico.vacio') }}</p>
    @else
        <div
            class="ag-grafico__marco {{ $horizontal ? 'ag-grafico__marco--horizontal' : '' }}"
            data-ag-grafico="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
            data-ag-grafico-estado="cargando"
            @if ($horizontal) style="--ag-grafico-filas: {{ count($etiquetas) }}" @endif
        >
            <canvas class="ag-grafico__lienzo" role="img" aria-labelledby="{{ $id }}-titulo" aria-describedby="{{ $id }}-datos">{{ __('ui.grafico.alternativa') }}</canvas>
            <p class="ag-grafico__estado ag-grafico__estado--cargando" role="status">{{ __('ui.grafico.cargando') }}</p>
            <p class="ag-grafico__estado ag-grafico__estado--error">{{ __('ui.grafico.error') }}</p>
        </div>

        <details class="ag-grafico__datos" id="{{ $id }}-datos">
            <summary>{{ __('ui.grafico.ver_datos') }}</summary>
            <div class="ag-tablero__scroll">
                <table class="ag-tablero__tabla">
                    <caption class="ag-grafico__caption">{{ $titulo }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ $categoria }}</th>
                            @foreach ($series as $serie)
                                <th scope="col" class="ag-tablero__num">{{ $serie['nombre'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($etiquetas as $i => $etiqueta)
                            <tr>
                                <th scope="row">{{ $etiqueta }}</th>
                                @foreach ($series as $serie)
                                    <td class="ag-tablero__num">{{ $texto($serie['valores'][$i] ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</section>
