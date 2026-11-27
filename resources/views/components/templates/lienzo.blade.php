{{--
    Template: lienzo
    Una composición de bloques sobre un fondo gris, como un lienzo de modelo de negocio: bloques
    blancos con separación pequeña y uniforme, un bloque protagonista, columnas de distinta
    anchura y filas de distinta altura. Es recursiva: un lienzo es una fila o una columna de
    bloques, y cada bloque puede contener otro lienzo.

    - `direccion="fila"`: los hijos van uno junto al otro; `fracciones` reparte el ANCHO.
    - `direccion="columna"`: los hijos van uno bajo el otro; `fracciones` reparte el ALTO.
    - `fracciones`: números separados por espacio sobre una base de 12 («4 8», «6 6», «12»).
      Se pintan como `fr` con CSS grid: ninguna altura es fija en píxeles.
    - `raiz`: el lienzo más externo. Pone el fondo gris, mide su ancho (container query) y le da
      su alto (`alto` unidades de `--ag-space-8`) para que las filas por fracción tengan sobre qué
      repartirse. Los lienzos anidados toman el alto de su bloque.

    Responsive: mientras el lienzo mida menos de 60rem de ancho (celular, o escritorio con el
    menú abierto y poco contenido) NO reparte nada: todo apila en una columna en el orden del
    código, así que el bloque principal va primero. Desde 60rem aplica las fracciones, y las
    gráficas de dentro llenan su bloque y se redibujan con él.

    Slot: `$slot` con los hijos, cada uno envuelto en `<x-molecules.lienzo-bloque>`.
    Esquema y ejemplo: docs/diseno/sistema_diseno_panel.md (§ Lienzo).
--}}
@props([
    'direccion' => 'fila',
    'fracciones' => '12',
    'raiz' => false,
    'alto' => 20,
])

@php
    abort_unless(in_array($direccion, ['fila', 'columna'], true) && preg_match('/^\d{1,2}( \d{1,2})*$/', $fracciones) === 1, 500, 'Lienzo mal armado.');

    $pistas = collect(explode(' ', $fracciones))->map(fn (string $n): string => "minmax(0, {$n}fr)")->implode(' ');
    $estilo = "--ag-lienzo-fracciones: {$pistas};".($raiz ? ' --ag-lienzo-unidades: '.(int) $alto.';' : '');
@endphp

@if ($raiz)
    <div class="ag-lienzo-marco">
@endif
<div {{ $attributes->class(['ag-lienzo', "ag-lienzo--{$direccion}", 'ag-lienzo--raiz' => $raiz]) }} style="{{ $estilo }}">
    {{ $slot }}
</div>
@if ($raiz)
    </div>
@endif
