<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Resources;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Representación API de la orden de aplicación. Expone las referencias al
 * módulo Comercial (`contrato_id`, `lotes.*.lote_id`, `emitida_por_contacto_id`)
 * solo como IDs (ADR 0003, regla 3): la app de campo resuelve el detalle de
 * lotes/contratos desde su catálogo sincronizado, no anidado acá.
 *
 * `lotes` (HU-92, tarea 107, reemplaza el `lote_id` único de antes): la
 * orden puede cubrir varios lotes de la propiedad — ver
 * `OrdenAplicacionCatalogo` para el mismo criterio de forma (lista plana de
 * arrays, sin DTO propio por ítem). Requiere `ordenLotes` cargada por el
 * llamador (`ListarOrdenesAplicacion` ya la trae con `with()`) para no
 * generar un N+1 en el listado paginado.
 *
 * Los DECIMAL viajan como string (invariante 6: nunca float en dinero ni
 * hectáreas — tampoco en el JSON del contrato de API). El schema OpenAPI de
 * abajo es el contrato de este resource (ADR 0014): si `toArray()` cambia,
 * cambia el schema en el mismo diff.
 *
 * Los 8 campos de límites climáticos y parámetros de vuelo
 * (`humedad_min_pct`...`ancho_pasada_m`) YA NO viajan acá (migración
 * `2026_09_18_100001_mueve_clima_vuelo_de_ordenes_a_trabajos_table`):
 * describen el vuelo de cada equipo, no la orden — viajan ahora en el
 * catálogo de trabajos asignados (`TrabajoAsignadoCatalogo`, expuesto por
 * `GET /api/sync/catalogo`), no en este resource.
 *
 * @mixin OrdenAplicacion
 */
#[OA\Schema(
    schema: 'OrdenAplicacion',
    title: 'Orden de aplicación',
    description: 'Una aplicación completa de un contrato: cubre TODOS los lotes del contrato (espec §4.3, ADR 0022). '
        .'Los valores DECIMAL (dosis) viajan como string. '
        .'Los límites climáticos y parámetros de vuelo ya no viajan acá: son del trabajo (equipo↔lote), ver el catálogo de sincronización.',
    required: [
        'id',
        'contrato_id',
        'lotes',
        'nro_aplicacion',
        'litros_ha',
        'kilos_por_vuelo',
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
        new OA\Property(
            property: 'lotes',
            description: 'Lotes que cubre la orden (módulo Comercial, solo ID): copia automática, tomada al emitirla, de todos los lotes del contrato con las hectáreas completas de cada uno.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/LoteDeOrdenCatalogo'),
        ),
        new OA\Property(property: 'nro_aplicacion', description: 'Número de aplicación dentro del contrato (1..n), correlativo y calculado por el servidor: una cancelada por fuerza mayor no consume su número.', type: 'integer', example: 1),
        new OA\Property(property: 'litros_ha', description: 'Dosis en litros por hectárea (insumo líquido). DECIMAL como string; null si la categoría de insumo es sólida (HU-79, tarea 110).', type: 'string', example: '10.00', nullable: true),
        new OA\Property(property: 'kilos_por_vuelo', description: 'Dosis en kilos por vuelo (insumo sólido). DECIMAL como string; null si la categoría de insumo es líquida (HU-79, tarea 110).', type: 'string', example: '8.50', nullable: true),
        new OA\Property(property: 'observaciones', description: 'Observaciones libres del emisor.', type: 'string', example: 'Aplicar en horas de la mañana.', nullable: true),
        new OA\Property(property: 'emitida_por_contacto_id', description: 'Contacto del cliente que emitió la orden (módulo Comercial, solo ID).', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'fecha_emision', description: 'Fecha de emisión de la orden.', type: 'string', format: 'date', example: '2026-08-26'),
        new OA\Property(property: 'estado', description: 'Estado de la orden (espec §5, ADR 0022): emitida → vigente ⇄ pausada; vigente → consumida (cierre manual) o cancelada (con causa y motivo, decisión del panel). La app de campo solo recibe las vigentes y nunca pausa, cierra ni cancela.', type: 'string', enum: EstadoOrdenAplicacion::class),
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
            'lotes' => $this->ordenLotes->map(fn (OrdenLote $ordenLote): array => [
                'lote_id' => $ordenLote->lote_id,
                'hectareas_solicitadas' => (string) $ordenLote->hectareas_solicitadas,
            ])->all(),
            'nro_aplicacion' => $this->nro_aplicacion,
            'litros_ha' => $this->litros_ha,
            'kilos_por_vuelo' => $this->kilos_por_vuelo,
            'observaciones' => $this->observaciones,
            'emitida_por_contacto_id' => $this->emitida_por_contacto_id,
            'fecha_emision' => $this->fecha_emision->toDateString(),
            'estado' => $this->estado->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
