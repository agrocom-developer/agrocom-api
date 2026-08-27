<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * Pivote usuario↔rol, modelo Eloquent propio — NO pivote implícito de
 * Laravel (HU-01, diseño `modulos-roles` §5): revocar un rol es un soft
 * delete de la fila (auditoría de quién lo asignó/revocó), nunca un DELETE.
 * Ver {@see SecRolePermission} para el porqué de `ModeloDominio` + `AsPivot`
 * en lugar de la clase `Pivot` de Laravel.
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_role
 */
class SecUserRole extends ModeloDominio
{
    use AsPivot;

    protected $table = 'sec_user_role';

    /** @var list<string> */
    protected $fillable = [
        'id_user',
        'id_role',
    ];
}
