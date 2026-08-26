<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote del campo (espec §4.1, tabla com_lotes). Hectáreas en DECIMAL — las
 * hectáreas son dinero (invariante 6); la geometría es GeoJSON en JSONB, se
 * guarda y se dibuja, no se consulta espacialmente (sin PostGIS en v1).
 *
 * @property int $id
 * @property int $campo_id
 * @property string $codigo
 * @property string $hectareas
 * @property array<string, mixed>|null $geometria
 * @property string|null $restricciones
 */
class Lote extends ModeloDominio
{
    protected $table = 'com_lotes';

    /** @var list<string> */
    protected $fillable = [
        'campo_id',
        'codigo',
        'hectareas',
        'geometria',
        'restricciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hectareas' => 'decimal:2',
            'geometria' => 'array',
        ];
    }

    /** @return BelongsTo<Campo, $this> */
    public function campo(): BelongsTo
    {
        return $this->belongsTo(Campo::class, 'campo_id');
    }
}
