<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Cliente */
#[OA\Schema(
    schema: 'Cliente',
    title: 'Cliente',
    description: 'Quien solicita el servicio de fumigación y califica al proveedor (especificación §4.1).',
    required: ['id', 'tipo', 'razon_social', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 4),
        new OA\Property(property: 'tipo', type: 'string', enum: ['persona', 'empresa'], example: 'empresa'),
        new OA\Property(property: 'razon_social', type: 'string', example: 'Agropecuaria San Rafael SRL'),
        new OA\Property(property: 'documento_fiscal', type: 'string', nullable: true, example: '1029384027'),
        new OA\Property(property: 'telefono', type: 'string', nullable: true, example: '+591 76543210'),
        new OA\Property(property: 'estado', type: 'string', enum: ['activo', 'suspendido'], example: 'activo'),
        new OA\Property(property: 'calificacion_promedio', description: 'Decimal exacto en string (0 a 5).', type: 'string', example: '0.00'),
        new OA\Property(property: 'calificaciones_conteo', type: 'integer', example: 0),
    ],
    type: 'object',
)]
final class ClienteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'razon_social' => $this->razon_social,
            'documento_fiscal' => $this->documento_fiscal,
            'telefono' => $this->telefono,
            'estado' => $this->estado->value,
            'calificacion_promedio' => $this->calificacion_promedio,
            'calificaciones_conteo' => $this->calificaciones_conteo,
        ];
    }
}
