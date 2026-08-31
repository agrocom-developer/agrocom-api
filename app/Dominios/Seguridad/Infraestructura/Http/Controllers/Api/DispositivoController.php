<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\BuscarDispositivoDeUsuario;
use App\Dominios\Seguridad\Aplicacion\ListarDispositivosDeUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\DispositivoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * Dispositivos con sesión viva del operario autenticado (HU-03).
 *
 * Adaptador delgado (ADR 0008). Todo el scoping vive en los casos de uso, que
 * consultan desde la relación del usuario del token y nunca desde la tabla
 * global con un `where` agregado al final — misma regla que la invariante 5
 * de CLAUDE.md fija para el portal del cliente, y con la misma consecuencia:
 * pedir el dispositivo de otro devuelve 404, no 403.
 */
final class DispositivoController
{
    #[OA\Get(
        path: '/api/dispositivos',
        operationId: 'listarDispositivosPropios',
        description: 'Dispositivos donde el operario autenticado tiene sesión abierta. Solo los '
            .'suyos: no existe forma de listar los de otro usuario desde la app de campo.',
        summary: 'Lista los dispositivos del operario autenticado',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dispositivos con sesión viva, el usado más recientemente primero.',
                content: new OA\JsonContent(
                    required: ['data'],
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Dispositivo')),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
        ],
    )]
    public function index(Request $request, ListarDispositivosDeUsuario $listarDispositivos): AnonymousResourceCollection
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return DispositivoResource::collection($listarDispositivos->ejecutar($usuario));
    }

    #[OA\Get(
        path: '/api/dispositivos/{id}',
        operationId: 'verDispositivoPropio',
        description: 'Un dispositivo del operario autenticado. Un id que no le pertenece devuelve '
            .'404 y no 403: un 403 confirmaría que ese dispositivo existe.',
        summary: 'Muestra un dispositivo del operario autenticado',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Identificador del dispositivo.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'El dispositivo pedido.',
                content: new OA\JsonContent(
                    required: ['data'],
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Dispositivo')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 404, description: 'No existe, o es de otro usuario — indistinguibles a propósito.'),
        ],
    )]
    public function show(Request $request, int $id, BuscarDispositivoDeUsuario $buscarDispositivo): DispositivoResource
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return new DispositivoResource($buscarDispositivo->ejecutar($usuario, $id));
    }
}
