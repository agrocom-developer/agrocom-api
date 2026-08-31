<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Resources;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Identidad del operario tal como la necesita la app de campo: lo justo para
 * saludarlo y para saber a qué persona operativa corresponde.
 *
 * `persona_id` viaja como ID pelado (ADR 0003, regla 3): el detalle de la
 * persona pertenece al módulo `Personal` y la app lo resuelve desde su
 * catálogo sincronizado, no anidado acá.
 *
 * @mixin SecUser
 */
#[OA\Schema(
    schema: 'UsuarioCampo',
    title: 'Usuario de campo',
    description: 'Cuenta con la que opera la app de campo.',
    required: ['id', 'name', 'username', 'persona_id'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'name', description: 'Nombre para mostrar.', type: 'string', example: 'Camila Rojas'),
        new OA\Property(property: 'username', description: 'Identificador de login (nunca un correo).', type: 'string', example: 'camila.rojas'),
        new OA\Property(property: 'persona_id', description: 'Persona operativa enlazada (módulo Personal, solo ID).', type: 'integer', example: 3, nullable: true),
    ],
    type: 'object',
)]
final class UsuarioCampoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'persona_id' => $this->persona_id,
        ];
    }
}
