<?php

namespace App\Dominios\Mezclas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Catálogo de productos cargados en el caldo (espec §7, HU-78, tarea 94,
 * revierte CR-01). Ver decisión de módulo y de esquema en
 * `database/migrations/2026_09_14_100007_create_mez_productos_table.php`.
 *
 * @property int $id
 * @property string $nombre
 */
class Producto extends ModeloDominio
{
    use RegistraBitacora;

    /** Prefijo de módulo en el nombre físico (ADR 0011); el global lo pone la conexión. */
    protected $table = 'mez_productos';

    /** @var list<string> */
    protected $fillable = ['nombre'];
}
