<?php

namespace App\Dominios\Flota\Infraestructura\Http\Resources;

use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Dron */
#[OA\Schema(
    schema: 'Dron',
    title: 'Dron',
    description: 'El vehículo del marketplace, con dueño y operador (ADR 0029). Necesita aprobación de un administrador antes de recibir ofertas. Capacidad y ancho de pasada viajan como string decimal.',
    required: ['id', 'dueno_proveedor_id', 'operador_proveedor_id', 'marca', 'modelo', 'numero_serie', 'capacidad_tanque_l', 'ancho_pasada_m', 'ciclos_bateria', 'estado', 'origen'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 9),
        new OA\Property(property: 'dueno_proveedor_id', type: 'integer', example: 12),
        new OA\Property(property: 'operador_proveedor_id', type: 'integer', example: 12),
        new OA\Property(property: 'marca', type: 'string', example: 'DJI'),
        new OA\Property(property: 'modelo', type: 'string', example: 'Agras T40'),
        new OA\Property(property: 'numero_serie', type: 'string', example: '1WNDH2K00A0001'),
        new OA\Property(property: 'capacidad_tanque_l', type: 'string', example: '40.00'),
        new OA\Property(property: 'ancho_pasada_m', type: 'string', example: '11.00'),
        new OA\Property(property: 'ciclos_bateria', description: 'Único dato de mantenimiento que se conserva; sube con cada recarga registrada en el lote.', type: 'integer', example: 118),
        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'], example: 'pendiente'),
        new OA\Property(property: 'motivo_estado', type: 'string', nullable: true),
        new OA\Property(property: 'origen', type: 'string', enum: ['flota_agrocom', 'vendido_por_agrocom', 'externo'], example: 'externo'),
    ],
    type: 'object',
)]
final class DronResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dueno_proveedor_id' => $this->dueno_proveedor_id,
            'operador_proveedor_id' => $this->operador_proveedor_id,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'numero_serie' => $this->numero_serie,
            'capacidad_tanque_l' => $this->capacidad_tanque_l,
            'ancho_pasada_m' => $this->ancho_pasada_m,
            'ciclos_bateria' => $this->ciclos_bateria,
            'estado' => $this->estado->value,
            'motivo_estado' => $this->motivo_estado,
            'origen' => $this->origen->value,
        ];
    }
}
