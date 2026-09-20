<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de accesorios que una cuadrilla lleva al campo (tarea
 * "cuadrillas-estadias", pedido del dueño 19/9/2026): machete, palas,
 * linternas… Ver el docblock de la migración `create_per_accesorios_table`
 * para el criterio de unicidad (sin distinguir mayúsculas ni espacios).
 *
 * `RegistraBitacora`: mismo criterio que `Cultivo`/`PerBase` — alta, edición
 * y baja de un accesorio del catálogo es una mutación de negocio auditable.
 *
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 */
class Accesorio extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_accesorios';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<EquipoAccesorio, $this> */
    public function equipoAccesorios(): HasMany
    {
        return $this->hasMany(EquipoAccesorio::class, 'accesorio_id');
    }
}
