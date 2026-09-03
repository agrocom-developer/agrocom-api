<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Aplicacion\CrearGasto;
use App\Dominios\Finanzas\Contratos\EscrituraGastoMantenimiento;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Subrubro;

/**
 * Implementación de {@see EscrituraGastoMantenimiento} sobre
 * `Finanzas/Aplicacion/CrearGasto` (HU-37, tarea 53): no reescribe el
 * cálculo de `monto` — le pasa `cantidad='1'` y `precio_unitario=$montoTotal`
 * para que `monto = 1 × montoTotal` salga de la misma cuenta con
 * `Brick\Math\BigDecimal` que usa cualquier otro gasto.
 *
 * Rubro/subrubro se resuelven por nombre contra el catálogo sembrado por
 * `FinanzasRubrosSeeder` (tarea 47) — no crea rubros nuevos; si no existen,
 * `firstOrFail()` deja ver el problema de seed en vez de fallar en silencio.
 * `base_id`/`trabajo_id` van `null`: `man_ordenes_mantenimiento` no tiene
 * columna de base propia en el alcance de esta HU.
 */
final class EscrituraGastoMantenimientoEloquent implements EscrituraGastoMantenimiento
{
    private const NOMBRE_RUBRO = 'Mantenimiento de equipos';

    private const NOMBRE_SUBRUBRO = 'Repuestos';

    public function __construct(private readonly CrearGasto $crearGasto) {}

    public function registrarPorCierreDeOrden(string $fecha, string $montoTotal): int
    {
        $rubro = Rubro::query()->where('nombre', self::NOMBRE_RUBRO)->firstOrFail();
        $subrubro = Subrubro::query()
            ->where('rubro_id', $rubro->id)
            ->where('nombre', self::NOMBRE_SUBRUBRO)
            ->firstOrFail();

        $gasto = $this->crearGasto->ejecutar(
            fecha: $fecha,
            rubroId: $rubro->id,
            subrubroId: $subrubro->id,
            cantidad: '1',
            precioUnitario: $montoTotal,
            baseId: null,
            trabajoId: null,
            comprobante: null,
        );

        return $gasto->id;
    }
}
