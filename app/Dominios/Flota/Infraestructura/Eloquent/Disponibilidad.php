<?php

namespace App\Dominios\Flota\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Support\Carbon;

/**
 * Disponibilidad de un equipo (`flo_disponibilidad`, especificación §4.4): una
 * fila por equipo con su estado «en línea» y la última ubicación declarada, si
 * la hay. Las coordenadas viajan como string decimal.
 *
 * @property int $id
 * @property int $equipo_id
 * @property bool $en_linea
 * @property string|null $ubicacion_lat
 * @property string|null $ubicacion_lng
 * @property Carbon $actualizado_en
 */
class Disponibilidad extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'flo_disponibilidad';

    /** @var list<string> */
    protected $fillable = ['equipo_id', 'en_linea', 'ubicacion_lat', 'ubicacion_lng', 'actualizado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'equipo_id' => 'integer',
            'en_linea' => 'boolean',
            'actualizado_en' => 'datetime',
        ];
    }
}
