<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departamento de Bolivia (`sol_departamentos`, ADR 0045 punto 3): catálogo oficial
 * de solo lectura con el código del INE (dos dígitos). No lleva geometría, centro
 * ni parámetros, y nadie lo edita desde el panel: lo siembra `UbicacionesSeeder`.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 */
class Departamento extends ModeloDominio
{
    protected $table = 'sol_departamentos';

    /** @var list<string> */
    protected $fillable = ['codigo', 'nombre'];

    /** @return HasMany<Provincia, $this> */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'departamento_codigo', 'codigo')->orderBy('codigo');
    }
}
