<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Permiso abstracto del sistema (RBAC), catálogo (ADR 0004; HU-01 diseño
 * `modulos-roles` §2/§5). `code` tiene forma `modulo.entidad.accion`
 * (ej. `seguridad.usuario.crear`), validada con CHECK en la migración.
 *
 * @property int $id
 * @property string $code
 * @property string $description
 * @property bool $state
 */
class SecPermission extends ModeloDominio
{
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
