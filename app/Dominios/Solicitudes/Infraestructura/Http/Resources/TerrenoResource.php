<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Terreno */
#[OA\Schema(
    schema: 'Terreno',
    title: 'Terreno',
    description: 'Terreno de un cliente sobre el que pide servicios (especificación §4.1). Hectáreas como decimal exacto en string.',
    required: ['id', 'nombre', 'hectareas', 'acceso_dificil'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'nombre', type: 'string', example: 'Lote Norte'),
        new OA\Property(property: 'hectareas', type: 'string', example: '120.5000'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true, example: 'Soya'),
        new OA\Property(property: 'geometria', description: 'Polígono GeoJSON del terreno.', type: 'object', nullable: true),
        new OA\Property(property: 'referencias_acceso', type: 'string', nullable: true),
        new OA\Property(property: 'colindantes_sensibles', description: 'Apiarios, cultivos orgánicos, viviendas.', type: 'string', nullable: true),
        new OA\Property(property: 'acceso_dificil', type: 'boolean', example: false),
        new OA\Property(property: 'zona_id', type: 'integer', nullable: true, example: 1),
    ],
    type: 'object',
)]
final class TerrenoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'hectareas' => $this->hectareas,
            'cultivo' => $this->cultivo,
            'geometria' => $this->geometria,
            'referencias_acceso' => $this->referencias_acceso,
            'colindantes_sensibles' => $this->colindantes_sensibles,
            'acceso_dificil' => $this->acceso_dificil,
            'zona_id' => $this->zona_id,
        ];
    }
}
