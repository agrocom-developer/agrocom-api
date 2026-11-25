<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Notificaciones\Aplicacion\DispositivosPush;
use App\Dominios\Notificaciones\Infraestructura\Http\Requests\RegistrarDispositivoPushRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Token de push del aparato de la cuenta (`negociacion-contrato.md` §7). Cualquier
 * cuenta con sesión registra y retira los suyos: el alcance sale del token de la
 * sesión, nunca del cuerpo, y retirar el token de otra cuenta responde 404.
 */
#[OA\Schema(
    schema: 'DispositivoPush',
    title: 'Token de push registrado',
    required: ['id', 'plataforma', 'registrado_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 14),
        new OA\Property(property: 'plataforma', type: 'string', enum: ['android', 'ios']),
        new OA\Property(property: 'registrado_en', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class DispositivosPushController
{
    public function __construct(private readonly DispositivosPush $dispositivos) {}

    #[OA\Post(
        path: '/api/dispositivos',
        operationId: 'registrarDispositivoPush',
        description: 'La app registra el token de push de su aparato al iniciar sesión. Idempotente por token: repetirlo responde 200 y deja una sola fila activa. '
            .'Si el token estaba registrado a otra cuenta (el aparato cambió de sesión sin retirarlo), pasa a la cuenta actual. '
            .'El envío al sistema operativo requiere un proveedor de push configurado; mientras no lo esté, los avisos se consultan en `GET /api/notificaciones`. '
            .'El token no se devuelve ni se registra en logs.',
        summary: 'Registrar el token de push del aparato',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'plataforma'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', maxLength: 512, example: 'fcm:cJ3x...'),
                    new OA\Property(property: 'plataforma', type: 'string', enum: ['android', 'ios']),
                ],
                type: 'object',
            ),
        ),
        tags: ['Notificaciones'],
        responses: [
            new OA\Response(response: 201, description: 'Token registrado.', content: new OA\JsonContent(ref: '#/components/schemas/DispositivoPush')),
            new OA\Response(response: 200, description: 'El token ya estaba registrado; queda a nombre de la cuenta actual.', content: new OA\JsonContent(ref: '#/components/schemas/DispositivoPush')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 422, description: 'Token o plataforma inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(RegistrarDispositivoPushRequest $request): JsonResponse
    {
        $datos = $request->validated();

        [$dispositivo, $nuevo] = $this->dispositivos->registrar($this->usuarioId(), $datos['token'], $datos['plataforma']);

        return response()->json([
            'id' => $dispositivo->id,
            'plataforma' => $dispositivo->plataforma,
            'registrado_en' => $dispositivo->ultimo_uso_en?->toIso8601String(),
        ], $nuevo ? 201 : 200);
    }

    #[OA\Delete(
        path: '/api/dispositivos/{token}',
        operationId: 'retirarDispositivoPush',
        description: 'Retira el token de push del aparato, al cerrar sesión. Un token que no existe o es de otra cuenta responde 404. '
            .'Va aparte de `DELETE /api/auth/token`, que revoca la sesión del aparato.',
        summary: 'Retirar el token de push del aparato',
        security: [['tokenDispositivo' => []]],
        tags: ['Notificaciones'],
        parameters: [new OA\Parameter(name: 'token', in: 'path', required: true, description: 'El token de push, codificado para URL.', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 204, description: 'Token retirado.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 404, description: 'El token no existe o es de otra cuenta.'),
        ],
    )]
    public function destroy(string $token): JsonResponse
    {
        abort_unless($this->dispositivos->retirar($this->usuarioId(), $token), 404, __('notificaciones.errores.dispositivo_no_encontrado'));

        return response()->json(status: 204);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
