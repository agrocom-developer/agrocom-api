<?php

namespace App\Dominios\Operaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;

/**
 * Catálogo mínimo de dron (HU-07, tarea 20; ver docblock de
 * `database/migrations/2026_09_01_100013_create_ope_drones_table.php` y
 * runs/20.md). Sin ciclo de vida propio (sin uso acumulado, sin historial de
 * mantenimiento) — cuando exista el módulo `Mantenimiento`/`Inventario`
 * (ADR 0011 punto 3), esa es la tabla que absorbe esta.
 *
 * @property int $id
 * @property string $identificador
 */
class Dron extends ModeloDominio
{
    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'ope_drones';

    /** @var list<string> */
    protected $fillable = [
        'identificador',
    ];
}
