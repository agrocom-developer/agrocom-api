<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Resources;

use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Calificacion */
#[OA\Schema(
    schema: 'Calificacion',
    title: 'Calificación',
    description: 'Una calificación de un servicio completado (especificación §4.7, ADR 0031). Se escribe una vez y nunca se edita: moderar cambia el `estado`, no el contenido. '
        .'El proveedor no ve las `oculta`; la que el proveedor escribe sobre el cliente es privada.',
    required: ['id', 'uuid_cliente', 'solicitud_id', 'sujeto_tipo', 'sujeto_id', 'estrellas', 'etiquetas', 'estado', 'creada_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 17),
        new OA\Property(property: 'uuid_cliente', description: 'Nace en el dispositivo; hace idempotente el reintento.', type: 'string', format: 'uuid'),
        new OA\Property(property: 'solicitud_id', type: 'integer', example: 31),
        new OA\Property(property: 'sujeto_tipo', type: 'string', enum: ['proveedor', 'piloto', 'auxiliar', 'cliente']),
        new OA\Property(property: 'sujeto_id', type: 'integer', example: 4),
        new OA\Property(property: 'estrellas', type: 'integer', minimum: 1, maximum: 5, example: 5),
        new OA\Property(property: 'etiquetas', description: 'Del catálogo cerrado: puntualidad, cobertura_pareja, comunicacion, cuidado_entorno, orden_lote, acceso_lote, claridad_pedido.', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'comentario', type: 'string', nullable: true),
        new OA\Property(property: 'estado', type: 'string', enum: ['visible', 'en_moderacion', 'oculta']),
        new OA\Property(property: 'creada_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class CalificacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuid_cliente,
            'solicitud_id' => $this->solicitud_id,
            'sujeto_tipo' => $this->sujeto_tipo->value,
            'sujeto_id' => $this->sujeto_id,
            'estrellas' => $this->estrellas,
            'etiquetas' => $this->etiquetas,
            'comentario' => $this->comentario,
            'estado' => $this->estado->value,
            'creada_en' => $this->created_at?->toIso8601String(),
        ];
    }
}
