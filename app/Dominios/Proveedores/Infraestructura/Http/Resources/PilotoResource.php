<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Resources;

use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use OpenApi\Attributes as OA;

/** El nombre no es columna de `prv_pilotos` (vive en la cuenta): quien arma la respuesta lo resuelve por lote. */
#[OA\Schema(
    schema: 'Piloto',
    title: 'Piloto',
    description: 'Quien opera el dron dentro de un proveedor. Comparte el ciclo de aprobación (ADR 0032).',
    required: ['id', 'proveedor_id', 'usuario_id', 'nombre', 'licencia_numero', 'licencia_vence', 'categoria_habilitacion', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'proveedor_id', type: 'integer', example: 12),
        new OA\Property(property: 'usuario_id', type: 'integer', example: 31),
        new OA\Property(property: 'nombre', type: 'string', example: 'Marcelo Terrazas'),
        new OA\Property(property: 'licencia_numero', type: 'string', example: 'RPA-2291'),
        new OA\Property(property: 'licencia_vence', type: 'string', format: 'date', example: '2027-06-30'),
        new OA\Property(property: 'categoria_habilitacion', type: 'string', example: 'RPAS agrícola'),
        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'], example: 'aprobado'),
        new OA\Property(property: 'motivo_estado', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class PilotoResource
{
    /**
     * @param  array<int, string>  $nombres  Nombre de cuenta por `usuario_id`.
     * @return array<string, mixed>
     */
    public static function conNombres(Piloto $piloto, array $nombres): array
    {
        return [
            'id' => $piloto->id,
            'proveedor_id' => $piloto->proveedor_id,
            'usuario_id' => $piloto->usuario_id,
            'nombre' => $nombres[$piloto->usuario_id] ?? "#{$piloto->usuario_id}",
            'licencia_numero' => $piloto->licencia_numero,
            'licencia_vence' => $piloto->licencia_vence->toDateString(),
            'categoria_habilitacion' => $piloto->categoria_habilitacion,
            'estado' => $piloto->estado->value,
            'motivo_estado' => $piloto->motivo_estado,
        ];
    }
}
