<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Catálogo de cultivos (HU-48, tarea 71, tabla com_cultivos). Sin relación
 * directa a `Lote`: el cultivo se vincula a un lote solo DENTRO de una
 * campaña, vía `com_lote_campania` (ADR 0015 punto 4) — nunca como atributo
 * propio del lote.
 *
 * `RegistraBitacora`: mismo criterio que `Campo`/`Cliente` — el alta,
 * edición y baja de un cultivo es una mutación de negocio con autor y
 * momento auditables.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class Cultivo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_cultivos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
