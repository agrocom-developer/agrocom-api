{{--
    Atom: texto-legal (documentos legales públicos)
    Pinta un texto de `lang/es/legal.php` y resalta el marcador `[POR DEFINIR]`
    con un <mark>. El texto se parte alrededor del marcador y cada trozo se
    escapa por separado: no hay HTML crudo. Sin espacios entre los trozos,
    para que el punto o la coma que siguen al marcador no queden separados
    (PHP se come el salto de línea que sigue a cada directiva; `@endif@endforeach`
    pegados no compila: Blade no reconoce una directiva pegada a otra).

    Props:
    - texto (string): el texto ya traducido y con sus marcadores `:x` resueltos.
--}}
@props([
    'texto',
])

@php
    $marca = '[POR DEFINIR]';
    $trozos = preg_split('/(\[POR DEFINIR\])/', (string) $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
@endphp
@foreach ($trozos as $trozo)@if ($trozo === $marca)<mark class="ag-legal__por-definir">{{ $trozo }}</mark>@else{{ $trozo }}@endif
@endforeach
