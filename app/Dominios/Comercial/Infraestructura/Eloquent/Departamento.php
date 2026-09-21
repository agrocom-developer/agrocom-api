<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo cerrado de departamentos de Bolivia (adenda 16/9/2026 a ADR 0018
 * punto 1, tabla com_departamentos) — nivel raíz de la cadena
 * Departamento → Provincia → Municipio que reemplaza el texto libre que
 * tenía `com_propiedades` desde HU-76.
 *
 * `RegistraBitacora`: mismo criterio que `Cultivo` — aunque hoy solo se
 * siembra por seeder, cualquier alta/edición futura queda auditable.
 *
 * @property int $id
 * @property string $nombre
 */
class Departamento extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_departamentos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
    ];

    /** @return HasMany<Provincia, $this> */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'departamento_id');
    }
}
