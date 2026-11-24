<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/** Catálogo público de zonas (especificación §4.1): el formulario de registro de un proveedor lo necesita antes de tener cuenta. */
final class ZonasController
{
    #[OA\Get(
        path: '/api/zonas',
        operationId: 'listarZonas',
        description: 'Zonas geográficas del marketplace con su centroide y radio, ordenadas por nombre. Sin autenticación: el '
            .'registro de un proveedor las usa para elegir su zona base. Coordenadas y radio como string decimal.',
        summary: 'Catálogo de zonas',
        tags: ['Clientes'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Zonas.',
                content: new OA\JsonContent(
                    required: ['data'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                required: ['id', 'nombre', 'centroide_lat', 'centroide_lng', 'radio_km'],
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nombre', type: 'string', example: 'Santa Cruz de la Sierra'),
                                    new OA\Property(property: 'centroide_lat', type: 'string', example: '-17.7833000'),
                                    new OA\Property(property: 'centroide_lng', type: 'string', example: '-63.1821000'),
                                    new OA\Property(property: 'radio_km', type: 'string', example: '40.0'),
                                ],
                                type: 'object',
                            ),
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        $zonas = Zona::query()->orderBy('nombre')->get()->map(static fn (Zona $zona): array => [
            'id' => $zona->id,
            'nombre' => $zona->nombre,
            'centroide_lat' => $zona->centroide_lat,
            'centroide_lng' => $zona->centroide_lng,
            'radio_km' => $zona->radio_km,
        ]);

        return response()->json(['data' => $zonas]);
    }
}
