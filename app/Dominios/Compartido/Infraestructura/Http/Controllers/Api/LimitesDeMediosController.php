<?php

namespace App\Dominios\Compartido\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Aplicacion\Medios\LimiteDeMedios;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/** Los límites de archivos multimedia que las apps leen para avisar antes de subir (el servidor sigue validando). */
#[OA\Tag(name: 'Medios', description: 'Archivos multimedia: límites por subida y por entidad, y los derivados reducidos (`url_miniatura`, `url_media`) que las apps usan por defecto.')]
final class LimitesDeMediosController
{
    public function __construct(private readonly LimiteDeMedios $limites) {}

    #[OA\Get(
        path: '/api/medios/limites',
        operationId: 'verLimitesDeMedios',
        description: 'Cuántos archivos multimedia admite la aplicación. `por_subida` es el máximo de archivos que trae una sola petición; `por_entidad` es el máximo de archivos activos de cada entidad dueña: '
            .'`dron_fotos` (fotos de un dron), `terreno_fotos` (fotos de un terreno), `documentos` (documentos en revisión de un proveedor, piloto o dron), `servicio_avance` (fotos de avance de un servicio) y '
            .'`servicio_chat` (fotos del chat de un servicio). Pasarse responde 422 con un mensaje en español. Los valores se parametrizan en el servidor (`config/medios.php`); la app no debe fijarlos.',
        summary: 'Límites de archivos multimedia',
        security: [['tokenDispositivo' => []]],
        tags: ['Medios'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Los límites vigentes.',
                content: new OA\JsonContent(
                    required: ['por_subida', 'por_entidad', 'peso_maximo_kb', 'formatos_imagen'],
                    properties: [
                        new OA\Property(property: 'por_subida', type: 'integer', example: 10),
                        new OA\Property(property: 'por_entidad', type: 'object', example: ['dron_fotos' => 10, 'terreno_fotos' => 10, 'documentos' => 10, 'servicio_avance' => 10, 'servicio_chat' => 10], additionalProperties: new OA\AdditionalProperties(type: 'integer')),
                        new OA\Property(property: 'peso_maximo_kb', description: 'Peso máximo de una foto: la app la comprime antes de subirla.', type: 'integer', example: 300),
                        new OA\Property(property: 'formatos_imagen', type: 'array', items: new OA\Items(type: 'string'), example: ['jpg', 'jpeg', 'png', 'webp']),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
        ],
    )]
    public function index(): JsonResponse
    {
        return response()->json($this->limites->resumen());
    }
}
