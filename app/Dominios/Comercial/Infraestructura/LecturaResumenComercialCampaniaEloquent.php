<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\DatosResumenComercialCampania;
use App\Dominios\Comercial\Contratos\LecturaResumenComercialCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use Brick\Math\BigDecimal;

/**
 * Implementación Eloquent del resumen comercial de campaña. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaContratoEloquent}: no es un modelo, es el adaptador que el
 * `ServiceProvider` liga al contrato.
 */
final class LecturaResumenComercialCampaniaEloquent implements LecturaResumenComercialCampania
{
    public function resumen(int $campaniaId): DatosResumenComercialCampania
    {
        $contratos = Contrato::query()->where('campania_id', $campaniaId)->get(['id', 'hectareas_contratadas']);
        $contratoIds = $contratos->pluck('id')->all();

        $facturado = $contratoIds === []
            ? BigDecimal::zero()
            : Factura::query()
                ->whereIn('contrato_id', $contratoIds)
                ->get('monto')
                ->reduce(fn (BigDecimal $acumulado, Factura $factura) => $acumulado->plus($factura->monto), BigDecimal::zero());

        $hectareasContratadas = $contratos->reduce(
            fn (BigDecimal $acumulado, Contrato $contrato) => $acumulado->plus($contrato->hectareas_contratadas),
            BigDecimal::zero(),
        );

        return new DatosResumenComercialCampania(
            facturado: (string) $facturado,
            totalContratos: $contratos->count(),
            hectareasContratadas: (string) $hectareasContratadas,
            contratoIds: $contratoIds,
        );
    }
}
