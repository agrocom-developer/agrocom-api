<?php

namespace App\Dominios\Solicitudes\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Precio por hectárea de referencia (`sol_precios_referencia`): guía para el cliente
 * al proponer y para el piloto al ofertar. Por zona, por macrorregión o general.
 *
 * @property int $id
 * @property string $tipo_aplicacion
 * @property int|null $zona_id
 * @property string|null $macrorregion
 * @property string $precio_ha
 */
class PrecioReferencia extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sol_precios_referencia';

    /** @var list<string> */
    protected $fillable = ['tipo_aplicacion', 'zona_id', 'macrorregion', 'precio_ha'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['zona_id' => 'integer', 'precio_ha' => 'decimal:4'];
    }
}
