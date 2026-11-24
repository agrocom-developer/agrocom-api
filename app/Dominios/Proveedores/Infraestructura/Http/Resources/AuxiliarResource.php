<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Resources;

use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Auxiliar',
    title: 'Auxiliar',
    description: 'Quien prepara la mezcla y asiste en el lote. Lo registra un piloto o el proveedor y lo aprueba un administrador (ADR 0032).',
    required: ['id', 'proveedor_id', 'usuario_id', 'nombre', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 8),
        new OA\Property(property: 'proveedor_id', type: 'integer', example: 12),
        new OA\Property(property: 'usuario_id', type: 'integer', example: 34),
        new OA\Property(property: 'nombre', type: 'string', example: 'Lucía Flores'),
        new OA\Property(property: 'registrado_por_piloto_id', type: 'integer', nullable: true),
        new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'aprobado', 'suspendido', 'rechazado', 'baja'], example: 'pendiente'),
        new OA\Property(property: 'motivo_estado', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class AuxiliarResource
{
    /**
     * @param  array<int, string>  $nombres  Nombre de cuenta por `usuario_id`.
     * @return array<string, mixed>
     */
    public static function conNombres(Auxiliar $auxiliar, array $nombres): array
    {
        return [
            'id' => $auxiliar->id,
            'proveedor_id' => $auxiliar->proveedor_id,
            'usuario_id' => $auxiliar->usuario_id,
            'nombre' => $nombres[$auxiliar->usuario_id] ?? "#{$auxiliar->usuario_id}",
            'registrado_por_piloto_id' => $auxiliar->registrado_por_piloto_id,
            'estado' => $auxiliar->estado->value,
            'motivo_estado' => $auxiliar->motivo_estado,
        ];
    }
}
