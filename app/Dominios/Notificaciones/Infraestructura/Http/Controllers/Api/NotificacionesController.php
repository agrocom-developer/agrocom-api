<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Avisos de la cuenta del token para las apps (especificación §12, canal
 * in-app): aprobaciones, rechazos, suspensiones y documentos por vencer de los
 * proveedores, entre otros. Solo se lee y se marca leído lo propio: el alcance
 * sale siempre de la cuenta autenticada, y un id ajeno es 404 (invariante 5).
 */
#[OA\Tag(
    name: 'Notificaciones',
    description: 'Avisos in-app de la cuenta (ADR 0025): el panel los ve en la campana; las apps, por esta API.',
)]
final class NotificacionesController
{
    private const LIMITE = 50;

    #[OA\Get(
        path: '/api/notificaciones',
        operationId: 'listarNotificaciones',
        description: 'Los avisos más recientes de la cuenta (hasta 50), los no leídos primero y después por fecha. El texto sale ya '
            .'armado en español. `no_leidas` cuenta todas las pendientes de la cuenta, aunque no entren en la lista.',
        summary: 'Mis avisos',
        security: [['tokenDispositivo' => []]],
        tags: ['Notificaciones'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avisos de la cuenta.',
                content: new OA\JsonContent(
                    required: ['data', 'no_leidas'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                required: ['id', 'tipo', 'titulo', 'recurso_tipo', 'recurso_id', 'leida', 'creada_en'],
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 71),
                                    new OA\Property(property: 'tipo', type: 'string', example: 'sujeto_aprobado'),
                                    new OA\Property(property: 'titulo', type: 'string', example: 'El proveedor «Fumigaciones del Oriente SRL» fue aprobado.'),
                                    new OA\Property(property: 'recurso_tipo', type: 'string', example: 'proveedor'),
                                    new OA\Property(property: 'recurso_id', type: 'integer', example: 12),
                                    new OA\Property(property: 'leida', type: 'boolean', example: false),
                                    new OA\Property(property: 'creada_en', type: 'string', format: 'date-time'),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(property: 'no_leidas', type: 'integer', example: 3),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $usuarioId = (int) Auth::guard('sanctum')->id();

        $avisos = Notificacion::query()
            ->deUsuario($usuarioId)
            ->orderByRaw('CASE WHEN leida_en IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(static fn (Notificacion $aviso): array => [
                'id' => $aviso->id,
                'tipo' => $aviso->tipo->value,
                'titulo' => __($aviso->tipo->claveDeTitulo(), $aviso->parametros ?? []),
                'recurso_tipo' => $aviso->recurso_tipo->value,
                'recurso_id' => $aviso->recurso_id,
                'leida' => $aviso->leida_en !== null,
                'creada_en' => $aviso->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $avisos->all(),
            'no_leidas' => Notificacion::query()->deUsuario($usuarioId)->sinLeer()->count(),
        ]);
    }

    #[OA\Post(
        path: '/api/notificaciones/{id}/leida',
        operationId: 'marcarNotificacionLeida',
        description: 'Marca como leído un aviso de la cuenta. Es idempotente: marcar un aviso ya leído no cambia nada. Un aviso de otra cuenta responde 404.',
        summary: 'Marca un aviso como leído',
        security: [['tokenDispositivo' => []]],
        tags: ['Notificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Aviso leído.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 404, description: 'El aviso no existe o no es de esta cuenta.'),
        ],
    )]
    public function leer(Request $request, int $id): JsonResponse
    {
        $aviso = Notificacion::query()
            ->deUsuario((int) Auth::guard('sanctum')->id())
            ->whereKey($id)
            ->first();

        abort_if($aviso === null, 404, __('notificaciones.errores.no_encontrada'));

        if ($aviso->leida_en === null) {
            // Fila por fila con `save()`, no `update()` masivo: cada cambio deja su fila de bitácora.
            $aviso->leida_en = now()->toImmutable();
            $aviso->save();
        }

        return response()->json(status: 204);
    }
}
