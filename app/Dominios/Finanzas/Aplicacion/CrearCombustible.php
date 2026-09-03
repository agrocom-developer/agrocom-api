<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;

/**
 * Alta de una carga de combustible (HU-35, tarea 49): "como encargado,
 * quiero registrar el combustible del generador y de los vehículos, para
 * imputarlo a la campaña". Persiste tal cual, sin cálculo: `litros` y
 * `monto` son ambos datos de entrada cargados por el encargado — a
 * diferencia de `CrearGasto`, que sí deriva `monto` de `cantidad ×
 * precio_unitario` (acá no hay columna de precio unitario en el CA
 * esencial, ver el docblock de la migración).
 *
 * Inmutable salvo baja (mismo criterio que `Gasto`/`Anticipo`): sin caso de
 * uso de edición — si está mal, se da de baja (`EliminarCombustible`) y se
 * recarga.
 */
final class CrearCombustible
{
    public function ejecutar(
        string $fecha,
        int $baseId,
        string $destino,
        string $litros,
        string $monto,
        ?string $descripcion,
    ): Combustible {
        return Combustible::query()->create([
            'fecha' => $fecha,
            'base_id' => $baseId,
            'destino' => $destino,
            'litros' => $litros,
            'monto' => $monto,
            'descripcion' => $descripcion,
        ]);
    }
}
