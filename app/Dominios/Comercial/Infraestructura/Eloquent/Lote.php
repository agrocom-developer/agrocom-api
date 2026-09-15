<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de la propiedad (espec §4.1, tabla com_lotes; ADR 0020 — cuelga
 * directo de `Propiedad`, sin el nivel intermedio `Campo`). Hectáreas en
 * DECIMAL — las hectáreas son dinero (invariante 6); la geometría es GeoJSON
 * en JSONB, se guarda y se dibuja, no se consulta espacialmente (sin PostGIS
 * en v1).
 *
 * `RegistraBitacora` (HU-24, tarea 35): mismo criterio que {@see Propiedad}.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string $hectareas
 * @property array<string, mixed>|null $geometria
 * @property string|null $restricciones
 * @property string|null $desnivel
 * @property string|null $limpieza
 */
class Lote extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_lotes';

    /** @var list<string> */
    protected $fillable = [
        'propiedad_id',
        'codigo',
        'hectareas',
        'geometria',
        'restricciones',
        'desnivel',
        'limpieza',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas' => 'decimal:2',
            'geometria' => 'array',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }
}
