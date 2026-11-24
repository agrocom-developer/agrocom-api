<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Contratos\CalificacionDeProveedores;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Brick\Math\BigDecimal;

/**
 * Guarda la calificación que calculó el módulo de calificaciones. Solo escribe
 * (y por lo tanto solo deja bitácora) si el promedio o el conteo cambiaron.
 */
final class ActualizarCalificacionDeProveedor implements CalificacionDeProveedores
{
    public function actualizar(int $proveedorId, string $promedio, int $conteo): void
    {
        $proveedor = Proveedor::query()->find($proveedorId);

        if ($proveedor === null) {
            return;
        }

        $promedio = BigDecimal::of($promedio)->toScale(2);

        if (BigDecimal::of((string) $proveedor->calificacion_promedio)->isEqualTo($promedio)
            && (int) $proveedor->calificaciones_conteo === $conteo) {
            return;
        }

        $proveedor->forceFill([
            'calificacion_promedio' => (string) $promedio,
            'calificaciones_conteo' => $conteo,
        ])->save();
    }
}
