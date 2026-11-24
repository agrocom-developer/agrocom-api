<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Asignacion\Aplicacion\ListarOfertasDelProveedor;
use App\Dominios\Asignacion\Aplicacion\ResponderOferta;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Http\Requests\AceptarOfertaRequest;
use App\Dominios\Asignacion\Infraestructura\Http\Requests\RechazarOfertaRequest;
use App\Dominios\Asignacion\Infraestructura\Http\Resources\OfertaResource;
use App\Dominios\Asignacion\Infraestructura\Http\RespuestaDeAsignacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Ofertas del proveedor (especificación §10.2, ADR 0030). Requiere red: aceptar
 * o rechazar es la única acción de negocio que no se encola (ADR 0028). Adaptador
 * delgado (ADR 0008): el alcance (solo las ofertas de su proveedor, una ajena es
 * 404, invariante 5 de CLAUDE.md) y las reglas viven en los casos de uso. El
 * titular de un proveedor y sus pilotos responden en nombre del proveedor.
 */
#[OA\Tag(
    name: 'Ofertas',
    description: 'El proveedor ve las ofertas que le hace el motor de asignación y las acepta o rechaza (ADR 0030). Requiere red.',
)]
final class OfertasController
{
    private const PERMISO_VER = 'asignacion.oferta.ver';

    private const PERMISO_RESPONDER = 'asignacion.oferta.responder';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly ListarOfertasDelProveedor $listar,
        private readonly ResponderOferta $responder,
    ) {}

    #[OA\Get(
        path: '/api/ofertas',
        operationId: 'listarOfertas',
        description: 'Las ofertas del proveedor de la cuenta. `vista=vivas` (por defecto) deja las que esperan respuesta, con su cuenta regresiva; `vista=historial`, las ya resueltas. '
            .'`desde` (ISO 8601) devuelve solo lo que cambió después: es la consulta periódica (polling) con que la app se entera de una oferta nueva, junto con `GET /api/notificaciones`. '
            .'El servicio muestra el terreno aproximado, las hectáreas, el pago estimado y la distancia; nunca datos del cliente.',
        summary: 'Mis ofertas',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [
            new OA\Parameter(name: 'vista', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['vivas', 'historial'])),
            new OA\Parameter(name: 'desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ofertas.', content: new OA\JsonContent(required: ['ofertas'], properties: [new OA\Property(property: 'ofertas', type: 'array', items: new OA\Items(ref: '#/components/schemas/Oferta'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no recibe ofertas.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_VER);

        $vista = $request->query('vista') === 'historial' ? 'historial' : 'vivas';
        $desde = $request->query('desde');

        try {
            $ofertas = $this->listar->ejecutar($this->usuarioId(), $vista, is_string($desde) && strtotime($desde) !== false ? $desde : null);
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['ofertas' => OfertaResource::collection($ofertas)->resolve($request)]);
    }

    #[OA\Get(
        path: '/api/ofertas/{id}',
        operationId: 'verOferta',
        description: 'Una oferta del proveedor de la cuenta. Una oferta de otro proveedor responde 404.',
        summary: 'Detalle de una oferta',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La oferta.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no recibe ofertas.'),
            new OA\Response(response: 404, description: 'No existe o es de otro proveedor.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_VER);

        try {
            $oferta = $this->listar->una($this->usuarioId(), $id);
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['oferta' => (new OfertaResource($oferta))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/aceptar',
        operationId: 'aceptarOferta',
        description: 'Acepta una oferta viva. Requiere red. Es atómico sobre la solicitud: si otro proveedor la aceptó antes, la oferta venció o se retiró, responde 409 y no cambia nada. '
            .'Con `equipo_id` asigna ese equipo; sin él, el primer equipo apto y libre en la ventana. Aceptar dos veces la misma oferta devuelve la misma oferta. '
            .'La solicitud queda `asignada` y se avisa al cliente.',
        summary: 'Aceptar una oferta',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'equipo_id', type: 'integer', nullable: true)], type: 'object'),
        ),
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Oferta aceptada.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no responde ofertas.'),
            new OA\Response(response: 404, description: 'No existe o es de otro proveedor.'),
            new OA\Response(response: 409, description: 'La oferta ya no está disponible: otro proveedor aceptó, venció o se retiró.'),
            new OA\Response(response: 422, description: 'No hay un equipo apto y libre, o el proveedor tiene una sanción vigente.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function aceptar(AceptarOfertaRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_RESPONDER);

        try {
            $this->responder->aceptar($this->usuarioId(), $id, $request->filled('equipo_id') ? (int) $request->input('equipo_id') : null);
            $oferta = $this->listar->una($this->usuarioId(), $id);
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['oferta' => (new OfertaResource($oferta))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/rechazar',
        operationId: 'rechazarOferta',
        description: 'Rechaza una oferta viva con motivo. Requiere red. Rechazar no penaliza hasta que la tasa de aceptación baje del umbral de la política de calificación. '
            .'Rechazar dos veces la misma oferta devuelve la misma oferta.',
        summary: 'Rechazar una oferta',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['motivo'], properties: [new OA\Property(property: 'motivo', type: 'string', example: 'No tengo equipo libre esa semana')], type: 'object'),
        ),
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Oferta rechazada.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no responde ofertas.'),
            new OA\Response(response: 404, description: 'No existe o es de otro proveedor.'),
            new OA\Response(response: 409, description: 'La oferta ya no está disponible.'),
            new OA\Response(response: 422, description: 'Falta el motivo.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function rechazar(RechazarOfertaRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_RESPONDER);

        try {
            $this->responder->rechazar($this->usuarioId(), $id, (string) $request->validated('motivo'));
            $oferta = $this->listar->una($this->usuarioId(), $id);
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['oferta' => (new OfertaResource($oferta))->resolve($request)]);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request, string $permiso): void
    {
        abort_unless($this->identidad->tienePermiso($request, $permiso), 403, __('aprobacion.errores.sin_permiso'));
    }
}
