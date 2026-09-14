<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Propiedad del cliente (ADR 0018, tabla com_propiedades): nivel de negocio
 * entre `Cliente` y `Campo` — un cliente tiene varias propiedades, y una
 * propiedad puede estar dividida en más de un campo físico delimitado (caso
 * "Gamelera": dos mitades de 1500 ha separadas por una carretera, cada una
 * con su propia campaña).
 *
 * `RegistraBitacora` (invariante 9 de CLAUDE.md): mismo criterio que
 * {@see Campo} — el alta, edición y baja de una propiedad es una mutación de
 * negocio con autor y momento auditables.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $ubicacion
 * @property string|null $departamento
 * @property string|null $municipio
 * @property string|null $localidad
 * @property string|null $latitud
 * @property string|null $longitud
 */
class Propiedad extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_propiedades';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'ubicacion',
        'departamento',
        'municipio',
        'localidad',
        'latitud',
        'longitud',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<Campo, $this> */
    public function campos(): HasMany
    {
        return $this->hasMany(Campo::class, 'propiedad_id');
    }
}
