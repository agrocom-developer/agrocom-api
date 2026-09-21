<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Contratos\DatosCombustibleRecurso;
use App\Dominios\Finanzas\Contratos\LecturaCombustiblePorRecurso;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use Brick\Math\BigDecimal;

/**
 * Implementación Eloquent de {@see LecturaCombustiblePorRecurso}. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaAnticiposPorPersonaEloquent}: no es un modelo, es el adaptador
 * que el `ServiceProvider` liga al contrato.
 *
 * Los litros se suman con `BigDecimal` sobre los `litros` DECIMAL (`decimal:2`
 * devuelve `string`), nunca con `float` (invariante 6). El soft delete de
 * `ModeloDominio` deja afuera las cargas dadas de baja.
 */
final class LecturaCombustiblePorRecursoEloquent implements LecturaCombustiblePorRecurso
{
    public function deRecurso(string $recursoTipo, int $recursoId): DatosCombustibleRecurso
    {
        $cargas = Combustible::query()
            ->where('recurso_tipo', $recursoTipo)
            ->where('recurso_id', $recursoId)
            ->get('litros');

        $litros = $cargas->reduce(
            fn (BigDecimal $acumulado, Combustible $carga): BigDecimal => $acumulado->plus($carga->litros),
            BigDecimal::zero(),
        );

        return new DatosCombustibleRecurso(
            cargas: $cargas->count(),
            litros: (string) $litros->toScale(2),
        );
    }
}
