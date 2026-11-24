<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Flota\Aplicacion\ActualizarDron;
use App\Dominios\Flota\Aplicacion\ReenviarDron;
use App\Dominios\Flota\Aplicacion\RegistrarDron;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Flota\Infraestructura\Http\Requests\GuardarDronRequest;
use App\Dominios\Flota\Infraestructura\Http\Resources\DronResource;
use App\Dominios\Proveedores\Contratos\CargaDocumentos;
use App\Dominios\Proveedores\Contratos\DocumentoInvalido;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\CargarDocumentoRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Drones del proveedor del token (ADR 0029). Cada operación parte del titular
 * autenticado: un dron de otro proveedor es 404 aunque se adivine el id
 * (invariante 5). Los documentos del dron se cargan acá porque la pertenencia
 * del dron es de Flota; el almacenamiento y la revisión son de `Proveedores`.
 */
final class DronesController
{
    private const PERMISO = 'proveedores.cuenta.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly RegistrarDron $registrar,
        private readonly ActualizarDron $actualizar,
        private readonly ReenviarDron $reenviar,
        private readonly CargaDocumentos $documentos,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/drones',
        operationId: 'listarDrones',
        description: 'Drones que el proveedor del que la cuenta es titular opera, con su estado de aprobación y sus ciclos de batería. '
            .'El piloto de un proveedor recibe, en solo lectura, los drones de sus equipos vigentes.',
        summary: 'Drones de mi proveedor',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 200, description: 'Drones.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Dron'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona ni ve drones.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $this->identidad->tienePermiso($request, self::PERMISO)) {
            return $this->deMisEquipos($request);
        }

        $proveedor = $this->proveedores->proveedorDelTitular($this->usuarioId($request));

        $drones = $proveedor === null
            ? collect()
            : Dron::query()->where('operador_proveedor_id', $proveedor->id)->orderBy('id')->get();

        return response()->json(['data' => DronResource::collection($drones)->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/proveedores/drones',
        operationId: 'registrarDron',
        description: 'El titular registra un dron suyo. Queda `pendiente` hasta que un administrador lo aprueba con sus '
            .'documentos (registro, habilitación y seguro). El número de serie es único. Capacidad y ancho de pasada van como string decimal.',
        summary: 'Registra un dron',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['marca', 'modelo', 'numero_serie', 'capacidad_tanque_l', 'ancho_pasada_m'],
                properties: [
                    new OA\Property(property: 'marca', type: 'string', example: 'DJI'),
                    new OA\Property(property: 'modelo', type: 'string', example: 'Agras T40'),
                    new OA\Property(property: 'numero_serie', type: 'string', example: '1WNDH2K00A0001'),
                    new OA\Property(property: 'anio', type: 'integer', nullable: true, example: 2024),
                    new OA\Property(property: 'capacidad_tanque_l', type: 'string', example: '40.00'),
                    new OA\Property(property: 'ancho_pasada_m', type: 'string', example: '11.00'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 201, description: 'Dron creado `pendiente`.', content: new OA\JsonContent(required: ['dron'], properties: [new OA\Property(property: 'dron', ref: '#/components/schemas/Dron')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 422, description: 'Datos inválidos, serie repetida o el proveedor no admite altas.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(GuardarDronRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $dron = $this->registrar->ejecutar($this->usuarioId($request), $request->validated());
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['dron' => (new DronResource($dron))->resolve($request)], 201);
    }

    #[OA\Put(
        path: '/api/proveedores/drones/{id}',
        operationId: 'actualizarDron',
        description: 'Corrige la ficha de un dron suyo mientras espera revisión o tras un rechazo. Un dron aprobado no se edita: '
            .'cambiar su serie o su capacidad invalidaría lo que el administrador revisó.',
        summary: 'Corrige un dron pendiente o rechazado',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['marca', 'modelo', 'numero_serie', 'capacidad_tanque_l', 'ancho_pasada_m'],
                properties: [
                    new OA\Property(property: 'marca', type: 'string'),
                    new OA\Property(property: 'modelo', type: 'string'),
                    new OA\Property(property: 'numero_serie', type: 'string'),
                    new OA\Property(property: 'anio', type: 'integer', nullable: true, example: 2024),
                    new OA\Property(property: 'capacidad_tanque_l', type: 'string', example: '40.00'),
                    new OA\Property(property: 'ancho_pasada_m', type: 'string', example: '11.00'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dron actualizado.', content: new OA\JsonContent(required: ['dron'], properties: [new OA\Property(property: 'dron', ref: '#/components/schemas/Dron')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El dron no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'Datos inválidos, serie repetida o el dron ya no se puede editar.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(GuardarDronRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $dron = $this->actualizar->ejecutar($this->usuarioId($request), $id, $request->validated());
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['dron' => (new DronResource($dron))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/proveedores/drones/{id}/reenviar',
        operationId: 'reenviarDron',
        description: 'Un dron rechazado, corregido, vuelve a la cola de revisión (`rechazado` → `pendiente`).',
        summary: 'Reenvía un dron rechazado a revisión',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Dron otra vez `pendiente`.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El dron no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'El dron no está rechazado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function reenviar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $this->reenviar->ejecutar($this->usuarioId($request), $id);
        } catch (OperacionDeFlotaInvalida|TransicionDeAprobacionInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(status: 204);
    }

    #[OA\Post(
        path: '/api/proveedores/drones/{id}/documentos',
        operationId: 'cargarDocumentoDron',
        description: 'Carga un documento del dron (registro, habilitación, seguro; PDF, JPG o PNG, hasta 8 MB). Queda '
            .'`en_revision`. Registro, habilitación y seguro exigen fecha de vencimiento. Va como `multipart/form-data`.',
        summary: 'Carga un documento de un dron',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['tipo', 'archivo'],
                    properties: [
                        new OA\Property(property: 'tipo', type: 'string', enum: ['registro_dron', 'habilitacion_dron', 'seguro_responsabilidad', 'otro']),
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'vence_en', type: 'string', format: 'date', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Documento cargado `en_revision`.', content: new OA\JsonContent(required: ['documento_id'], properties: [new OA\Property(property: 'documento_id', type: 'integer', example: 41)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El dron no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'Archivo o datos inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cargarDocumento(CargarDocumentoRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        $proveedor = $this->proveedores->proveedorDelTitular($this->usuarioId($request));

        abort_if(
            $proveedor === null || ! Dron::query()->whereKey($id)->where('dueno_proveedor_id', $proveedor->id)->exists(),
            404,
            __('flota.errores.dron_ajeno'),
        );

        $datos = $request->validated();

        try {
            $documentoId = $this->documentos->cargar('dron', $id, $datos['tipo'], $request->file('archivo'), $datos['vence_en'] ?? null, $this->usuarioId($request));
        } catch (DocumentoInvalido $excepcion) {
            return RechazoDeDominio::respuesta($excepcion, 'vence_en');
        }

        return response()->json(['documento_id' => $documentoId], 201);
    }

    private function usuarioId(Request $request): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }

    /** Solo lectura para el piloto: los drones de los equipos vigentes en los que la cuenta es piloto. */
    private function deMisEquipos(Request $request): JsonResponse
    {
        abort_unless($this->identidad->tienePermiso($request, 'proveedores.cuenta.ver'), 403, __('aprobacion.errores.sin_permiso'));

        $equipos = $this->equipos->equiposDeCuenta($this->usuarioId($request));
        $dronIds = $equipos === [] ? [] : Equipo::query()->whereIn('id', $equipos)->pluck('dron_id')->all();

        $drones = $dronIds === [] ? collect() : Dron::query()->whereIn('id', $dronIds)->orderBy('id')->get();

        return response()->json(['data' => DronResource::collection($drones)->resolve($request)]);
    }
}
