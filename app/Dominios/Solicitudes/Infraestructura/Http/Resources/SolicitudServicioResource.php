<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Resources;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin SolicitudServicio */
#[OA\Schema(
    schema: 'SolicitudServicio',
    title: 'Solicitud de servicio',
    description: 'La unidad de negocio del marketplace (especificación §4.2, ADR 0027). Hectáreas, precios y montos son decimales exactos en string. '
        .'`precio_ha_propuesto` lo fija el cliente; `precio_ha_acordado` queda fijo al adjudicar; `monto_final` (hectáreas ejecutadas × precio acordado) se congela al finalizar y es lo que el cliente paga fuera de la app. `terreno` e `historial` solo viajan en el detalle.',
    required: ['id', 'uuid_cliente', 'terreno_id', 'tipo_aplicacion', 'hectareas_solicitadas', 'producto_indicado', 'aporta_producto', 'urgente', 'estado'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'uuid_cliente', description: 'Nace en el dispositivo; hace idempotente la creación.', type: 'string', format: 'uuid'),
        new OA\Property(property: 'terreno_id', type: 'integer', example: 12),
        new OA\Property(property: 'contacto_id', description: 'Contacto de referencia elegido; una empresa que no elige usa su contacto principal. Nulo en un cliente persona sin contacto.', type: 'integer', nullable: true, example: 7),
        new OA\Property(property: 'tipo_aplicacion', type: 'string', enum: ['herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro']),
        new OA\Property(property: 'hectareas_solicitadas', type: 'string', example: '80.0000'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true, example: 'Soya'),
        new OA\Property(property: 'producto_indicado', type: 'string', example: 'Glifosato 480 SL, 2 L/ha'),
        new OA\Property(property: 'aporta_producto', type: 'string', enum: ['cliente', 'proveedor']),
        new OA\Property(property: 'ventana_desde', description: 'Fecha deseada de fumigación: inicio. Opcional; no vence nada (nulo si el cliente no pidió fecha).', type: 'string', format: 'date', nullable: true, example: '2026-12-01'),
        new OA\Property(property: 'ventana_hasta', description: 'Fecha deseada de fumigación: fin. Opcional.', type: 'string', format: 'date', nullable: true, example: '2026-12-10'),
        new OA\Property(property: 'urgente', type: 'boolean', example: false),
        new OA\Property(property: 'observaciones', type: 'string', nullable: true),
        new OA\Property(property: 'precio_ha_propuesto', description: 'Precio por hectárea que propone el cliente.', type: 'string', nullable: true, example: '125.0000'),
        new OA\Property(property: 'justificacion_precio', description: 'Texto opcional (hasta 500 caracteres, sin HTML) con que el cliente explica el precio que propone; lo escribe solo el cliente y lo ve el proveedor.', type: 'string', nullable: true, example: 'Es la tarifa de la zona del año pasado.'),
        new OA\Property(property: 'precio_ha_acordado', description: 'Precio de la oferta adjudicada.', type: 'string', nullable: true, example: '118.0000'),
        new OA\Property(property: 'monto_estimado', description: 'Total estimado: hectáreas solicitadas por el precio vigente (el propuesto y, tras adjudicar, el acordado). Lo calcula el servidor con decimal exacto y es de solo lectura: si una app lo envía, se ignora.', type: 'string', nullable: true, example: '9440.00'),
        new OA\Property(property: 'hectareas_ejecutadas', type: 'string', nullable: true, example: '78.5000'),
        new OA\Property(property: 'porcentaje_avance', type: 'string', example: '65.00'),
        new OA\Property(property: 'monto_final', description: 'Se congela al finalizar: hectáreas ejecutadas por el precio acordado.', type: 'string', nullable: true),
        new OA\Property(property: 'estado', type: 'string', enum: ['borrador', 'abierta', 'con_ofertas', 'adjudicada', 'en_ejecucion', 'finalizada', 'calificada', 'cancelada', 'en_reclamo']),
        new OA\Property(property: 'oferta_adjudicada_id', type: 'integer', nullable: true),
        new OA\Property(property: 'proveedor_id', type: 'integer', nullable: true),
        new OA\Property(property: 'publicada_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'motivo_cancelacion', type: 'string', nullable: true),
        new OA\Property(property: 'cancelada_por', type: 'string', enum: ['cliente', 'proveedor', 'administrador', 'sistema'], nullable: true),
        new OA\Property(property: 'departamento_codigo', description: 'Ubicación congelada al publicar: código INE del departamento. Nulo en `borrador`.', type: 'string', nullable: true, example: '07'),
        new OA\Property(property: 'provincia_codigo', description: 'Ubicación congelada al publicar: código INE de la provincia.', type: 'string', nullable: true, example: '0702'),
        new OA\Property(property: 'municipio', description: 'Texto de referencia copiado del terreno al publicar.', type: 'string', nullable: true),
        new OA\Property(property: 'comunidad', description: 'Texto de referencia copiado del terreno al publicar.', type: 'string', nullable: true),
        new OA\Property(property: 'contacto', allOf: [new OA\Schema(ref: '#/components/schemas/ClienteContacto')], nullable: true),
        new OA\Property(property: 'terreno', ref: '#/components/schemas/Terreno'),
        new OA\Property(property: 'historial', type: 'array', items: new OA\Items(ref: '#/components/schemas/HistorialSolicitud')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'HistorialSolicitud',
    title: 'Paso del historial de una solicitud',
    description: 'Cada transición deja una fila inmutable con quién, por qué y cuándo (ADR 0027, punto 4).',
    required: ['estado_nuevo', 'origen', 'creado_en'],
    properties: [
        new OA\Property(property: 'estado_anterior', type: 'string', nullable: true),
        new OA\Property(property: 'estado_nuevo', type: 'string'),
        new OA\Property(property: 'origen', type: 'string', enum: ['cliente', 'proveedor', 'administrador', 'sistema']),
        new OA\Property(property: 'motivo', type: 'string', nullable: true),
        new OA\Property(property: 'creado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class SolicitudServicioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuid_cliente,
            'terreno_id' => $this->terreno_id,
            'contacto_id' => $this->contacto_id,
            'tipo_aplicacion' => $this->tipo_aplicacion->value,
            'hectareas_solicitadas' => $this->hectareas_solicitadas,
            'cultivo' => $this->cultivo,
            'producto_indicado' => $this->producto_indicado,
            'aporta_producto' => $this->aporta_producto->value,
            'ventana_desde' => $this->ventana_desde?->toDateString(),
            'ventana_hasta' => $this->ventana_hasta?->toDateString(),
            'urgente' => $this->urgente,
            'observaciones' => $this->observaciones,
            'precio_ha_propuesto' => $this->precio_ha_propuesto,
            'justificacion_precio' => $this->justificacion_precio,
            'precio_ha_acordado' => $this->precio_ha_acordado,
            'monto_estimado' => $this->monto_estimado,
            'hectareas_ejecutadas' => $this->hectareas_ejecutadas,
            'porcentaje_avance' => $this->porcentaje_avance,
            'monto_final' => $this->monto_final,
            'estado' => $this->estado->value,
            'oferta_adjudicada_id' => $this->oferta_adjudicada_id,
            'proveedor_id' => $this->proveedor_id,
            'publicada_en' => $this->publicada_en?->toIso8601String(),
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'cancelada_por' => $this->cancelada_por?->value,
            'departamento_codigo' => $this->departamento_codigo,
            'provincia_codigo' => $this->provincia_codigo,
            'municipio' => $this->municipio,
            'comunidad' => $this->comunidad,
            'contacto' => $this->whenLoaded('contacto', fn () => $this->contacto === null ? null : new ClienteContactoResource($this->contacto)),
            'terreno' => $this->whenLoaded('terreno', fn () => new TerrenoResource($this->terreno)),
            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn (HistorialSolicitud $paso): array => [
                'estado_anterior' => $paso->estado_anterior?->value,
                'estado_nuevo' => $paso->estado_nuevo->value,
                'origen' => $paso->origen->value,
                'motivo' => $paso->motivo,
                'creado_en' => $paso->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
