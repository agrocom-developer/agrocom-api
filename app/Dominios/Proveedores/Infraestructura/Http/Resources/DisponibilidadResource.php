<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Resources;

use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use OpenApi\Attributes as OA;

/** El interruptor de disponibilidad de una cuenta de proveedor, con cuándo y quién lo cambió. */
#[OA\Schema(
    schema: 'Disponibilidad',
    title: 'Disponibilidad',
    description: 'Interruptor manual de la cuenta (ADR 0045, punto 8). `ocupado` y `en_trabajo` dejan de recibir avisos de nuevas solicitudes y se distinguen solo por el texto; la cuenta sigue viendo el mapa y puede ofertar. Al volver a `disponible` no llegan avisos atrasados.',
    required: ['valor', 'cambiada_en', 'cambiada_por_usuario_id', 'cambiada_por_propia'],
    properties: [
        new OA\Property(property: 'valor', type: 'string', enum: ['disponible', 'ocupado', 'en_trabajo'], example: 'ocupado'),
        new OA\Property(property: 'cambiada_en', description: 'Último cambio; null si nunca se movió.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cambiada_por_usuario_id', description: 'Cuenta que hizo el último cambio; null si nunca se movió.', type: 'integer', example: 31, nullable: true),
        new OA\Property(property: 'cambiada_por_propia', description: '`true` si la cambió la propia cuenta, `false` si la cambió otra (el encargado de la empresa: la app muestra «Tu empresa te marcó como ocupado»); null si nunca se movió.', type: 'boolean', nullable: true),
    ],
    type: 'object',
)]
final class DisponibilidadResource
{
    /** @return array{valor: string, cambiada_en: ?string, cambiada_por_usuario_id: ?int, cambiada_por_propia: ?bool} */
    public static function de(Proveedor|Piloto $registro): array
    {
        $duenoId = $registro instanceof Proveedor ? $registro->usuario_titular_id : $registro->usuario_id;
        $por = $registro->disponibilidad_cambiada_por;

        return [
            'valor' => ($registro->disponibilidad ?? Disponibilidad::Disponible)->value,
            'cambiada_en' => $registro->disponibilidad_cambiada_en?->toIso8601String(),
            'cambiada_por_usuario_id' => $por,
            'cambiada_por_propia' => $por === null ? null : $por === $duenoId,
        ];
    }
}
