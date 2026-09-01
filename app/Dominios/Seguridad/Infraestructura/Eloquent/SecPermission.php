<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Permiso abstracto del sistema (RBAC), catálogo (ADR 0004; HU-01 diseño
 * `modulos-roles` §2/§5). `code` tiene forma `modulo.entidad.accion`
 * (ej. `seguridad.usuario.crear`), validada con CHECK en la migración.
 *
 * Lleva {@see RegistraBitacora} (ADR 0007, invariante 9 de CLAUDE.md): es
 * catálogo de control de acceso, con la columna `state` que activa/desactiva
 * el permiso para todo el sistema.
 *
 * @property int $id
 * @property string $code
 * @property string $description
 * @property bool $state
 */
class SecPermission extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_permission';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'description',
        'state',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => 'boolean',
        ];
    }
}
