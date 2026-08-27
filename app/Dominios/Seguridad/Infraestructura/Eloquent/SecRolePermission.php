<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Pivote rol↔permiso, modelo Eloquent propio — NO pivote implícito de
 * Laravel (HU-01): quitar un permiso de un rol es un soft delete de la
 * fila (auditoría de quién lo otorgó/quitó), nunca un DELETE.
 *
 * Extiende `ModeloDominio` (no la clase `Pivot` de Laravel) para heredar
 * soft delete + `created_by`/`updated_by`. Se navega vía `HasMany` (ver
 * {@see SecRole::asignacionesDePermiso()}), no `belongsToMany()->using()`:
 * el stub de tipos de `BelongsToMany::using()` exige un pivote subtipo de
 * `Pivot`, incompatible con extender `ModeloDominio`. Modelo normal en todo
 * lo demás — PK propia `id` autoincremental, sin `AsPivot`.
 *
 * @property int $id
 * @property int $id_role
 * @property int $id_permission
 */
class SecRolePermission extends ModeloDominio
{
    protected $table = 'sec_role_permission';

    /** @var list<string> */
    protected $fillable = [
        'id_role',
        'id_permission',
    ];
}
