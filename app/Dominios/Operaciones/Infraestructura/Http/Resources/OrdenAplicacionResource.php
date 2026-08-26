<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Resources;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Representación API de la orden de aplicación. Expone las referencias al
 * módulo Comercial (`contrato_id`, `lote_id`, `emitida_por_contacto_id`)
 * solo como IDs (ADR 0003, regla 3): la app de campo resuelve el detalle de
 * lotes/contratos desde su catálogo sincronizado, no anidado acá.
 *
 * Los DECIMAL viajan como string (invariante 6: nunca float en dinero ni
 * hectáreas — tampoco en el JSON del contrato de API). El schema OpenAPI de
 * abajo es el contrato de este resource (ADR 0014): si `toArray()` cambia,
 * cambia el schema en el mismo diff.
 *
 * @mixin OrdenAplicacion
 */
#[OA\Schema(
    schema: 'OrdenAplicacion',
    title: 'Orden de aplicación',
    description: 'Orden de aplicación emitida por el cliente para un lote (espec §4.3). '
        .'Los valores DECIMAL (dosis, límites climáticos y de vuelo) viajan como string. '
        .'Los límites en null heredan del contrato o del parámetro por defecto del sistema (RF-60).',
    required: [
        'id',
        'contrato_id',
        'lote_id',
        'nro_aplicacion',
        'litros_ha',
        'humedad_min_pct',
        'viento_max_kmh',
        'temperatura_max_c',
        'humedad_max_pct',
        'velocidad_max_kmh',
        'altura_vuelo_m',
        'velocidad_vuelo_kmh',
        'ancho_pasada_m',
        'observaciones',
        'emitida_por_contacto_id',
        'fecha_emision',
        'estado',
        'created_at',
        'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', description: 'Identificador de la orden.', type: 'integer', example: 1),
        new OA\Property(property: 'contrato_id', description: 'Contrato al que pertenece la orden (módulo Comercial, solo ID).', type: 'integer', example: 1),
        new OA\Property(property: 'lote_id', description: 'Lote sobre el que se aplica (módulo Comercial, solo ID).', type: 'integer', example: 3),
        new OA\Property(property: 'nro_aplicacion', description: 'Número de aplicación dentro del contrato (1..n).', type: 'integer', example: 1),
        new OA\Property(property: 'litros_ha', description: 'Dosis en litros por hectárea. DECIMAL como string.', type: 'string', example: '10.00'),
        new OA\Property(property: 'humedad_min_pct', description: 'Humedad relativa mínima para aplicar (%). DECIMAL como string; null hereda.', type: 'string', example: '60.00', nullable: true),
        new OA\Property(property: 'viento_max_kmh', description: 'Viento máximo para aplicar (km/h). DECIMAL como string; null hereda.', type: 'string', example: '15.00', nullable: true),
        new OA\Property(property: 'temperatura_max_c', description: 'Temperatura máxima para aplicar (°C). DECIMAL como string; null hereda.', type: 'string', example: '32.00', nullable: true),
        new OA\Property(property: 'humedad_max_pct', description: 'Humedad relativa máxima para aplicar (%). DECIMAL como string; null hereda.', type: 'string', example: '90.00', nullable: true),
        new OA\Property(property: 'velocidad_max_kmh', description: 'Velocidad máxima del equipo (km/h). DECIMAL como string; null hereda.', type: 'string', example: '25.00', nullable: true),
        new OA\Property(property: 'altura_vuelo_m', description: 'Altura de vuelo indicada (m). DECIMAL como string; null hereda.', type: 'string', example: '3.00', nullable: true),
        new OA\Property(property: 'velocidad_vuelo_kmh', description: 'Velocidad de vuelo indicada (km/h). DECIMAL como string; null hereda.', type: 'string', example: '18.00', nullable: true),
        new OA\Property(property: 'ancho_pasada_m', description: 'Ancho de pasada indicado (m). DECIMAL como string; null hereda.', type: 'string', example: '7.00', nullable: true),
        new OA\Property(property: 'observaciones', description: 'Observaciones libres del emisor.', type: 'string', example: 'Aplicar en horas de la mañana.', nullable: true),
        new OA\Property(property: 'emitida_por_contacto_id', description: 'Contacto del cliente que emitió la orden (módulo Comercial, solo ID).', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'fecha_emision', description: 'Fecha de emisión de la orden.', type: 'string', format: 'date', example: '2026-08-26'),
        new OA\Property(property: 'estado', description: 'Estado de la orden (espec §5: emitida → vigente → consumida | vencida).', type: 'string', enum: EstadoOrdenAplicacion::class),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00', nullable: true),
    ],
    type: 'object',
)]
class OrdenAplicacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contrato_id' => $this->contrato_id,
            'lote_id' => $this->lote_id,
            'nro_aplicacion' => $this->nro_aplicacion,
            'litros_ha' => $this->litros_ha,
            'humedad_min_pct' => $this->humedad_min_pct,
            'viento_max_kmh' => $this->viento_max_kmh,
            'temperatura_max_c' => $this->temperatura_max_c,
            'humedad_max_pct' => $this->humedad_max_pct,
            'velocidad_max_kmh' => $this->velocidad_max_kmh,
            'altura_vuelo_m' => $this->altura_vuelo_m,
            'velocidad_vuelo_kmh' => $this->velocidad_vuelo_kmh,
            'ancho_pasada_m' => $this->ancho_pasada_m,
            'observaciones' => $this->observaciones,
            'emitida_por_contacto_id' => $this->emitida_por_contacto_id,
            'fecha_emision' => $this->fecha_emision->toDateString(),
            'estado' => $this->estado->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
