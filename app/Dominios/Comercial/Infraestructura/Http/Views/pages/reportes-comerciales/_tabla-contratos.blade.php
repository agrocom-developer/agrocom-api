{{--
    Partial: tabla de contratos de un grupo del informe de avance (HU-52,
    tarea 75; homogeneizada en la tarea 120). La comparten la pestaña «Por
    cultivo» (contratos de un cultivo) y el acordeón de la pestaña «Por
    cliente» (contratos de un cliente dentro de un cultivo): mismas cuatro
    columnas y misma fila de totales al pie, con `molecules/index-table`.

    Espera:
    - $contratos (list<FilaContratoInforme>): una fila por contrato.
    - $totales (GrupoCultivoInforme|GrupoClienteInforme): el grupo cuyos tres
      totales (`hectareasContratadas`, `hectareasAplicadas`,
      `hectareasAAplicar`) van en la última fila.

    Las hectáreas son texto DECIMAL y se formatean sin pasar por `float`
    (invariante 6). Sin columna de índice ni de acciones: es un desglose de un
    informe, no un listado de objetos. Con la cabecera oculta (móvil) cada cifra
    lleva su rótulo delante (`ag-reportes-comerciales__etiqueta`, solo visible
    ahí); en escritorio los rótulos son los encabezados de la tabla.
--}}
@use('App\Dominios\Comercial\Infraestructura\Http\FormatoMonto')

<x-molecules.index-table columns="1.4fr 1fr 1fr 1fr">
    <x-slot:head>
        <span role="columnheader">{{ __('comercial.reportes_comerciales.tabla.contrato') }}</span>
        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.reportes_comerciales.tabla.hectareas_contratadas') }}</span>
        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.reportes_comerciales.tabla.hectareas_aplicadas') }}</span>
        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.reportes_comerciales.tabla.hectareas_a_aplicar') }}</span>
    </x-slot:head>

    @foreach ($contratos as $fila)
        <div class="ag-index-table__row" role="row">
            <span role="cell">{{ __('comercial.reportes_comerciales.tabla.contrato_valor', ['id' => $fila->contratoId]) }}</span>
            <span role="cell" class="ag-index-table__cifra">
                <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_contratadas') }}</span>
                {{ FormatoMonto::decimal($fila->hectareasContratadas) }}
            </span>
            <span role="cell" class="ag-index-table__cifra">
                <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_aplicadas') }}</span>
                {{ FormatoMonto::decimal($fila->hectareasAplicadas) }}
            </span>
            <span role="cell" class="ag-index-table__cifra">
                <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_a_aplicar') }}</span>
                {{ FormatoMonto::decimal($fila->hectareasAAplicar) }}
            </span>
        </div>
    @endforeach

    <div class="ag-index-table__row ag-reportes-comerciales__totales" role="row">
        <span role="cell">{{ __('comercial.reportes_comerciales.tabla.total') }}</span>
        <span role="cell" class="ag-index-table__cifra">
            <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_contratadas') }}</span>
            {{ FormatoMonto::decimal($totales->hectareasContratadas) }}
        </span>
        <span role="cell" class="ag-index-table__cifra">
            <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_aplicadas') }}</span>
            {{ FormatoMonto::decimal($totales->hectareasAplicadas) }}
        </span>
        <span role="cell" class="ag-index-table__cifra">
            <span class="ag-reportes-comerciales__etiqueta">{{ __('comercial.reportes_comerciales.tabla.hectareas_a_aplicar') }}</span>
            {{ FormatoMonto::decimal($totales->hectareasAAplicar) }}
        </span>
    </div>
</x-molecules.index-table>
