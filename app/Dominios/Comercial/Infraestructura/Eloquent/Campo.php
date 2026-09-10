<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campo físico de una propiedad (ADR 0018, tabla com_campos).
 *
 * Cuelga de {@see Propiedad}, no directo de `Cliente` (perdió `cliente_id`,
 * ganó `propiedad_id` y `geometria` — el perímetro del campo, mismo formato
 * GeoJSON que {@see Lote::$geometria}): una propiedad puede estar dividida en
 * más de un campo físico delimitado, caso que antes de este ADR no se podía
 * representar porque "propiedad" y "campo" eran la misma fila.
 *
 * `RegistraBitacora` (HU-24, tarea 35): mismo criterio que {@see Cliente} —
 * el esquema no lo marca como catálogo de rol/permiso
 * (`tests/Unit/BitacoraAuditoriaTest.php` no lo exige), pero el alta,
 * edición y baja de un campo es una mutación de negocio con autor y momento
 * auditables, y el criterio de aceptación de esta HU lo pide explícito.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property array<string, mixed>|null $geometria
 */
class Campo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_campos';

    /** @var list<string> */
    protected $fillable = [
        'propiedad_id',
        'nombre',
        'geometria',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'geometria' => 'array',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'campo_id');
    }
}
