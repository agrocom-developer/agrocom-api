<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Zona geográfica (`sol_zonas`, especificación §4.1): centroide y macrorregión.
 * Alimentan los filtros del mapa de solicitudes y los avisos de nuevas
 * solicitudes de los pilotos. Las coordenadas viajan como string decimal.
 *
 * @property int $id
 * @property string $nombre
 * @property string $centroide_lat
 * @property string $centroide_lng
 * @property string $radio_km
 * @property string|null $macrorregion
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
        'macrorregion',
    ];
}
