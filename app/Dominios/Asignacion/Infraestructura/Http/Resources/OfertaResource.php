<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Resources;

use App\Dominios\Asignacion\Aplicacion\OfertaParaProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Una oferta tal como la ve el proveedor (especificación §6, punto 8): el
 * terreno aproximado, las hectáreas, el pago estimado y la distancia; sin datos
 * de contacto del cliente. Los importes y las hectáreas viajan como cadena
 * decimal exacta (invariante 6 de CLAUDE.md).
 *
 * @property OfertaParaProveedor $resource
 */
#[OA\Schema(
    schema: 'Oferta',
    title: 'Oferta de servicio',
    required: ['id', 'solicitud_id', 'estado', 'ronda', 'grupo', 'ofrecida_en', 'vence_en', 'segundos_restantes'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'solicitud_id', type: 'integer', example: 12),
        new OA\Property(property: 'estado', type: 'string', enum: ['ofrecida', 'aceptada', 'rechazada', 'vencida', 'retirada']),
        new OA\Property(property: 'ronda', type: 'integer', example: 1),
        new OA\Property(property: 'grupo', type: 'string', enum: ['propio', 'tercero']),
        new OA\Property(property: 'ofrecida_en', type: 'string', format: 'date-time'),
        new OA\Property(property: 'vence_en', type: 'string', format: 'date-time'),
        new OA\Property(property: 'segundos_restantes', description: 'Cuenta regresiva de una oferta viva; 0 si ya se resolvió.', type: 'integer', example: 540),
        new OA\Property(property: 'respondida_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'motivo_rechazo', type: 'string', nullable: true),
        new OA\Property(property: 'motivo_retiro', type: 'string', nullable: true, enum: ['otro_acepto', 'cancelada', 'reasignada', 'asignacion_forzada']),
        new OA\Property(property: 'equipo_id', type: 'integer', nullable: true),
        new OA\Property(property: 'pago_estimado', type: 'string', nullable: true, example: '9792.00'),
        new OA\Property(property: 'distancia_km', type: 'string', nullable: true, example: '23.40'),
        new OA\Property(
            property: 'servicio',
            description: 'Lo que se muestra del servicio antes de aceptar: sin nombre del terreno ni contacto del cliente.',
            properties: [
                new OA\Property(property: 'tipo_aplicacion', type: 'string'),
                new OA\Property(property: 'hectareas', type: 'string', example: '80.0000'),
                new OA\Property(property: 'urgente', type: 'boolean'),
                new OA\Property(property: 'ventana_desde', type: 'string', format: 'date'),
                new OA\Property(property: 'ventana_hasta', type: 'string', format: 'date'),
                new OA\Property(property: 'zona', type: 'string', nullable: true, example: 'Warnes'),
                new OA\Property(property: 'ubicacion_aproximada', properties: [new OA\Property(property: 'lat', type: 'string'), new OA\Property(property: 'lng', type: 'string')], type: 'object', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
    ],
    type: 'object',
)]
final class OfertaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $oferta = $this->resource->oferta;
        $solicitud = $this->resource->solicitud;

        return [
            'id' => $oferta->id,
            'solicitud_id' => $oferta->solicitud_id,
            'estado' => $oferta->estado->value,
            'ronda' => $oferta->ronda,
            'grupo' => $oferta->grupo->value,
            'ofrecida_en' => $oferta->ofrecida_en->toIso8601String(),
            'vence_en' => $oferta->vence_en->toIso8601String(),
            'segundos_restantes' => $this->resource->segundosRestantes,
            'respondida_en' => $oferta->respondida_en?->toIso8601String(),
            'motivo_rechazo' => $oferta->motivo_rechazo,
            'motivo_retiro' => $oferta->motivo_retiro?->value,
            'equipo_id' => $oferta->equipo_id,
            'pago_estimado' => $oferta->pago_estimado_proveedor,
            'distancia_km' => $oferta->distancia_km,
            'servicio' => $solicitud === null ? null : [
                'tipo_aplicacion' => $solicitud->tipoAplicacion,
                'hectareas' => $solicitud->hectareas,
                'urgente' => $solicitud->urgente,
                'ventana_desde' => $solicitud->ventanaDesde,
                'ventana_hasta' => $solicitud->ventanaHasta,
                'zona' => $solicitud->zonaNombre,
                'ubicacion_aproximada' => $this->resource->ubicacionAproximada,
            ],
        ];
    }
}
