<?php

namespace App\Dominios\Campania\Contratos;

/**
 * Frontera de lectura de Campania hacia otros módulos (ADR 0003, regla 2):
 * `Comercial\Aplicacion\CrearContrato`/`ActualizarContrato` y
 * `Finanzas\Aplicacion\CrearGasto` necesitan saber si la campaña elegida es
 * del cliente correcto y si admite imputaciones (no está `cerrada`), sin
 * importar el modelo Eloquent `Campania` ni reimplementar esa lectura con
 * `DB::table` en cada módulo consumidor.
 */
interface LecturaCampania
{
    /** `null` si la campaña no existe. */
    public function obtener(int $campaniaId): ?DatosCampania;
}
