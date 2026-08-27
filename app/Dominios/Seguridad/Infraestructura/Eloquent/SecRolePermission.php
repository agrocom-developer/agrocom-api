<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * Pivote rol↔permiso, modelo Eloquent propio — NO pivote implícito de
 * Laravel (HU-01, diseño `modulos-roles` §5): quitar un permiso de un rol es
 * un soft delete de la fila (auditoría de quién lo otorgó/quitó), nunca un
 * DELETE.
 *
 * Extiende `ModeloDominio` (no la clase `Pivot` de Laravel) para heredar
 * soft delete + `created_by`/`updated_by`; usa el trait `AsPivot` para que
 * `belongsToMany(...)->using(self::class)` hidrate y persista correctamente.
 * `Pivot` desactiva `$incrementing` por defecto pensando en claves
 * compuestas — acá no aplica: la tabla tiene su propia PK `id` autoincremental
 * (heredada del default de `Model`), así que se comporta como un modelo
 * normal en todo lo demás.
 *
 * @property int $id
 * @property int $id_role
 * @property int $id_permission
 */
class SecRolePermission extends ModeloDominio
{
    use AsPivot;

    protected $table = 'sec_role_permission';

    /** @var list<string> */
    protected $fillable = [
        'id_role',
        'id_permission',
    ];
}
