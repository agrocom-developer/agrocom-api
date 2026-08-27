<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Seguridad\Dominio\TipoUsuario;

/**
 * Modelo de autenticación del guard `interno` (`config/auth.php`): panel y
 * apps de campo. `sec_user` es una tabla física compartida entre cuentas
 * internas y de portal (columna `type`); este subtipo aplica un global scope
 * `type = 'interno'` para que el guard jamás autentique, ni por error, una
 * cuenta de portal — la separación de guards no puede depender de que cada
 * llamador se acuerde de filtrar (HU-01, diseño `modulos-roles` §6, nota de
 * coordinación sobre `App\Models\User`).
 *
 * No agrega columnas ni tabla propia: es el mismo `sec_user` con un scope,
 * por eso no lleva su propio archivo de migración.
 */
class SecUsuarioInterno extends SecUser
{
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('tipo_interno', function ($consulta): void {
            $consulta->where('type', TipoUsuario::Interno->value);
        });
    }
}
