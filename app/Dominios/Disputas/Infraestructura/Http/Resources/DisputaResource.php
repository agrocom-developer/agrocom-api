<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Resources;

use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\MensajeDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\ResolucionDeDisputa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Disputa */
#[OA\Schema(
    schema: 'Disputa',
    title: 'Disputa',
    description: 'La disputa de un servicio (especificación §4.8, ADR 0033): una por solicitud. La abre el cliente (hasta 72 horas después de `ejecutada`) o el proveedor (cobro pendiente, acceso negado o calificación abusiva). '
        .'Los importes son decimales exactos en string. `mensajes` y `resoluciones` solo viajan en el detalle. Los reembolsos se liquidan entre las partes fuera de la app.',
    required: ['id', 'solicitud_id', 'abierta_por', 'motivo', 'detalle', 'monto_en_disputa', 'estado', 'ronda', 'creada_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 9),
        new OA\Property(property: 'solicitud_id', type: 'integer', example: 31),
        new OA\Property(property: 'abierta_por', type: 'string', enum: ['cliente', 'proveedor']),
        new OA\Property(property: 'motivo', type: 'string', enum: ['cobertura_incompleta', 'mala_aplicacion', 'dano_a_cultivos_o_terceros', 'producto_distinto', 'equipo_ausente', 'evidencia_faltante', 'cobro_indebido', 'trato_inadecuado', 'cobro_pendiente', 'acceso_negado', 'calificacion_abusiva']),
        new OA\Property(property: 'detalle', type: 'string'),
        new OA\Property(property: 'monto_en_disputa', type: 'string', example: '12000.00'),
        new OA\Property(property: 'estado', type: 'string', enum: ['abierta', 'en_revision', 'resuelta', 'retirada']),
        new OA\Property(property: 'ronda', description: '1 es la disputa original; 2, la apelación (una sola vez).', type: 'integer', enum: [1, 2]),
        new OA\Property(property: 'responde_antes_de', description: 'Hasta cuándo responde la contraparte (48 horas por defecto).', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'apelable_hasta', description: 'Hasta cuándo se puede apelar la resolución (7 días); nulo si no hay resolución apelable.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'creada_en', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'mensajes',
            type: 'array',
            items: new OA\Items(
                required: ['id', 'autor_rol', 'tipo', 'texto', 'creado_en'],
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'autor_rol', type: 'string', enum: ['cliente', 'proveedor', 'soporte']),
                    new OA\Property(property: 'tipo', type: 'string', enum: ['mensaje', 'informacion', 'apelacion']),
                    new OA\Property(property: 'texto', type: 'string'),
                    new OA\Property(property: 'creado_en', type: 'string', format: 'date-time'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'resoluciones',
            description: 'La decisión del administrador es un registro nuevo; la apelación agrega una segunda y la última rige.',
            type: 'array',
            items: new OA\Items(
                required: ['ronda', 'resultado', 'monto_reembolso', 'motivo', 'derivada_a_seguro', 'resuelta_en'],
                properties: [
                    new OA\Property(property: 'ronda', type: 'integer'),
                    new OA\Property(property: 'resultado', type: 'string', enum: ['a_favor_cliente', 'a_favor_proveedor', 'parcial', 'sin_lugar']),
                    new OA\Property(property: 'monto_reembolso', type: 'string', example: '6000.00'),
                    new OA\Property(property: 'motivo', type: 'string'),
                    new OA\Property(property: 'derivada_a_seguro', description: 'El daño probado se deriva al seguro del proveedor; Agrocom facilita el expediente.', type: 'boolean'),
                    new OA\Property(property: 'resuelta_en', type: 'string', format: 'date-time'),
                ],
                type: 'object',
            ),
        ),
    ],
    type: 'object',
)]
final class DisputaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'abierta_por' => $this->abierta_por_rol->value,
            'motivo' => $this->motivo->value,
            'detalle' => $this->detalle,
            'monto_en_disputa' => $this->monto_en_disputa,
            'estado' => $this->estado->value,
            'ronda' => $this->ronda,
            'responde_antes_de' => $this->responde_antes_de?->toIso8601String(),
            'apelable_hasta' => $this->apelable_hasta?->toIso8601String(),
            'creada_en' => $this->created_at?->toIso8601String(),
            'mensajes' => $this->whenLoaded('mensajes', fn () => $this->mensajes->map(fn (MensajeDeDisputa $mensaje): array => [
                'id' => $mensaje->id,
                'autor_rol' => $mensaje->autor_rol,
                'tipo' => $mensaje->tipo,
                'texto' => $mensaje->texto,
                'creado_en' => $mensaje->created_at->toIso8601String(),
            ])->all()),
            'resoluciones' => $this->whenLoaded('resoluciones', fn () => $this->resoluciones->map(fn (ResolucionDeDisputa $resolucion): array => [
                'ronda' => $resolucion->ronda,
                'resultado' => $resolucion->resultado->value,
                'monto_reembolso' => $resolucion->monto_reembolso,
                'motivo' => $resolucion->motivo,
                'derivada_a_seguro' => $resolucion->derivada_a_seguro,
                'resuelta_en' => $resolucion->resuelta_en->toIso8601String(),
            ])->all()),
        ];
    }
}
