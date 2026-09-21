<?php

namespace App\Dominios\Inventario\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `inv_repuestos_codigo_unico` (HU-36, tarea 52): un código no puede
 * repetirse entre repuestos ACTIVOS (uno dado de baja lógica no bloquea el
 * re-alta con el mismo código). El caso de uso que persiste `Repuesto`
 * captura la `QueryException` y la relanza como esta excepción — nunca deja
 * propagarse el 500 crudo del motor de base de datos. Mismo criterio que
 * `BateriaDuplicada` en `Mantenimiento`.
 */
final class RepuestoDuplicado extends RuntimeException
{
    public static function porCodigo(string $codigo): self
    {
        return new self(Texto::de('inventario.errores.repuesto_duplicado', ['codigo' => $codigo]));
    }
}
