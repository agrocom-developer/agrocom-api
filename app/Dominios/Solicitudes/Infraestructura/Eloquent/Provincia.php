<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provincia de Bolivia (`sol_provincias`, ADR 0045 punto 3): catálogo oficial de
 * solo lectura con el código del INE (cuatro dígitos: el del departamento y dos
 * más). Lo siembra `UbicacionesSeeder`.
 *
 * @property int $id
 * @property string $departamento_codigo
 * @property string $codigo
 * @property string $nombre
 */
class Provincia extends ModeloDominio
{
    protected $table = 'sol_provincias';

    /** @var list<string> */
    protected $fillable = ['departamento_codigo', 'codigo', 'nombre'];

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_codigo', 'codigo');
    }
}
