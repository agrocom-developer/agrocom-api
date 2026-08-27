<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Pivote usuario↔rol, modelo Eloquent propio — NO pivote implícito de
 * Laravel (HU-01): revocar un rol es un soft delete de la fila (auditoría
 * de quién lo asignó/revocó), nunca un DELETE. Se navega vía `HasMany`
 * (ver {@see SecUser::asignacionesDeRol()}), no `belongsToMany()->using()`
 * — ver esa nota para el porqué. Modelo normal, sin `AsPivot`: no hace
 * falta, esa relación no se usa acá.
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_role
 */
class SecUserRole extends ModeloDominio
{
    protected $table = 'sec_user_role';

    /** @var list<string> */
    protected $fillable = [
        'id_user',
        'id_role',
    ];
}
