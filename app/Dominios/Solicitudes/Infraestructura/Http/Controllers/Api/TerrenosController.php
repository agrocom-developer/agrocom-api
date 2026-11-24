<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\GestionarTerrenos;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CrearTerrenoRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Resources\TerrenoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Terrenos del cliente (especificación §10.1, `GET/POST/PUT /terrenos`).
 * Adaptador delgado (ADR 0008): la regla y el alcance (solo lo propio, un id
 * ajeno es 404) viven en {@see GestionarTerrenos}.
 */
#[OA\Tag(
    name: 'Terrenos',
    description: 'Terrenos que el cliente registra una vez y reutiliza en cada solicitud de servicio (ADR 0027).',
)]
#[OA\Schema(
    schema: 'TerrenoEntrada',
    title: 'Terreno (datos que envía el cliente)',
    description: 'Cuerpo de `POST /api/terrenos` y de `PUT /api/terrenos/{id}`: solo lo que el cliente escribe, sin `id`. Las hectáreas viajan como número o cadena decimal con hasta cuatro decimales (invariante 6).',
    required: ['nombre', 'hectareas'],
    properties: [
        new OA\Property(property: 'nombre', type: 'string', example: 'Lote Norte'),
        new OA\Property(property: 'hectareas', type: 'string', example: '120.5'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true),
        new OA\Property(property: 'geometria', description: 'Polígono GeoJSON (`Polygon` o `MultiPolygon`).', type: 'object', nullable: true),
        new OA\Property(property: 'referencias_acceso', type: 'string', nullable: true),
        new OA\Property(property: 'colindantes_sensibles', type: 'string', nullable: true),
        new OA\Property(property: 'acceso_dificil', type: 'boolean', nullable: true),
        new OA\Property(property: 'zona_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
final class TerrenosController
{
    private const PERMISO = 'solicitudes.terreno.gestionar';

    public function __construct(
        private readonly GestionarTerrenos $terrenos,
        private readonly IdentidadOperarioToken $identidad,
    ) {}

    #[OA\Get(
        path: '/api/terrenos',
        operationId: 'listarTerrenos',
        description: 'Los terrenos del cliente de la cuenta del token, por nombre.',
        summary: 'Mis terrenos',
        security: [['tokenDispositivo' => []]],
        tags: ['Terrenos'],
        responses: [
            new OA\Response(response: 200, description: 'Terrenos del cliente.', content: new OA\JsonContent(required: ['terrenos'], properties: [new OA\Property(property: 'terrenos', type: 'array', items: new OA\Items(ref: '#/components/schemas/Terreno'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La cuenta no tiene ficha de cliente.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $terrenos = $this->terrenos->listar($this->usuarioId());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['terrenos' => TerrenoResource::collection($terrenos)]);
    }

    #[OA\Get(
        path: '/api/terrenos/{id}',
        operationId: 'verTerreno',
        summary: 'Un terreno mío',
        security: [['tokenDispositivo' => []]],
        tags: ['Terrenos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El terreno.', content: new OA\JsonContent(required: ['terreno'], properties: [new OA\Property(property: 'terreno', ref: '#/components/schemas/Terreno')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $terreno = $this->terrenos->obtener($this->usuarioId(), $id);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['terreno' => new TerrenoResource($terreno)]);
    }

    #[OA\Post(
        path: '/api/terrenos',
        operationId: 'crearTerreno',
        description: 'Registra un terreno del cliente. Las hectáreas se envían como número o cadena decimal con hasta cuatro decimales.',
        summary: 'Registrar un terreno',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TerrenoEntrada')),
        tags: ['Terrenos'],
        responses: [
            new OA\Response(response: 201, description: 'Terreno creado.', content: new OA\JsonContent(required: ['terreno'], properties: [new OA\Property(property: 'terreno', ref: '#/components/schemas/Terreno')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o cliente suspendido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CrearTerrenoRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $terreno = $this->terrenos->crear($this->usuarioId(), $request->validated());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['terreno' => new TerrenoResource($terreno)], 201);
    }

    #[OA\Put(
        path: '/api/terrenos/{id}',
        operationId: 'actualizarTerreno',
        description: 'Corrige los datos de un terreno propio. Las solicitudes ya creadas conservan sus propias hectáreas.',
        summary: 'Actualizar un terreno',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TerrenoEntrada')),
        tags: ['Terrenos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Terreno actualizado.', content: new OA\JsonContent(required: ['terreno'], properties: [new OA\Property(property: 'terreno', ref: '#/components/schemas/Terreno')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Datos inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(CrearTerrenoRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $terreno = $this->terrenos->actualizar($this->usuarioId(), $id, $request->validated());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['terreno' => new TerrenoResource($terreno)]);
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
