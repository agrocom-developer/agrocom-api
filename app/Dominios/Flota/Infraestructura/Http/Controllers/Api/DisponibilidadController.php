<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Flota\Aplicacion\DeclararDisponibilidad;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Disponibilidad;
use App\Dominios\Flota\Infraestructura\Http\Requests\DeclararDisponibilidadRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Disponibilidad «en línea» de los equipos del proveedor (especificación §4.4 y
 * §10.2). Requiere red: sin señal el proveedor no recibe ofertas de todos modos.
 */
#[OA\Schema(
    schema: 'Disponibilidad',
    title: 'Disponibilidad de un equipo',
    required: ['equipo_id', 'en_linea', 'actualizado_en'],
    properties: [
        new OA\Property(property: 'equipo_id', type: 'integer', example: 4),
        new OA\Property(property: 'en_linea', type: 'boolean'),
        new OA\Property(property: 'lat', type: 'string', nullable: true, example: '-17.7833000'),
        new OA\Property(property: 'lng', type: 'string', nullable: true, example: '-63.1821000'),
        new OA\Property(property: 'actualizado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class DisponibilidadController
{
    private const PERMISO = 'flota.disponibilidad.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly DeclararDisponibilidad $declarar,
    ) {}

    #[OA\Get(
        path: '/api/disponibilidad',
        operationId: 'consultarDisponibilidad',
        description: 'La disponibilidad declarada de los equipos vigentes de la cuenta. Un equipo sin fila nunca declaró nada y cuenta como fuera de línea.',
        summary: 'Mi disponibilidad',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 200, description: 'Disponibilidad por equipo.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Disponibilidad'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona disponibilidad.'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $this->exigirPermiso($request);

        return response()->json(['data' => array_map($this->fila(...), $this->declarar->consultar($this->usuarioId()))]);
    }

    #[OA\Put(
        path: '/api/disponibilidad',
        operationId: 'declararDisponibilidad',
        description: 'Pone en línea o fuera de línea un equipo (`equipo_id`) o, sin él, todos los equipos vigentes de la cuenta. Una solicitud urgente solo se ofrece a quien está en línea. '
            .'`lat` y `lng` actualizan la última ubicación, que mide la distancia a los terrenos. Idempotente.',
        summary: 'Declararme en línea o fuera de línea',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['en_linea'],
                properties: [
                    new OA\Property(property: 'en_linea', type: 'boolean'),
                    new OA\Property(property: 'equipo_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'lat', type: 'number', nullable: true, example: -17.7833),
                    new OA\Property(property: 'lng', type: 'number', nullable: true, example: -63.1821),
                ],
                type: 'object',
            ),
        ),
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 200, description: 'Disponibilidad actualizada.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Disponibilidad'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona disponibilidad.'),
            new OA\Response(response: 404, description: 'El equipo no es de la cuenta.'),
            new OA\Response(response: 422, description: 'Datos inválidos o la cuenta no tiene equipos vigentes.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(DeclararDisponibilidadRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $filas = $this->declarar->ejecutar(
                $this->usuarioId(),
                $request->boolean('en_linea'),
                $request->filled('lat') ? (string) $request->input('lat') : null,
                $request->filled('lng') ? (string) $request->input('lng') : null,
                $request->filled('equipo_id') ? (int) $request->input('equipo_id') : null,
            );
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['data' => array_map($this->fila(...), $filas)]);
    }

    /** @return array<string, mixed> */
    private function fila(Disponibilidad $fila): array
    {
        return [
            'equipo_id' => $fila->equipo_id,
            'en_linea' => $fila->en_linea,
            'lat' => $fila->ubicacion_lat === null ? null : (string) $fila->ubicacion_lat,
            'lng' => $fila->ubicacion_lng === null ? null : (string) $fila->ubicacion_lng,
            'actualizado_en' => $fila->actualizado_en->toIso8601String(),
        ];
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }
}
