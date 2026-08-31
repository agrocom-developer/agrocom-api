<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Resources;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Representación API de un dispositivo con sesión (HU-03).
 *
 * **Nunca expone `token`**: en la base solo está el sha256, y el valor en
 * claro existió una única vez, en la respuesta de emisión. Un endpoint que
 * devolviera la credencial de un dispositivo ya registrado convertiría
 * cualquier token robado en una llave maestra de la cuenta.
 *
 * @mixin SecTokenDispositivo
 */
#[OA\Schema(
    schema: 'Dispositivo',
    title: 'Dispositivo con sesión',
    description: 'Dispositivo de campo con una sesión viva (HU-03). El token en sí nunca se devuelve.',
    required: ['id', 'uuid_dispositivo', 'nombre_dispositivo', 'rol', 'last_used_at', 'created_at'],
    properties: [
        new OA\Property(property: 'id', description: 'Identificador del token de dispositivo.', type: 'integer', example: 1),
        new OA\Property(property: 'uuid_dispositivo', description: 'UUID generado por el dispositivo.', type: 'string', format: 'uuid', example: '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31'),
        new OA\Property(property: 'nombre_dispositivo', description: 'Nombre legible informado por la app.', type: 'string', example: 'Moto G84 — piloto 2', nullable: true),
        new OA\Property(
            property: 'rol',
            description: 'Rol activo con el que opera este dispositivo (nunca la unión de roles del usuario).',
            ref: '#/components/schemas/RolActivo',
        ),
        new OA\Property(property: 'last_used_at', description: 'Último request autenticado con este token.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', description: 'Cuándo se emitió el token.', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class DispositivoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid_dispositivo' => $this->uuid_dispositivo,
            'nombre_dispositivo' => $this->nombre_dispositivo,
            'rol' => new RolActivoResource($this->rol),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
