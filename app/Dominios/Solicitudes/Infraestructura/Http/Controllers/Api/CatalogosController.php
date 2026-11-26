<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Solicitudes\Contratos\LecturaUbicaciones;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/** Catálogos públicos de solo lectura que las dos apps necesitan antes de tener cuenta (ADR 0045, punto 3). */
final class CatalogosController
{
    public function __construct(private readonly LecturaUbicaciones $ubicaciones) {}

    #[OA\Get(
        path: '/api/catalogos/ubicaciones',
        operationId: 'listarUbicaciones',
        description: 'Catálogo oficial de Bolivia: los 9 departamentos y sus provincias, con el código del INE (dos dígitos el departamento, cuatro la provincia), ordenados por código. '
            .'De solo lectura y sin token: las apps lo usan para elegir la ubicación de un terreno sin llevar una copia propia. Solo el departamento sirve de filtro de búsqueda; la provincia informa.',
        summary: 'Catálogo de departamentos y provincias',
        tags: ['Clientes'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Departamentos con sus provincias.',
                content: new OA\JsonContent(
                    required: ['data'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                required: ['codigo', 'nombre', 'provincias'],
                                properties: [
                                    new OA\Property(property: 'codigo', type: 'string', example: '07'),
                                    new OA\Property(property: 'nombre', type: 'string', example: 'Santa Cruz'),
                                    new OA\Property(
                                        property: 'provincias',
                                        type: 'array',
                                        items: new OA\Items(
                                            required: ['codigo', 'nombre'],
                                            properties: [
                                                new OA\Property(property: 'codigo', type: 'string', example: '0702'),
                                                new OA\Property(property: 'nombre', type: 'string', example: 'Warnes'),
                                            ],
                                            type: 'object',
                                        ),
                                    ),
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
    public function ubicaciones(): JsonResponse
    {
        return response()->json(['data' => $this->ubicaciones->catalogo()]);
    }
}
