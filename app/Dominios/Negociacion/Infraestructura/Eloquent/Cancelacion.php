<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;

/**
 * Cancelación de una solicitud ya adjudicada (`neg_cancelaciones`,
 * `negociacion-contrato.md` §3.5): alimenta la tasa de cancelación del proveedor.
 * Sin penalidad en dinero ni restricción automática.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int|null $proveedor_id
 * @property string $parte `cliente` o `proveedor`
 * @property string $motivo
 */
class Cancelacion extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'neg_cancelaciones';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'proveedor_id', 'parte', 'motivo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['solicitud_id' => 'integer', 'proveedor_id' => 'integer'];
    }
}
