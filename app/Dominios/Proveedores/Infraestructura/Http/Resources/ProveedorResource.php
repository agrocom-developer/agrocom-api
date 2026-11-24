<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Resources;

use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Proveedor */
#[OA\Schema(
    schema: 'Proveedor',
    title: 'Proveedor',
    description: 'Entidad que presta el servicio y recibe ofertas (ADR 0028). Nace `pendiente`; solo un administrador la aprueba.',
    required: ['id', 'tipo', 'razon_social', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'tipo', description: '`propio` (Agrocom), `independiente` o `tercero`.', type: 'string', enum: ['propio', 'independiente', 'tercero'], example: 'tercero'),
        new OA\Property(property: 'razon_social', type: 'string', example: 'Fumigaciones del Oriente SRL'),
        new OA\Property(property: 'documento_fiscal', type: 'string', example: '1023456028', nullable: true),
        new OA\Property(property: 'telefono', type: 'string', example: '+591 70012345', nullable: true),
        new OA\Property(property: 'zona_base_id', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'radio_operacion_km', description: 'Decimal exacto en string.', type: 'string', example: '80.0'),
        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'], example: 'pendiente'),
        new OA\Property(property: 'motivo_estado', description: 'Motivo del último rechazo, suspensión o baja; null si no aplica.', type: 'string', example: null, nullable: true),
        new OA\Property(property: 'aprobado_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'calificacion_promedio', description: 'Decimal exacto en string (0 a 5).', type: 'string', example: '4.80'),
        new OA\Property(property: 'calificaciones_conteo', type: 'integer', example: 17),
    ],
    type: 'object',
)]
final class ProveedorResource extends JsonResource
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
            'zona_base_id' => $this->zona_base_id,
            'radio_operacion_km' => $this->radio_operacion_km,
            'estado' => $this->estado->value,
            'motivo_estado' => $this->motivo_estado,
            'aprobado_en' => $this->aprobado_en?->toIso8601String(),
            'calificacion_promedio' => $this->calificacion_promedio,
            'calificaciones_conteo' => $this->calificaciones_conteo,
        ];
    }
}
