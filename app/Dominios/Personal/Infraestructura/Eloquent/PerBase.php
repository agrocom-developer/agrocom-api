<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Base operativa de campo (espec §4.2, tabla per_bases), alcance mínimo
 * para HU-01 (ADR 0011, extensión 26/8/2026, punto 6).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $ubicacion
 */
class PerBase extends ModeloDominio
{
    protected $table = 'per_bases';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'ubicacion',
    ];

    /** @return HasMany<PerPersona, $this> */
    public function personas(): HasMany
    {
        return $this->hasMany(PerPersona::class, 'base_id');
    }
}
