<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Controllers\Api;

use App\Dominios\Disputas\Aplicacion\AbrirDisputa;
use App\Dominios\Disputas\Aplicacion\AgregarMensajeDeDisputa;
use App\Dominios\Disputas\Aplicacion\ApelarResolucion;
use App\Dominios\Disputas\Aplicacion\ConsultarDisputas;
use App\Dominios\Disputas\Aplicacion\RetirarDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Http\Requests\AbrirDisputaRequest;
use App\Dominios\Disputas\Infraestructura\Http\Requests\ApelarDisputaRequest;
use App\Dominios\Disputas\Infraestructura\Http\Requests\MensajeDeDisputaRequest;
use App\Dominios\Disputas\Infraestructura\Http\Resources\DisputaResource;
use App\Dominios\Disputas\Infraestructura\Http\RespuestaDeDisputa;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Disputas de los servicios (especificación §10.1, política de disputas, ADR 0033).
 * Las dos apps (cliente y proveedor) comparten la superficie: el lado lo decide el
 * permiso del rol del token (`gestionar_cliente` o `gestionar_proveedor`). Requiere
 * red (ADR 0028). Adaptador delgado (ADR 0008): el alcance (solo lo suyo, una
 * disputa o un servicio ajeno es 404, invariante 5 de CLAUDE.md) y las reglas viven
 * en los casos de uso.
 */
#[OA\Tag(
    name: 'Disputas',
    description: 'El cliente (hasta 72 horas después de `ejecutada`) o el proveedor (cobro pendiente, acceso negado o calificación abusiva) abren un reclamo; soporte media y un administrador resuelve; ambas partes pueden apelar una vez en 7 días (ADR 0033). Los reembolsos se liquidan fuera de la app.',
)]
final class DisputasController
{
    private const PERMISO_CLIENTE = 'disputas.disputa.gestionar_cliente';

