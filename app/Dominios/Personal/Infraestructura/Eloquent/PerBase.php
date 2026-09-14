<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Base operativa de campo (espec §4.2, tabla per_bases), alcance mínimo
 * para HU-01 (ADR 0011, extensión 26/8/2026, punto 6).
 *
 * `RegistraBitacora` (HU-26, tarea 37): mismo criterio que `Cliente` en
 * Comercial — el esquema no la marca como catálogo de rol/permiso
 * (`tests/Unit/BitacoraAuditoriaTest.php` no la exige), pero el alta,
 * edición y baja de una base es una mutación de negocio con autor y momento
 * auditables, y el criterio de aceptación de esta HU lo pide explícito.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $ubicacion
 * @property string|null $latitud
 * @property string|null $longitud
 */
class PerBase extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_bases';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'ubicacion',
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

    /** @return HasMany<PerPersona, $this> */
    public function personas(): HasMany
    {
        return $this->hasMany(PerPersona::class, 'base_id');
    }
}
