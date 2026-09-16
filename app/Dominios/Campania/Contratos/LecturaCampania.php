<?php

namespace App\Dominios\Campania\Contratos;

/**
 * Frontera de lectura de Campania hacia otros módulos (ADR 0003, regla 2):
 * `Comercial\Aplicacion\CrearContrato`/`ActualizarContrato` y
 * `Finanzas\Aplicacion\CrearGasto` necesitan saber si la campaña elegida
 * admite imputaciones (no está `cerrada`), sin importar el modelo Eloquent
 * `Campania` ni reimplementar esa lectura con `DB::table` en cada módulo
 * consumidor.
 */
interface LecturaCampania
{
    /** `null` si la campaña no existe. */
    public function obtener(int $campaniaId): ?DatosCampania;

    /**
     * Todo el catálogo, para poblar selects (`ContratosController`: alta,
     * edición y filtro del listado) — mismo motivo que `obtener()`: sin
     * este método, el consumidor cae en `DB::table('cpn_campanias')`
     * directo, la violación de ADR 0003 regla 2 que el arch test no ve
     * (no deja rastro de `Node\Name` para el analizador AST) porque el
     * nombre de tabla viaja como string, no como referencia a clase — ver
     * el mismo caso ya corregido en `CampaniasController::resumenCampania()`.
     *
     * @return list<DatosCampania> ordenadas por código
     */
    public function todas(): array;
}
