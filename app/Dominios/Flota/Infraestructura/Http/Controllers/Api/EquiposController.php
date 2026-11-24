<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Flota\Aplicacion\ActualizarEquipo;
use App\Dominios\Flota\Aplicacion\CrearEquipo;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Flota\Infraestructura\Http\Requests\ActualizarEquipoRequest;
use App\Dominios\Flota\Infraestructura\Http\Requests\CrearEquipoRequest;
use App\Dominios\Flota\Infraestructura\Http\Resources\EquipoResource;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/** Equipos (piloto + dron + auxiliar) del proveedor del token; el alcance sale del titular autenticado. */
final class EquiposController
{
    private const PERMISO = 'proveedores.cuenta.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly LecturaProveedores $proveedores,
        private readonly CrearEquipo $crear,
        private readonly ActualizarEquipo $actualizar,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/equipos',
        operationId: 'listarEquipos',
        description: 'Equipos del proveedor del que la cuenta es titular, vigentes o no, del más reciente al más antiguo.',
        summary: 'Equipos de mi proveedor',
        security: [['tokenDispositivo' => []]],
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 200, description: 'Equipos.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Equipo'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request);

        $proveedor = $this->proveedores->proveedorDelTitular($this->usuarioId($request));

        $equipos = $proveedor === null
            ? collect()
            : Equipo::query()->where('proveedor_id', $proveedor->id)->orderByDesc('id')->get();

        return response()->json(['data' => EquipoResource::collection($equipos)->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/proveedores/equipos',
        operationId: 'crearEquipo',
        description: 'El titular arma un equipo con un piloto, un dron y, opcionalmente, un auxiliar de su proveedor. Un piloto '
            .'y un dron no pueden estar en dos equipos vigentes a la vez; el equipo no recibe ofertas hasta que todos sus '
            .'integrantes estén aprobados con documentos vigentes.',
        summary: 'Crea un equipo',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['piloto_id', 'dron_id', 'vigente_desde'],
                properties: [
                    new OA\Property(property: 'piloto_id', type: 'integer', example: 5),
                    new OA\Property(property: 'dron_id', type: 'integer', example: 9),
                    new OA\Property(property: 'auxiliar_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'vigente_desde', type: 'string', format: 'date', example: '2026-11-23'),
                    new OA\Property(property: 'vigente_hasta', type: 'string', format: 'date', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Flota'],
        responses: [
            new OA\Response(response: 201, description: 'Equipo creado.', content: new OA\JsonContent(required: ['equipo'], properties: [new OA\Property(property: 'equipo', ref: '#/components/schemas/Equipo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 422, description: 'Un integrante es ajeno, no está disponible o ya está en otro equipo vigente.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CrearEquipoRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        $datos = $request->validated();

        try {
            $equipo = $this->crear->ejecutar(
                $this->usuarioId($request),
                (int) $datos['piloto_id'],
                (int) $datos['dron_id'],
                isset($datos['auxiliar_id']) ? (int) $datos['auxiliar_id'] : null,
                $datos['vigente_desde'],
                $datos['vigente_hasta'] ?? null,
            );
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['equipo' => (new EquipoResource($equipo))->resolve($request)], 201);
    }

    #[OA\Put(
        path: '/api/proveedores/equipos/{id}',
        operationId: 'actualizarEquipo',
        description: 'Cambia el auxiliar del equipo o lo cierra poniéndole fin de vigencia. El piloto y el dron de un equipo '
            .'no se cambian (otro piloto u otro dron es otro equipo) y una vigencia solo se acorta, nunca se alarga.',
        summary: 'Cambia el auxiliar o cierra un equipo',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'auxiliar_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'vigente_hasta', type: 'string', format: 'date', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Flota'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Equipo actualizado.', content: new OA\JsonContent(required: ['equipo'], properties: [new OA\Property(property: 'equipo', ref: '#/components/schemas/Equipo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El equipo no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'Auxiliar ajeno o no disponible, o vigencia inválida.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(ActualizarEquipoRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $equipo = $this->actualizar->ejecutar($this->usuarioId($request), $id, $request->validated());
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['equipo' => (new EquipoResource($equipo))->resolve($request)]);
    }

    private function usuarioId(Request $request): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }
}
