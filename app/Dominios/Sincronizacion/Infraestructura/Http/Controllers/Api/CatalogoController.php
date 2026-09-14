<?php

namespace App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Sincronizacion\Aplicacion\ObtenerCatalogoDesdeCursor;
use App\Dominios\Sincronizacion\Infraestructura\Http\Requests\ObtenerCatalogoRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Adaptador delgado (ADR 0008): valida la query string, invoca el caso de
 * uso y devuelve la respuesta — ninguna regla de negocio vive acá.
 */
#[OA\Tag(
    name: 'Sincronizacion',
    description: 'Pull de catálogo con cursor para la app de campo (espec §2.1, punto 6). '
        .'TE-06 parcial: órdenes, lotes, personas y trabajos asignados desde el panel (HU-70) — '
        .'recetas y productos de mezcla quedan fuera a propósito (ver docblock de '
        .'`ObtenerCatalogoDesdeCursor`, HU-78/tarea 94): el piloto transcribe el producto por '
        .'nombre en el propio evento `mezcla` de `POST /api/sync`, sin necesitar bajarlo antes.',
)]
#[OA\Schema(
    schema: 'LoteDeOrdenCatalogo',
    title: 'Lote de una orden (catálogo)',
    description: 'Uno de los lotes que cubre una orden, con las hectáreas que le pidió a ESE lote '
        .'(HU-92, tarea 107 — una orden puede cubrir varios lotes de la propiedad). DECIMAL como string (invariante 6).',
    required: ['lote_id', 'hectareas_solicitadas'],
    properties: [
        new OA\Property(property: 'lote_id', description: 'Lote que cubre la orden (módulo Comercial, solo ID).', type: 'integer', example: 3),
        new OA\Property(property: 'hectareas_solicitadas', description: 'Hectáreas que la orden le pidió a ESE lote. DECIMAL como string.', type: 'string', example: '50.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrdenCatalogo',
    title: 'Orden de aplicación (catálogo)',
    description: 'Orden vigente para el pull de catálogo (espec §4.3, ampliada HU-92 tarea 107: '
        .'`lotes` reemplaza el `lote_id` único de antes; HU-79 tarea 110: `litros_ha`/`kilos_por_vuelo` '
        .'son mutuamente excluyentes según la categoría de insumo). Los DECIMAL viajan como string (invariante 6).',
    required: [
        'id', 'contrato_id', 'lotes', 'nro_aplicacion', 'litros_ha', 'kilos_por_vuelo', 'humedad_min_pct',
        'viento_max_kmh', 'temperatura_max_c', 'humedad_max_pct', 'velocidad_max_kmh',
        'altura_vuelo_m', 'velocidad_vuelo_kmh', 'ancho_pasada_m', 'observaciones',
        'emitida_por_contacto_id', 'fecha_emision', 'estado', 'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'contrato_id', type: 'integer', example: 1),
        new OA\Property(
            property: 'lotes',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/LoteDeOrdenCatalogo'),
        ),
        new OA\Property(property: 'nro_aplicacion', type: 'integer', example: 1),
        new OA\Property(property: 'litros_ha', description: 'Dosis en litros por hectárea (insumo líquido); null si la categoría es sólida.', type: 'string', example: '10.00', nullable: true),
        new OA\Property(property: 'kilos_por_vuelo', description: 'Dosis en kilos por vuelo (insumo sólido); null si la categoría es líquida.', type: 'string', example: '8.50', nullable: true),
        new OA\Property(property: 'humedad_min_pct', type: 'string', example: '60.00', nullable: true),
        new OA\Property(property: 'viento_max_kmh', type: 'string', example: '15.00', nullable: true),
        new OA\Property(property: 'temperatura_max_c', type: 'string', example: '32.00', nullable: true),
        new OA\Property(property: 'humedad_max_pct', type: 'string', example: '90.00', nullable: true),
        new OA\Property(property: 'velocidad_max_kmh', type: 'string', example: '25.00', nullable: true),
        new OA\Property(property: 'altura_vuelo_m', type: 'string', example: '3.00', nullable: true),
        new OA\Property(property: 'velocidad_vuelo_kmh', type: 'string', example: '18.00', nullable: true),
        new OA\Property(property: 'ancho_pasada_m', type: 'string', example: '7.00', nullable: true),
        new OA\Property(property: 'observaciones', type: 'string', example: 'Aplicar en horas de la mañana.', nullable: true),
        new OA\Property(property: 'emitida_por_contacto_id', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'fecha_emision', type: 'string', format: 'date', example: '2026-08-26'),
        new OA\Property(property: 'estado', type: 'string', example: 'vigente'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LoteCatalogo',
    title: 'Lote (catálogo)',
    description: 'Lote vigente (no borrado) para el pull de catálogo (espec §4.1). `hectareas` es DECIMAL como string (invariante 6).',
    required: ['id', 'campo_id', 'codigo', 'hectareas', 'geometria', 'restricciones', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'campo_id', type: 'integer', example: 1),
        new OA\Property(property: 'codigo', type: 'string', example: 'L-01'),
        new OA\Property(property: 'hectareas', type: 'string', example: '120.50'),
        new OA\Property(property: 'geometria', description: 'GeoJSON del lote, o null si no está cargado.', type: 'object', nullable: true),
        new OA\Property(property: 'restricciones', type: 'string', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PersonaCatalogo',
    title: 'Persona operativa (catálogo)',
    description: 'Persona vigente (no borrada) para el pull de catálogo (espec §4.2).',
    required: ['id', 'nombre', 'rol', 'base_id', 'activo', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'nombre', type: 'string', example: 'Piloto Uno'),
        new OA\Property(property: 'rol', type: 'string', example: 'piloto'),
        new OA\Property(property: 'base_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'activo', type: 'boolean', example: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TrabajoCatalogo',
    title: 'Trabajo asignado desde el panel (catálogo)',
    description: 'Trabajo abierto por el jefe de campo al repartir una orden vigente entre equipos '
        .'(HU-70, tarea 85) — nunca los que nacen por sync (`equipo_trabajo_id` siempre presente acá). '
        .'`uuid_cliente` es el que generó el panel al confirmar la asignación: la app lo usa TAL CUAL '
        .'para abrir sesiones sobre este trabajo. `hectareas_declaradas` es DECIMAL como string (invariante 6).',
    required: ['id', 'uuid_cliente', 'orden_id', 'lote_id', 'hectareas_declaradas', 'equipo_trabajo_id', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'uuid_cliente', type: 'string', example: '9a1b7e3e-2f7a-4b3d-8c1e-6f2a1d9c4b0a'),
        new OA\Property(property: 'orden_id', type: 'integer', example: 1),
        new OA\Property(property: 'lote_id', type: 'integer', example: 3),
        new OA\Property(property: 'hectareas_declaradas', type: 'string', example: '300.00'),
        new OA\Property(property: 'equipo_trabajo_id', type: 'integer', example: 7),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-09-13T12:00:00+00:00'),
    ],
    type: 'object',
)]
final class CatalogoController
{
    #[OA\Get(
        path: '/api/sync/catalogo',
        operationId: 'obtenerCatalogoSincronizacion',
        description: 'Baja el catálogo de órdenes vigentes, lotes, personas y trabajos asignados '
            .'desde el panel modificados desde la posición del cursor recibido, con paginación por '
            .'cursor (`updated_at`, `id`) — nunca por número de página, para no perder ni repetir '
            .'registros entre pulls. `desde` vacío o ausente trae todo lo vigente (primera '
            .'sincronización). Un `desde` no decodificable se trata igual que vacío, nunca como '
            .'error de validación.',
        summary: 'Pull de catálogo con cursor (órdenes, lotes, personas, trabajos)',
        security: [['tokenDispositivo' => []]],
        tags: ['Sincronizacion'],
        parameters: [
            new OA\Parameter(
                name: 'desde',
                description: 'Cursor opaco devuelto por el pull anterior. Ausente o vacío en la primera sincronización.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo modificado desde el cursor, y el cursor de continuación para el próximo pull.',
                content: new OA\JsonContent(
                    required: ['ordenes', 'lotes', 'personas', 'trabajos', 'cursor'],
                    properties: [
                        new OA\Property(
                            property: 'ordenes',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/OrdenCatalogo'),
                        ),
                        new OA\Property(
                            property: 'lotes',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/LoteCatalogo'),
                        ),
                        new OA\Property(
                            property: 'personas',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/PersonaCatalogo'),
                        ),
                        new OA\Property(
                            property: 'trabajos',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/TrabajoCatalogo'),
                        ),
                        new OA\Property(
                            property: 'cursor',
                            description: 'Cursor opaco a reenviar tal cual en `desde` en el próximo pull.',
                            type: 'string',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
        ],
    )]
    public function index(
        ObtenerCatalogoRequest $request,
        ObtenerCatalogoDesdeCursor $obtenerCatalogo,
    ): JsonResponse {
        /** @var array<string, mixed> $filtros */
        $filtros = $request->validated();

        return response()->json($obtenerCatalogo->ejecutar(
            isset($filtros['desde']) ? (string) $filtros['desde'] : null,
        ));
    }
}