    private const PERMISO_PROVEEDOR = 'disputas.disputa.gestionar_proveedor';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AbrirDisputa $apertura,
        private readonly ConsultarDisputas $consultar,
        private readonly AgregarMensajeDeDisputa $conversacion,
        private readonly RetirarDisputa $retiro,
        private readonly ApelarResolucion $apelacion,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/disputas',
        operationId: 'abrirDisputa',
        description: 'Abre el reclamo de un servicio (uno por solicitud). El cliente puede hacerlo desde `ejecutada` hasta 72 horas después, por: `cobertura_incompleta`, `mala_aplicacion`, `dano_a_cultivos_o_terceros`, `producto_distinto`, `equipo_ausente`, `evidencia_faltante`, `cobro_indebido` o `trato_inadecuado`; la solicitud pasa a `en_disputa`. '
            .'El proveedor puede abrirlo por `cobro_pendiente`, `acceso_negado` o `calificacion_abusiva`. `monto_en_disputa` es opcional (por defecto, todo el monto del servicio) y no puede pasar de él. '
            .'La contraparte tiene 48 horas para responder (`responde_antes_de`).',
        summary: 'Abrir un reclamo',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['motivo', 'detalle'],
                properties: [
                    new OA\Property(property: 'motivo', type: 'string', example: 'cobertura_incompleta'),
                    new OA\Property(property: 'detalle', type: 'string', example: 'Quedaron franjas sin cubrir en el lado norte'),
                    new OA\Property(property: 'monto_en_disputa', type: 'string', nullable: true, example: '3000.00'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Reclamo abierto.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no abre reclamos.'),
            new OA\Response(response: 404, description: 'El servicio no existe o no es tuyo.'),
            new OA\Response(response: 409, description: 'El servicio ya tiene un reclamo.'),
            new OA\Response(response: 422, description: 'El motivo no corresponde a tu lado, el servicio no está en un estado disputable, el plazo venció o el monto es inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(AbrirDisputaRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $disputa = $this->apertura->ejecutar($this->usuarioId(), $parte, $id, (string) $request->validated('motivo'), (string) $request->validated('detalle'), $request->validated('monto_en_disputa'));
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa->load(['mensajes', 'resoluciones'])))->resolve($request)], 201);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/disputa',
        operationId: 'verDisputaDeLaSolicitud',
        description: 'El reclamo de un servicio tuyo, con su conversación y sus resoluciones. Responde 404 si la solicitud no tiene reclamo o no es tuya.',
        summary: 'El reclamo de una solicitud',
        security: [['tokenDispositivo' => []]],
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El reclamo.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve reclamos.'),
            new OA\Response(response: 404, description: 'No hay reclamo en esa solicitud o no es tuya.'),
        ],
    )]
    public function deLaSolicitud(Request $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $disputa = $this->consultar->deLaSolicitud($this->usuarioId(), $parte, $id);
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa))->resolve($request)]);
    }

    #[OA\Get(
        path: '/api/disputas',
        operationId: 'listarDisputas',
        description: 'Tus reclamos (los de tu cuenta o los de tu proveedor), de la más nueva a la más vieja. `estado` filtra por un estado exacto.',
        summary: 'Mis reclamos',
        security: [['tokenDispositivo' => []]],
        tags: ['Disputas'],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['abierta', 'en_revision', 'resuelta', 'retirada'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Página de reclamos.',
                content: new OA\JsonContent(
                    required: ['disputas', 'pagina', 'paginas', 'total'],
                    properties: [
                        new OA\Property(property: 'disputas', type: 'array', items: new OA\Items(ref: '#/components/schemas/Disputa')),
                        new OA\Property(property: 'pagina', type: 'integer', example: 1),
                        new OA\Property(property: 'paginas', type: 'integer', example: 1),
                        new OA\Property(property: 'total', type: 'integer', example: 2),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve reclamos.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $pagina = $this->consultar->propias($this->usuarioId(), $parte, $request->query('estado') === null ? null : (string) $request->query('estado'), max(1, (int) $request->query('page', 1)));
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json([
            'disputas' => DisputaResource::collection($pagina->getCollection())->resolve($request),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    #[OA\Get(
        path: '/api/disputas/{id}',
        operationId: 'verDisputa',
        description: 'Un reclamo tuyo con su conversación y sus resoluciones. Una de otra cuenta responde 404.',
        summary: 'Detalle de un reclamo',
        security: [['tokenDispositivo' => []]],
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El reclamo.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $disputa = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/disputas/{id}/mensajes',
        operationId: 'enviarMensajeDeDisputa',
        description: 'Agrega un mensaje a la conversación de un reclamo vivo (`abierta` o `en_revision`): tu respuesta a la contraparte o a soporte. Un reclamo resuelto se reabre apelando, no escribiendo.',
        summary: 'Escribir en un reclamo',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['texto'], properties: [new OA\Property(property: 'texto', type: 'string', example: 'Adjunto la hora del vuelo: terminó a las 15:40')], type: 'object'),
        ),
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Mensaje guardado; devuelve el reclamo con su conversación.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no escribe en reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
            new OA\Response(response: 409, description: 'El reclamo ya no admite mensajes.'),
            new OA\Response(response: 422, description: 'Falta el texto.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function mensaje(MensajeDeDisputaRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $this->conversacion->comoParte($this->usuarioId(), $parte, $id, (string) $request->validated('texto'));
            $disputa = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa))->resolve($request)], 201);
    }

    #[OA\Post(
        path: '/api/disputas/{id}/retirar',
        operationId: 'retirarDisputa',
        description: 'Quien abrió el reclamo lo retira mientras está vivo. Si había movido la solicitud a `en_disputa`, la solicitud se completa como si el reclamo no se hubiera hecho. Retirar dos veces devuelve el mismo reclamo.',
        summary: 'Retirar un reclamo',
        security: [['tokenDispositivo' => []]],
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reclamo retirado.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
            new OA\Response(response: 409, description: 'El reclamo ya está resuelto y no se retira.'),
            new OA\Response(response: 422, description: 'Solo quien abrió el reclamo puede retirarlo.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function retirar(Request $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $this->retiro->ejecutar($this->usuarioId(), $parte, $id);
            $disputa = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/disputas/{id}/apelar',
        operationId: 'apelarDisputa',
        description: 'Apela la resolución con pruebas nuevas: una sola vez y dentro de 7 días desde la resolución (`apelable_hasta`). El reclamo vuelve a revisión en su segunda ronda y un administrador lo resuelve de nuevo; la primera resolución rige hasta entonces.',
        summary: 'Apelar una resolución',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['pruebas'], properties: [new OA\Property(property: 'pruebas', type: 'string', example: 'El registro del dron muestra la franja norte sin cubrir')], type: 'object'),
        ),
        tags: ['Disputas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Apelación registrada.', content: new OA\JsonContent(required: ['disputa'], properties: [new OA\Property(property: 'disputa', ref: '#/components/schemas/Disputa')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
            new OA\Response(response: 409, description: 'El reclamo no está resuelto o ya se apeló.'),
            new OA\Response(response: 422, description: 'Faltan las pruebas o el plazo de 7 días venció.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function apelar(ApelarDisputaRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $this->apelacion->ejecutar($this->usuarioId(), $parte, $id, (string) $request->validated('pruebas'));
            $disputa = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (DisputaInvalida $excepcion) {
            return RespuestaDeDisputa::de($excepcion);
        }

        return response()->json(['disputa' => (new DisputaResource($disputa))->resolve($request)]);
    }

    /** El lado de la cuenta según el permiso del rol del token; sin ninguno, 403. */
    private function parte(Request $request): ParteDeDisputa
    {
        if ($this->identidad->tienePermiso($request, self::PERMISO_CLIENTE)) {
            return ParteDeDisputa::Cliente;
        }

        abort_unless($this->identidad->tienePermiso($request, self::PERMISO_PROVEEDOR), 403, __('aprobacion.errores.sin_permiso'));

        return ParteDeDisputa::Proveedor;
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
