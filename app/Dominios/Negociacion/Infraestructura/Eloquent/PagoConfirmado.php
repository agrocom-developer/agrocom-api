<?php

namespace App\Dominios\Negociacion\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Support\Carbon;

/**
 * El proveedor informa que recibió el pago de un servicio (`neg_pagos_confirmados`,
 * ADR 0034): registro informativo, único por solicitud, que no cambia el estado.
 *
 * @property int $id
 * @property string $uuid_cliente
 * @property int $solicitud_id
 * @property int $confirmado_por_usuario_id
 * @property string $monto
 * @property string|null $metodo
 * @property string|null $nota
 * @property Carbon $confirmado_en
 */
class PagoConfirmado extends ModeloDominio
{
    protected $table = 'neg_pagos_confirmados';

    /** @var list<string> */
    protected $fillable = ['uuid_cliente', 'solicitud_id', 'confirmado_por_usuario_id', 'monto', 'metodo', 'nota', 'confirmado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['solicitud_id' => 'integer', 'confirmado_por_usuario_id' => 'integer', 'monto' => 'decimal:2', 'confirmado_en' => 'datetime'];
    }
}
