<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subrubro de gasto (espec §4.4, línea 144; HU-33, tarea 47), hijo directo de
 * {@see Rubro} dentro del mismo módulo — `belongsTo` real, no una FK plana:
 * ADR 0003 regla 3 solo restringe relaciones CRUZANDO módulos.
 *
 * Sin `tipo_imputacion` (`directo_dron`/`directo_vehiculo`/`compartido` en la
 * especificación completa): recortado en esta tarea por falta de módulo
 * `Vehículo` — ver el docblock de la migración
 * `2026_09_03_100002_create_fin_subrubros_table`.
 *
 * @property int $id
 * @property int $rubro_id
 * @property string $nombre
 */
class Subrubro extends ModeloDominio
{
    protected $table = 'fin_subrubros';

    /** @var list<string> */
    protected $fillable = [
        'rubro_id',
        'nombre',
    ];

    /** @return BelongsTo<Rubro, $this> */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class, 'rubro_id');
    }
}
