<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Comercial\Dominio\CicloVidaCultivo;
use App\Dominios\Comercial\Dominio\TipoCultivo;
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
 * `tipo_cultivo`/`ciclo_vida` cast a enum (ampliación 16/9/2026): mismo
 * criterio que `Cliente::tipo_persona` — sin cast, `$cultivo->tipo_cultivo`
 * queda como string plano y cualquier vista que asuma `?->value` rompe
 * (bug ya encontrado una vez en `Cliente`, ver su docblock).
 *
 * `notas_agronomicas` es informativo (memoria "la mezcla es del cliente"):
 * ningún caso de uso de este módulo ni el motor de sesiones lo interpreta.
 *
 * @property int $id
 * @property string $nombre_comun
 * @property string|null $nombre_cientifico
 * @property TipoCultivo|null $tipo_cultivo
 * @property CicloVidaCultivo|null $ciclo_vida
 * @property string|null $notas_agronomicas
 * @property bool $activo
 */
class Cultivo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_cultivos';

    /** @var list<string> */
    protected $fillable = [
        'nombre_comun',
        'nombre_cientifico',
        'tipo_cultivo',
        'ciclo_vida',
        'notas_agronomicas',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_cultivo' => TipoCultivo::class,
            'ciclo_vida' => CicloVidaCultivo::class,
            'activo' => 'boolean',
        ];
    }
}
