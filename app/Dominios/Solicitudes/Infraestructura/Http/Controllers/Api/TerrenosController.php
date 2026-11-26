<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\GestionarFotosDeTerreno;
use App\Dominios\Solicitudes\Aplicacion\GestionarTerrenos;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CrearTerrenoRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\SubirFotoDeTerrenoRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Resources\TerrenoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        new OA\Property(property: 'departamento_codigo', description: 'Código INE del departamento del catálogo (`GET /api/catalogos/ubicaciones`). Si se envía `provincia_codigo`, es obligatorio.', type: 'string', nullable: true, example: '07'),
        new OA\Property(property: 'provincia_codigo', description: 'Código INE de la provincia; debe ser del departamento enviado.', type: 'string', nullable: true, example: '0702'),
        new OA\Property(property: 'municipio', description: 'Texto libre de referencia.', type: 'string', nullable: true),
        new OA\Property(property: 'comunidad', description: 'Texto libre de referencia.', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class TerrenosController
{
    private const PERMISO = 'solicitudes.terreno.gestionar';

    public function __construct(
        private readonly GestionarTerrenos $terrenos,
        private readonly GestionarFotosDeTerreno $fotos,
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

    #[OA\Post(
        path: '/api/terrenos/{id}/fotos',
        operationId: 'subirFotoDeTerreno',
        description: 'El cliente sube una foto del estado de su terreno (multipart, menos de 300 KB: la app la comprime antes). Hasta 8 por terreno. '
            .'El piloto la ve en `GET /api/solicitudes-abiertas/{id}` antes de ofertar. Se guarda en el disco privado.',
        summary: 'Subir una foto del terreno',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['archivo'], properties: [
            new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
            new OA\Property(property: 'descripcion', type: 'string', maxLength: 140, nullable: true),
        ], type: 'object'))),
        tags: ['Terrenos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Foto guardada.', content: new OA\JsonContent(required: ['foto'], properties: [new OA\Property(property: 'foto', ref: '#/components/schemas/FotoDeTerreno')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Archivo inválido, de 300 KB o más, o el terreno ya tiene el máximo de fotos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function subirFoto(SubirFotoDeTerrenoRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);
        $datos = $request->validated();

        try {
            $foto = $this->fotos->subir($this->usuarioId(), $id, $request->file('archivo'), $datos['descripcion'] ?? null);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['foto' => ['id' => $foto->id, 'descripcion' => $foto->descripcion, 'url' => GestionarFotosDeTerreno::url($foto->id)]], 201);
    }

    #[OA\Delete(
        path: '/api/terrenos/{id}/fotos/{foto}',
        operationId: 'borrarFotoDeTerreno',
        description: 'Borra una foto de un terreno propio.',
        summary: 'Borrar una foto del terreno',
        security: [['tokenDispositivo' => []]],
        tags: ['Terrenos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'foto', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Foto borrada.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La foto o el terreno no existen o son de otro cliente.'),
        ],
    )]
    public function borrarFoto(Request $request, int $id, int $foto): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $this->fotos->borrar($this->usuarioId(), $id, $foto);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/terrenos/fotos/{foto}/archivo',
        operationId: 'descargarFotoDeTerreno',
        description: 'El archivo de una foto de terreno. No lleva token: la URL es firmada y de corta vida (15 minutos), y solo la entregan el terreno del cliente dueño '
            .'y el detalle de una solicitud abierta al piloto habilitado. Una firma inválida o vencida responde 403.',
        summary: 'Descargar una foto del terreno',
        tags: ['Terrenos'],
        parameters: [
            new OA\Parameter(name: 'foto', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'La imagen.', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 403, description: 'Firma inválida o vencida.'),
            new OA\Response(response: 404, description: 'La foto ya no existe.'),
        ],
    )]
    public function archivoDeFoto(int $foto): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->fotos->ruta($foto);

        return $ruta === null
            ? response()->json(['message' => __('solicitudes.errores.foto_de_terreno_ajena')], 404)
            : response()->file($ruta, ['Cache-Control' => 'private, max-age=600']);
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
