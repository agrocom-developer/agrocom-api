{{--
    Partial del dashboard: días efectivos en hacienda del mes, por cuadrilla y
    por propiedad (HU-102, 19/9/2026). Es la cuenta que justifica el gasto
    imputado a cada cuadrilla — cuántos días estuvo y dónde —, y vive ACÁ y no
    en el listado de estadías: esa pantalla es para operar (registrar,
    finalizar), y un total de todo el mes debajo de una tabla paginada queda
    escondido y se lee como si fuera de esa página.

    Dos tarjetas hermanas con las mismas barras que «Pausas por causa»
    (`_barras-pausas`): el ancho de cada barra es su parte del total del mes.

    Espera:
    - $diasHacienda (array{total_dias: float, en_curso: int,
      por_cuadrilla: list<array{nombre: string, dias: float}>,
      por_propiedad: list<array{nombre: string, dias: float}>}): ya ordenado
      de mayor a menor y con los nombres resueltos (ver
      ArmarDashboard::diasEnHacienda()). Las cuentas son las mismas del
      listado: estadías finalizadas cuya entrada cae en el mes.
--}}
@php
    $totalDias = max(0.1, $diasHacienda['total_dias']);
    $aBarras = fn (array $filas, string $tono) => collect($filas)->map(fn (array $fila) => [
        'causa' => $fila['nombre'],
        'horas' => __('seguridad.dashboard.dias_hacienda_valor', ['dias' => number_format($fila['dias'], 1, ',', '.')]),
        'pct' => round($fila['dias'] / $totalDias * 100, 1),
        'tono' => $tono,
    ])->all();
    $notaTotal = __('seguridad.dashboard.dias_hacienda_valor', ['dias' => number_format($diasHacienda['total_dias'], 1, ',', '.')]);
@endphp

<div class="ag-dash__grid ag-dash__grid--par">
    <div class="ag-card ag-card--padded">
        <div class="ag-card__head ag-card__head--flush">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.dias_hacienda_cuadrilla_titulo') }}</h2>
            <span class="ag-dash__mono-note">{{ $notaTotal }}</span>
        </div>
        @if ($diasHacienda['por_cuadrilla'] === [])
            <p class="ag-dash__mono-note">{{ trans_choice('seguridad.dashboard.dias_hacienda_solo_en_curso', $diasHacienda['en_curso'], ['cantidad' => $diasHacienda['en_curso']]) }}</p>
        @else
            @include('seguridad::pages.dashboard._barras-pausas', ['causas' => $aBarras($diasHacienda['por_cuadrilla'], 'primary')])
        @endif
    </div>

    <div class="ag-card ag-card--padded">
        <div class="ag-card__head ag-card__head--flush">
            <h2 class="ag-card__title">{{ __('seguridad.dashboard.dias_hacienda_propiedad_titulo') }}</h2>
            <span class="ag-dash__mono-note">{{ trans_choice('seguridad.dashboard.dias_hacienda_en_curso', $diasHacienda['en_curso'], ['cantidad' => $diasHacienda['en_curso']]) }}</span>
        </div>
        @if ($diasHacienda['por_propiedad'] === [])
            <p class="ag-dash__mono-note">{{ trans_choice('seguridad.dashboard.dias_hacienda_solo_en_curso', $diasHacienda['en_curso'], ['cantidad' => $diasHacienda['en_curso']]) }}</p>
        @else
            @include('seguridad::pages.dashboard._barras-pausas', ['causas' => $aBarras($diasHacienda['por_propiedad'], 'info')])
        @endif
    </div>
</div>
