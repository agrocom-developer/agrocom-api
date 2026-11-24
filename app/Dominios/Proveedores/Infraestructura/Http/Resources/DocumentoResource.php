<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Resources;

use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Nunca expone `archivo_ref`: el archivo es privado y solo lo ven
 * administradores y soporte desde el panel (política de verificación).
 *
 * @mixin Documento
 */
#[OA\Schema(
    schema: 'DocumentoProveedor',
    title: 'Documento de verificación',
    description: 'Documento con vencimiento de un proveedor, piloto, auxiliar o dron. El archivo no se descarga por la API.',
    required: ['id', 'sujeto_tipo', 'sujeto_id', 'tipo', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 40),
        new OA\Property(property: 'sujeto_tipo', type: 'string', enum: ['proveedor', 'piloto', 'auxiliar', 'dron'], example: 'piloto'),
        new OA\Property(property: 'sujeto_id', type: 'integer', example: 5),
        new OA\Property(property: 'tipo', type: 'string', enum: ['identidad', 'licencia_piloto', 'habilitacion_dron', 'seguro_responsabilidad', 'registro_dron', 'certificado_fumigacion', 'antecedentes', 'otro'], example: 'licencia_piloto'),
        new OA\Property(property: 'estado', type: 'string', enum: ['en_revision', 'aprobado', 'rechazado', 'vencido'], example: 'en_revision'),
        new OA\Property(property: 'vence_en', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'motivo_rechazo', type: 'string', nullable: true),
        new OA\Property(property: 'nombre_original', type: 'string', nullable: true),
        new OA\Property(property: 'bytes', type: 'integer', example: 182044),
        new OA\Property(property: 'subido_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class DocumentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sujeto_tipo' => $this->sujeto_tipo->value,
            'sujeto_id' => $this->sujeto_id,
            'tipo' => $this->tipo->value,
            'estado' => $this->estado->value,
            'vence_en' => $this->vence_en?->toDateString(),
            'motivo_rechazo' => $this->motivo_rechazo,
            'nombre_original' => $this->nombre_original,
            'bytes' => $this->bytes,
            'subido_en' => $this->created_at->toIso8601String(),
        ];
    }
}
