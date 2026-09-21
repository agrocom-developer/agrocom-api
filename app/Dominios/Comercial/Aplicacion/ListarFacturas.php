<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listado de facturas emitidas, paginado (HU-31, tarea 45; filtros y
 * resumen, tarea 120). Solo lectura. `contrato.cliente` va precargado: la
 * vista pinta el cliente sin una consulta extra por fila (checklist de
 * `guia_pantalla_panel.md` §8).
 *
 * Filtros: `$busqueda` por razón social del cliente o por número de acta
 * (solo si es un número); `$clienteId`, `$contratoId` y el período de
 * emisión (`$desde`/`$hasta`, `Y-m-d`, ambos inclusive). Una factura no tiene
 * estado —es un snapshot inmutable—, así que no hay filtro por estado.
 *
 * {@see resumen()} responde al MISMO filtro que {@see ejecutar()}: la cifra de
 * cabecera cuadra a centavo con la columna de la tabla.
 */
final class ListarFacturas
{
    /** @return LengthAwarePaginator<int, Factura> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $clienteId = null,
        ?int $contratoId = null,
        ?string $desde = null,
        ?string $hasta = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($busqueda, $clienteId, $contratoId, $desde, $hasta)
            ->with('contrato.cliente')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Cifras de cabecera del listado (KPI): cuántas facturas hay, cuánto suman
     * en monto y en hectáreas. Sumas sobre `BigDecimal`, nunca `float`
     * (invariante 6), redondeadas a escala 2 como se guardan.
     *
     * @return array{cantidad: int, monto: string, hectareas: string}
     */
    public function resumen(
        ?string $busqueda = null,
        ?int $clienteId = null,
        ?int $contratoId = null,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        $facturas = $this->consulta($busqueda, $clienteId, $contratoId, $desde, $hasta)
            ->get(['monto', 'hectareas_facturadas']);

        $monto = BigDecimal::zero();
        $hectareas = BigDecimal::zero();

        foreach ($facturas as $factura) {
            $monto = $monto->plus($factura->monto);
            $hectareas = $hectareas->plus($factura->hectareas_facturadas);
        }

        return [
            'cantidad' => $facturas->count(),
            'monto' => (string) $monto->toScale(2, RoundingMode::HalfUp),
            'hectareas' => (string) $hectareas->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /** @return Builder<Factura> */
    private function consulta(?string $busqueda, ?int $clienteId, ?int $contratoId, ?string $desde, ?string $hasta): Builder
    {
        return Factura::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->where(function (Builder $sub) use ($busqueda): void {
                    $sub->whereHas(
                        'contrato.cliente',
                        fn (Builder $cliente) => BusquedaTexto::aplicar($cliente, ['razon_social'], (string) $busqueda),
                    );

                    if (ctype_digit((string) $busqueda)) {
                        $sub->orWhere('acta_id', (int) $busqueda);
                    }
                }),
            )
            ->when(
                $clienteId !== null,
                fn (Builder $consulta) => $consulta->whereHas('contrato', fn (Builder $contrato) => $contrato->where('cliente_id', $clienteId)),
            )
            ->when($contratoId !== null, fn (Builder $consulta) => $consulta->where('contrato_id', $contratoId))
            ->when($desde !== null, fn (Builder $consulta) => $consulta->whereDate('fecha_emision', '>=', $desde))
            ->when($hasta !== null, fn (Builder $consulta) => $consulta->whereDate('fecha_emision', '<=', $hasta));
    }
}
