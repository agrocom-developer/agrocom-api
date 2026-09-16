<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo cerrado de provincias de Bolivia (adenda 16/9/2026 a ADR 0018
 * punto 1, tabla com_provincias) — nivel intermedio de la cadena
 * Departamento → Provincia → Municipio.
 *
 * @property int $id
 * @property int $departamento_id
 * @property string $nombre
 */
class Provincia extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_provincias';

    /** @var list<string> */
    protected $fillable = [
        'departamento_id',
        'nombre',
    ];

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /** @return HasMany<Municipio, $this> */
    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'provincia_id');
    }
}
