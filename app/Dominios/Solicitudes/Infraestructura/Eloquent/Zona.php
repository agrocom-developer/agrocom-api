<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Zona geográfica (`sol_zonas`, especificación §4.1): centroide y radio. El
 * proveedor declara su zona base y el motor de asignación las usa para escalar
 * por radio (ADR 0030). Las coordenadas viajan como string decimal.
 *
 * @property int $id
 * @property string $nombre
 * @property string $centroide_lat
 * @property string $centroide_lng
 * @property string $radio_km
 */
class Zona extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_zonas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'centroide_lat',
        'centroide_lng',
        'radio_km',
    ];
}
