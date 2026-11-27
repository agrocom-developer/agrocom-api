<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Flota\Aplicacion\GestionarFotosDeDron;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Http\Requests\SubirFotoDeDronRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fotos del dron (`negociacion-contrato.md` §9): el proveedor las sube y el administrador las
 * aprueba; el cliente ve las aprobadas al comparar ofertas. Requieren red.
 */
#[OA\Schema(
    schema: 'FotoDeDron',
    title: 'Foto de un dron',
    required: ['id', 'dron_id', 'tipo', 'estado', 'url'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'dron_id', type: 'integer'),
        new OA\Property(property: 'tipo', type: 'string', enum: ['frontal', 'lateral', 'placa_serie', 'otra']),
        new OA\Property(property: 'estado', type: 'string', enum: ['en_revision', 'aprobada', 'rechazada']),
        new OA\Property(property: 'motivo', description: 'Motivo del rechazo.', type: 'string', nullable: true),
        new OA\Property(property: 'url', description: 'El original; se pide con el mismo token.', type: 'string', example: '/api/drones/fotos/5/archivo'),
        new OA\Property(property: 'url_miniatura', description: 'Derivado chico para listas; úsalo por defecto y pide el original solo en el visor.', type: 'string', example: '/api/drones/fotos/5/archivo?variante=miniatura'),
        new OA\Property(property: 'url_media', description: 'Derivado medio para la vista previa.', type: 'string', example: '/api/drones/fotos/5/archivo?variante=media'),
    ],
    type: 'object',
)]
final class FotosDeDronController
{
    private const PERMISO = 'proveedores.cuenta.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly GestionarFotosDeDron $fotos,
    ) {}

    #[OA\Post(
        path: '/api/proveedores/drones/{id}/fotos',
        operationId: 'subirFotoDeDron',
        description: 'Sube una foto del dron (multipart, menos de 300 KB). Queda `en_revision` hasta que un administrador la aprueba o la rechaza con motivo. Fotos obligatorias para aprobar el dron: frontal, lateral y placa de serie.',
        summary: 'Subir una foto del dron',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['tipo', 'archivo'], properties: [
            new OA\Property(property: 'tipo', type: 'string', enum: ['frontal', 'lateral', 'placa_serie', 'otra']),
            new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
        ], type: 'object'))),
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Foto guardada.', content: new OA\JsonContent(required: ['foto'], properties: [new OA\Property(property: 'foto', ref: '#/components/schemas/FotoDeDron')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona drones.'),
            new OA\Response(response: 404, description: 'El dron no existe o es de otro proveedor.'),
            new OA\Response(response: 422, description: 'Archivo inválido, de 300 KB o más, o el dron ya tiene el máximo de fotos (`GET /api/medios/limites`).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(SubirFotoDeDronRequest $request, int $id): JsonResponse
    {
        $this->exigir($request);
        $datos = $request->validated();

        try {
            return response()->json(['foto' => $this->fotos->subir($this->usuarioId(), $id, $datos['tipo'], $request->file('archivo'))], 201);
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }
    }

    #[OA\Get(
        path: '/api/proveedores/drones/{id}/fotos',
        operationId: 'listarFotosDeDron',
        description: 'Las fotos de un dron propio con su estado de revisión.',
        summary: 'Fotos del dron',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Fotos.', content: new OA\JsonContent(required: ['fotos'], properties: [new OA\Property(property: 'fotos', type: 'array', items: new OA\Items(ref: '#/components/schemas/FotoDeDron'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona drones.'),
            new OA\Response(response: 404, description: 'El dron no existe o es de otro proveedor.'),
        ],
    )]
    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigir($request);

        try {
            return response()->json(['fotos' => $this->fotos->listar($this->usuarioId(), $id)]);
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }
    }

    #[OA\Delete(
        path: '/api/proveedores/drones/{id}/fotos/{foto}',
        operationId: 'borrarFotoDeDron',
        description: 'Borra una foto propia, solo mientras está `en_revision` o `rechazada`.',
        summary: 'Borrar una foto del dron',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'foto', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Foto borrada.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona drones.'),
            new OA\Response(response: 404, description: 'La foto o el dron no existen o son de otro proveedor.'),
            new OA\Response(response: 422, description: 'La foto ya está aprobada.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function destroy(Request $request, int $id, int $foto): JsonResponse
    {
        $this->exigir($request);

        try {
            $this->fotos->borrar($this->usuarioId(), $id, $foto);
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/drones/fotos/{foto}/archivo',
        operationId: 'descargarFotoDeDron',
        description: 'El archivo de una foto de dron: las aprobadas las ve cualquier cuenta autenticada (el cliente al comparar ofertas); las demás, solo el proveedor dueño.',
        summary: 'Descargar una foto del dron',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        parameters: [
            new OA\Parameter(name: 'foto', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variante', in: 'query', required: false, description: 'Tamaño: `miniatura` o `media` (derivados reducidos, para listas y vistas previas) u `original` (por omisión). Si el derivado aún no se generó, se entrega el original.', schema: new OA\Schema(type: 'string', enum: ['miniatura', 'media', 'original'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'La imagen.', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 404, description: 'No existe o no es visible para la cuenta.'),
        ],
    )]
    public function archivo(Request $request, int $foto): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->fotos->rutaVisible($this->usuarioId(), $foto, VarianteDeMedio::desde($request->query('variante')));

        return $ruta === null ? response()->json(['message' => __('flota.errores.dron_ajeno')], 404) : response()->file($ruta, ['Cache-Control' => 'private, max-age=3600']);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigir(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }
}
