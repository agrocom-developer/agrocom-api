<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Support\Carbon;

/**
 * El cliente confirma el precio final de un servicio cerrado por el piloto
 * (`neg_precios_finales_confirmados`): registro único por solicitud que no cambia el estado.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int $confirmado_por_usuario_id
 * @property string $hectareas_ejecutadas
 * @property string $precio_ha_acordado
 * @property string $monto
 * @property Carbon $confirmado_en
 */
class PrecioFinalConfirmado extends ModeloDominio
{
    protected $table = 'neg_precios_finales_confirmados';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'confirmado_por_usuario_id', 'hectareas_ejecutadas', 'precio_ha_acordado', 'monto', 'confirmado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['solicitud_id' => 'integer', 'confirmado_por_usuario_id' => 'integer', 'hectareas_ejecutadas' => 'decimal:4', 'precio_ha_acordado' => 'decimal:4', 'monto' => 'decimal:2', 'confirmado_en' => 'datetime'];
    }
}
