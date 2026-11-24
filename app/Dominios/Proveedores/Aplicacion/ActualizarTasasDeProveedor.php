<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Contratos\ReputacionDeProveedores;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Brick\Math\BigDecimal;

/**
 * Guarda las tasas que calculó la asignación. Solo escribe (y por lo tanto solo
 * deja bitácora) si algún valor cambió: el cálculo se repite tras cada oferta
 * respondida y casi siempre da lo mismo.
 */
final class ActualizarTasasDeProveedor implements ReputacionDeProveedores
{
    public function actualizarTasas(int $proveedorId, string $tasaAceptacion, string $tasaCancelacion): void
    {
        $proveedor = Proveedor::query()->find($proveedorId);

        if ($proveedor === null) {
            return;
        }

        $aceptacion = BigDecimal::of($tasaAceptacion)->toScale(4);
        $cancelacion = BigDecimal::of($tasaCancelacion)->toScale(4);

        if (BigDecimal::of((string) $proveedor->tasa_aceptacion)->isEqualTo($aceptacion)
            && BigDecimal::of((string) $proveedor->tasa_cancelacion)->isEqualTo($cancelacion)) {
            return;
        }

        $proveedor->forceFill([
            'tasa_aceptacion' => (string) $aceptacion,
            'tasa_cancelacion' => (string) $cancelacion,
        ])->save();
    }
}
