<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Contratos\DatosAnticiposPersona;
use App\Dominios\Finanzas\Contratos\LecturaAnticiposPorPersona;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Brick\Math\BigDecimal;

/**
 * Implementación Eloquent de {@see LecturaAnticiposPorPersona}. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaGastoPorCampaniaEloquent}: no es un modelo, es el adaptador que
 * el `ServiceProvider` liga al contrato.
 *
 * El total se suma con `BigDecimal` sobre los `monto` DECIMAL (`decimal:2`
 * devuelve `string`), nunca con `float` (invariante 6). El soft delete de
 * `ModeloDominio` deja afuera los anticipos dados de baja.
 */
final class LecturaAnticiposPorPersonaEloquent implements LecturaAnticiposPorPersona
{
    public function dePersona(int $personaId): DatosAnticiposPersona
    {
        $anticipos = Anticipo::query()->where('persona_id', $personaId)->get('monto');

        $total = $anticipos->reduce(
            fn (BigDecimal $acumulado, Anticipo $anticipo): BigDecimal => $acumulado->plus($anticipo->monto),
            BigDecimal::zero(),
        );

        return new DatosAnticiposPersona(
            cantidad: $anticipos->count(),
            montoTotal: (string) $total->toScale(2),
        );
    }
}
