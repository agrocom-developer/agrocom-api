{{--
    Parcial: repuestos bajo mínimo, del más crítico al menos.

    Espera: $stock (list<{codigo, descripcion, cantidad, stockMinimo, base}>).
--}}
<div class="ag-card ag-card--padded">
    <div class="ag-card__head ag-card__head--flush">
        <h2 class="ag-card__title ag-dash__stock-title">
            <x-atoms.icon name="inventory_2" size="sm" class="ag-dash__stock-icon" />
            {{ __('seguridad.dashboard.stock_titulo') }}
        </h2>
    </div>
    <div class="ag-dash__stock">
        @foreach ($stock as $item)
            <div class="ag-dash__stock-row">
                <span>
                    {{ $item['descripcion'] }}
                    @if ($item['base'])
                        <span class="ag-dash__mono-note">· {{ $item['base'] }}</span>
                    @endif
                </span>
                <span class="ag-dash__stock-nivel ag-dash__stock-nivel--warning">
                    {{ __('seguridad.dashboard.stock_nivel', [
                        'cantidad' => rtrim(rtrim($item['cantidad'], '0'), '.'),
                        'minimo' => rtrim(rtrim($item['stockMinimo'], '0'), '.'),
                    ]) }}
                </span>
            </div>
        @endforeach
    </div>
    <a class="ag-dash__link ag-dash__stock-accion" href="{{ route('panel.stock.index') }}">
        {{ __('seguridad.dashboard.stock_accion') }}
    </a>
</div>
