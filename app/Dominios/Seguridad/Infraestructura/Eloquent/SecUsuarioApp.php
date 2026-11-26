<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Seguridad\Contratos\TipoCuenta;

/**
 * Modelo del guard `sanctum` (`config/auth.php`): las cuentas de app, de tipo
 * `cliente` o `proveedor` (ADR 0046). Es la contracara de {@see SecUsuarioInterno}:
 * el scope `tipo_cuenta IN ('cliente', 'proveedor')` garantiza que una cuenta de
 * panel jamás autentique en `/api/*`, ni siquiera si alguien le emitiera un token
 * por error (`Guard::hasValidProvider()` compara al dueño del token contra esta clase).
 *
 * No agrega columnas ni tabla propia: es el mismo `sec_user` con un scope.
 */
class SecUsuarioApp extends SecUser
{
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('tipo_app', function ($consulta): void {
            $consulta->whereIn('tipo_cuenta', [TipoCuenta::Cliente->value, TipoCuenta::Proveedor->value]);
        });
    }
}
