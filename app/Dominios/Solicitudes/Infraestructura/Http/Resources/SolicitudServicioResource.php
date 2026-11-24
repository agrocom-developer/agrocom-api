<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin SolicitudServicio */
#[OA\Schema(
    schema: 'SolicitudServicio',
    title: 'Solicitud de servicio',
    description: 'La unidad de negocio del marketplace (especificación §4.2, ADR 0027). Hectáreas, tarifa y montos son decimales exactos en string. '
        .'`tarifa_hectarea` y `monto_estimado` son nulos hasta publicar y quedan congelados desde entonces. `terreno` e `historial` solo viajan en el detalle.',
    required: ['id', 'uuid_cliente', 'terreno_id', 'tipo_aplicacion', 'hectareas_solicitadas', 'producto_indicado', 'aporta_producto', 'ventana_desde', 'ventana_hasta', 'urgente', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'uuid_cliente', description: 'Nace en el dispositivo; hace idempotente la creación.', type: 'string', format: 'uuid'),
        new OA\Property(property: 'terreno_id', type: 'integer', example: 12),
        new OA\Property(property: 'tipo_aplicacion', type: 'string', enum: ['herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro']),
        new OA\Property(property: 'hectareas_solicitadas', type: 'string', example: '80.0000'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true, example: 'Soya'),
        new OA\Property(property: 'producto_indicado', type: 'string', example: 'Glifosato 480 SL, 2 L/ha'),
        new OA\Property(property: 'aporta_producto', type: 'string', enum: ['cliente', 'proveedor']),
        new OA\Property(property: 'ventana_desde', type: 'string', format: 'date', example: '2026-12-01'),
        new OA\Property(property: 'ventana_hasta', type: 'string', format: 'date', example: '2026-12-10'),
        new OA\Property(property: 'urgente', type: 'boolean', example: false),
        new OA\Property(property: 'observaciones', type: 'string', nullable: true),
        new OA\Property(property: 'tarifa_hectarea', description: 'Congelada al publicar; incluye recargos por hectárea.', type: 'string', nullable: true, example: '132.0000'),
        new OA\Property(property: 'monto_estimado', description: 'Congelado al publicar.', type: 'string', nullable: true, example: '10560.00'),
        new OA\Property(property: 'monto_final', description: 'Se congela al completar la solicitud: el estimado escalado por las hectáreas facturadas.', type: 'string', nullable: true),
        new OA\Property(property: 'estado', type: 'string', enum: ['borrador', 'publicada', 'en_asignacion', 'sin_proveedor', 'asignada', 'en_camino', 'en_ejecucion', 'suspendida_por_clima', 'ejecutada', 'en_disputa', 'completada', 'cancelada']),
        new OA\Property(property: 'proveedor_id', type: 'integer', nullable: true),
        new OA\Property(property: 'publicada_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'motivo_cancelacion', type: 'string', nullable: true),
        new OA\Property(property: 'cancelada_por', type: 'string', enum: ['cliente', 'proveedor', 'administrador', 'sistema'], nullable: true),
        new OA\Property(property: 'terreno', ref: '#/components/schemas/Terreno'),
        new OA\Property(property: 'historial', type: 'array', items: new OA\Items(ref: '#/components/schemas/HistorialSolicitud')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'HistorialSolicitud',
    title: 'Paso del historial de una solicitud',
    description: 'Cada transición deja una fila inmutable con quién, por qué y cuándo (ADR 0027, punto 4).',
    required: ['estado_nuevo', 'origen', 'creado_en'],
    properties: [
        new OA\Property(property: 'estado_anterior', type: 'string', nullable: true),
        new OA\Property(property: 'estado_nuevo', type: 'string'),
        new OA\Property(property: 'origen', type: 'string', enum: ['cliente', 'proveedor', 'administrador', 'sistema']),
        new OA\Property(property: 'motivo', type: 'string', nullable: true),
        new OA\Property(property: 'creado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class SolicitudServicioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuid_cliente,
            'terreno_id' => $this->terreno_id,
            'tipo_aplicacion' => $this->tipo_aplicacion->value,
            'hectareas_solicitadas' => $this->hectareas_solicitadas,
            'cultivo' => $this->cultivo,
            'producto_indicado' => $this->producto_indicado,
            'aporta_producto' => $this->aporta_producto->value,
            'ventana_desde' => $this->ventana_desde->toDateString(),
            'ventana_hasta' => $this->ventana_hasta->toDateString(),
            'urgente' => $this->urgente,
            'observaciones' => $this->observaciones,
            'tarifa_hectarea' => $this->tarifa_hectarea,
            'monto_estimado' => $this->monto_estimado,
            'monto_final' => $this->monto_final,
            'estado' => $this->estado->value,
            'proveedor_id' => $this->proveedor_id,
            'publicada_en' => $this->publicada_en?->toIso8601String(),
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'cancelada_por' => $this->cancelada_por?->value,
            'terreno' => $this->whenLoaded('terreno', fn () => new TerrenoResource($this->terreno)),
            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn (HistorialSolicitud $paso): array => [
                'estado_anterior' => $paso->estado_anterior?->value,
                'estado_nuevo' => $paso->estado_nuevo->value,
                'origen' => $paso->origen->value,
                'motivo' => $paso->motivo,
                'creado_en' => $paso->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
