<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Resources;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Rol tal como lo ve la app de campo. Es el rol ÚNICO bajo el que opera la
 * sesión —del token o del selector—, nunca una lista de permisos efectivos
 * (invariante 10 de CLAUDE.md): los permisos se resuelven en el servidor
 * contra la base en cada request, no se le entregan a la app para que decida
 * sola.
 *
 * @mixin SecRole
 */
#[OA\Schema(
    schema: 'RolActivo',
    title: 'Rol',
    description: 'Rol bajo el que opera una sesión de la app de campo.',
    required: ['id', 'name', 'description'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre interno del rol.', type: 'string', example: 'piloto'),
        new OA\Property(property: 'description', description: 'Descripción legible del rol.', type: 'string', example: 'Piloto de dron: ejecuta sesiones de vuelo en campo.', nullable: true),
    ],
    type: 'object',
)]
final class RolActivoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
