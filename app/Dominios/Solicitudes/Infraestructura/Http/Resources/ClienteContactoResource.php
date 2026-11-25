<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\ClienteContacto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin ClienteContacto */
#[OA\Schema(
    schema: 'ClienteContacto',
    title: 'Contacto de referencia del cliente',
    description: 'Persona con quien el proveedor coordina el servicio. Una empresa tiene al menos uno; un cliente persona puede no tener. '
        .'Se entrega al proveedor solo tras adjudicar la solicitud.',
    required: ['id', 'nombre', 'telefono', 'es_principal'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'nombre', type: 'string', example: 'Rosa Condori Vargas'),
        new OA\Property(property: 'telefono', type: 'string', example: '+591 76543210'),
        new OA\Property(property: 'cargo', type: 'string', nullable: true, example: 'Encargada de campo'),
        new OA\Property(property: 'es_principal', description: 'El contacto que se usa cuando una solicitud no elige otro. Hay uno solo.', type: 'boolean', example: true),
    ],
    type: 'object',
)]
final class ClienteContactoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'telefono' => $this->telefono,
            'cargo' => $this->cargo,
            'es_principal' => $this->es_principal,
        ];
    }
}
