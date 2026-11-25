<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\GestionarFotoDePerfil;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarFotoDePerfilRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Foto de perfil de la persona de la cuenta. Cada cuenta escribe solo la suya: el alcance
 * sale del token, nunca de un id del request. La imagen se entrega por una URL firmada de
 * corta vida que solo emite el servidor a quien tiene derecho a ver a esa persona.
 */
final class FotoDePerfilController
{
    public function __construct(private readonly GestionarFotoDePerfil $fotos) {}

    #[OA\Put(
        path: '/api/perfil/foto',
        operationId: 'subirFotoDePerfil',
        description: 'La cuenta del token sube o reemplaza su foto de perfil (multipart, menos de 300 KB: la app la comprime antes). '
            .'Sirve a clientes, proveedores, pilotos y auxiliares. El cliente la ve en la tarjeta del proveedor al comparar ofertas. '
            .'La foto anterior se borra del disco privado.',
        summary: 'Subir mi foto de perfil',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['archivo'], properties: [
            new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
        ], type: 'object'))),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(response: 200, description: 'Foto guardada.', content: new OA\JsonContent(required: ['foto_url'], properties: [new OA\Property(property: 'foto_url', description: 'URL firmada y de corta vida; se pide sin token.', type: 'string', example: '/api/personas/7/foto?expires=1764000000&signature=ab12')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 422, description: 'Archivo inválido o de 300 KB o más.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(ActualizarFotoDePerfilRequest $request): JsonResponse
    {
        return response()->json(['foto_url' => $this->fotos->subir($this->usuarioId(), $request->file('archivo'))]);
    }

    #[OA\Delete(
        path: '/api/perfil/foto',
        operationId: 'quitarFotoDePerfil',
        description: 'Quita la foto de perfil de la cuenta del token y borra el archivo. Idempotente: sin foto responde igual.',
        summary: 'Quitar mi foto de perfil',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(response: 204, description: 'Sin foto de perfil.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
        ],
    )]
    public function destroy(): JsonResponse
    {
        $this->fotos->quitar($this->usuarioId());

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/personas/{usuario}/foto',
        operationId: 'descargarFotoDePerfil',
        description: 'La imagen de perfil de una cuenta. No lleva token: la URL es firmada y de corta vida (15 minutos), y solo la entregan la propia sesión y la tarjeta del proveedor '
            .'que ve el cliente al comparar ofertas. Una firma inválida o vencida responde 403.',
        summary: 'Descargar una foto de perfil',
        tags: ['Seguridad'],
        parameters: [
            new OA\Parameter(name: 'usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'La imagen.', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 403, description: 'Firma inválida o vencida.'),
            new OA\Response(response: 404, description: 'La cuenta ya no tiene foto.'),
        ],
    )]
    public function archivo(int $usuario): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->fotos->ruta($usuario);

        return $ruta === null
            ? response()->json(['message' => __('seguridad.errores.foto_no_encontrada')], 404)
            : response()->file($ruta, ['Cache-Control' => 'private, max-age=600']);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
