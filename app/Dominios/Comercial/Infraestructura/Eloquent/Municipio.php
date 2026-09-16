<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo cerrado de municipios de Bolivia (adenda 16/9/2026 a ADR 0018
 * punto 1, tabla com_municipios) — hoja de la cadena
 * Departamento → Provincia → Municipio. `Localidad` NO tiene catálogo propio:
 * sigue siendo texto libre en `com_propiedades`, dentro del municipio
 * elegido (no hay datos de esa granularidad en el dump de origen).
 *
 * @property int $id
 * @property int $provincia_id
 * @property string $nombre
 */
class Municipio extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_municipios';

    /** @var list<string> */
    protected $fillable = [
        'provincia_id',
        'nombre',
    ];

    /** @return BelongsTo<Provincia, $this> */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }
}
