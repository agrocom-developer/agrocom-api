<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Operaciones\Aplicacion\ListarOrdenesAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ListarOrdenesRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Resources\OrdenAplicacionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * Adaptador delgado (ADR 0008): valida la query string, invoca el caso de uso
 * y serializa — ninguna regla de negocio vive acá. Los atributos OA\...
 * documentan el endpoint donde vive (ADR 0014): un tag por módulo.
 */
#[OA\Tag(
    name: 'Operaciones',
    description: 'Órdenes de aplicación y, más adelante, trabajos y sesiones de vuelo (espec §5).',
)]
final class OrdenAplicacionController
{
    #[OA\Get(
        path: '/api/ordenes',
        operationId: 'listarOrdenes',
        description: 'Listado paginado de órdenes de aplicación para la app de campo (espec §8). '
            .'Exige el token del dispositivo desde HU-03: dejó de ser público. '
            .'Todos los filtros se combinan por AND; si `vigentes` y `estado` se contradicen, el '
            .'resultado es vacío — no hay prevalencia silenciosa entre filtros. Las órdenes '
            .'borradas lógicamente nunca aparecen.',
        summary: 'Lista las órdenes de aplicación con filtros',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        parameters: [
            new OA\Parameter(
                name: 'estado',
                description: 'Filtra por estado de la orden.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: EstadoOrdenAplicacion::class),
            ),
            new OA\Parameter(
                name: 'lote_id',
                description: 'Filtra por lote (ID del módulo Comercial).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
            new OA\Parameter(
                name: 'contrato_id',
                description: 'Filtra por contrato (ID del módulo Comercial).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
            new OA\Parameter(
                name: 'nro_aplicacion',
                description: 'Filtra por número de aplicación dentro del contrato (1..n).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
            new OA\Parameter(
                name: 'vigentes',
                description: 'Con valor verdadero devuelve solo las órdenes vigentes (atajo para la app del piloto; equivale a `estado=vigente`).',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false),
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Registros por página.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1),
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Número de página.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de órdenes, ordenado por fecha de emisión descendente.',
                content: new OA\JsonContent(
                    required: ['data', 'links', 'meta'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/OrdenAplicacion'),
                        ),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginacionLinks'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginacionMeta'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(
                response: 422,
                description: 'Algún filtro no pasó la validación (estado desconocido, per_page fuera de 1..100, etc.).',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion'),
            ),
        ],
    )]
    public function index(
        ListarOrdenesRequest $request,
        ListarOrdenesAplicacion $listarOrdenes,
    ): AnonymousResourceCollection {
        /** @var array<string, mixed> $filtros */
        $filtros = $request->validated();

        $ordenes = $listarOrdenes->ejecutar(
            estado: isset($filtros['estado']) ? EstadoOrdenAplicacion::from((string) $filtros['estado']) : null,
            loteId: isset($filtros['lote_id']) ? (int) $filtros['lote_id'] : null,
            contratoId: isset($filtros['contrato_id']) ? (int) $filtros['contrato_id'] : null,
            nroAplicacion: isset($filtros['nro_aplicacion']) ? (int) $filtros['nro_aplicacion'] : null,
            soloVigentes: filter_var($filtros['vigentes'] ?? false, FILTER_VALIDATE_BOOL),
            porPagina: isset($filtros['per_page']) ? (int) $filtros['per_page'] : 15,
        );

        return OrdenAplicacionResource::collection($ordenes->withQueryString());
    }
}
