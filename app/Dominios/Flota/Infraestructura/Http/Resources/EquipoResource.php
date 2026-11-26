<?php

namespace App\Dominios\Flota\Infraestructura\Http\Resources;

use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Equipo */
#[OA\Schema(
    schema: 'Equipo',
    title: 'Equipo',
    description: 'Combinación operativa piloto + dron que ejecuta una solicitud. Un piloto y un dron no pueden estar en dos equipos vigentes a la vez.',
    required: ['id', 'proveedor_id', 'piloto_id', 'dron_id', 'vigente_desde'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'proveedor_id', type: 'integer', example: 12),
        new OA\Property(property: 'piloto_id', type: 'integer', example: 5),
        new OA\Property(property: 'dron_id', type: 'integer', example: 9),
        new OA\Property(property: 'vigente_desde', type: 'string', format: 'date', example: '2026-11-23'),
        new OA\Property(property: 'vigente_hasta', description: 'null = sin fin.', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
)]
final class EquipoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'piloto_id' => $this->piloto_id,
            'dron_id' => $this->dron_id,
            'vigente_desde' => $this->vigente_desde->toDateString(),
            'vigente_hasta' => $this->vigente_hasta?->toDateString(),
        ];
    }
}
