<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Controllers\Api;

use App\Dominios\Reclamos\Aplicacion\AbrirReclamo;
use App\Dominios\Reclamos\Aplicacion\AgregarMensajeDeReclamo;
use App\Dominios\Reclamos\Aplicacion\ApelarResolucion;
use App\Dominios\Reclamos\Aplicacion\ConsultarReclamos;
use App\Dominios\Reclamos\Aplicacion\RetirarReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Http\Requests\AbrirReclamoRequest;
use App\Dominios\Reclamos\Infraestructura\Http\Requests\ApelarReclamoRequest;
use App\Dominios\Reclamos\Infraestructura\Http\Requests\MensajeDeReclamoRequest;
use App\Dominios\Reclamos\Infraestructura\Http\Resources\ReclamoResource;
use App\Dominios\Reclamos\Infraestructura\Http\RespuestaDeReclamo;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Reclamos de los servicios (especificación §10.1, política de reclamos, ADR 0033).
 * Las dos apps (cliente y proveedor) comparten la superficie: el lado lo decide el
 * permiso del rol del token (`gestionar_cliente` o `gestionar_proveedor`). Requiere
 * red (ADR 0028). Adaptador delgado (ADR 0008): el alcance (solo lo suyo, una
 * reclamo o un servicio ajeno es 404, invariante 5 de CLAUDE.md) y las reglas viven
 * en los casos de uso.
 */
#[OA\Tag(
    name: 'Reclamos',
    description: 'El cliente (hasta 72 horas después de `ejecutada`) o el proveedor (cobro pendiente, acceso negado o calificación abusiva) abren un reclamo; soporte media y un administrador resuelve; ambas partes pueden apelar una vez en 7 días (ADR 0033). Los reembolsos se liquidan fuera de la app.',
)]
final class ReclamosController
{
    private const PERMISO_CLIENTE = 'reclamos.reclamo.gestionar_cliente';

    private const PERMISO_PROVEEDOR = 'reclamos.reclamo.gestionar_proveedor';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AbrirReclamo $apertura,
        private readonly ConsultarReclamos $consultar,
        private readonly AgregarMensajeDeReclamo $conversacion,
        private readonly RetirarReclamo $retiro,
        private readonly ApelarResolucion $apelacion,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/reclamos',
        operationId: 'abrirReclamo',
        description: 'Abre el reclamo de un servicio (uno por solicitud). El cliente puede hacerlo desde `ejecutada` hasta 72 horas después, por: `cobertura_incompleta`, `mala_aplicacion`, `dano_a_cultivos_o_terceros`, `producto_distinto`, `equipo_ausente`, `evidencia_faltante`, `cobro_indebido` o `trato_inadecuado`; la solicitud pasa a `en_reclamo`. '
            .'El proveedor puede abrirlo por `cobro_pendiente`, `acceso_negado` o `calificacion_abusiva`. `monto_en_reclamo` es opcional (por defecto, todo el monto del servicio) y no puede pasar de él. '
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
                    new OA\Property(property: 'monto_en_reclamo', type: 'string', nullable: true, example: '3000.00'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Reclamo abierto.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no abre reclamos.'),
            new OA\Response(response: 404, description: 'El servicio no existe o no es tuyo.'),
            new OA\Response(response: 409, description: 'El servicio ya tiene un reclamo.'),
            new OA\Response(response: 422, description: 'El motivo no corresponde a tu lado, el servicio no está en un estado declamable, el plazo venció o el monto es inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(AbrirReclamoRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $reclamo = $this->apertura->ejecutar($this->usuarioId(), $parte, $id, (string) $request->validated('motivo'), (string) $request->validated('detalle'), $request->validated('monto_en_reclamo'));
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo->load(['mensajes', 'resoluciones'])))->resolve($request)], 201);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/reclamo',
        operationId: 'verReclamoDeLaSolicitud',
        description: 'El reclamo de un servicio tuyo, con su conversación y sus resoluciones. Responde 404 si la solicitud no tiene reclamo o no es tuya.',
        summary: 'El reclamo de una solicitud',
        security: [['tokenDispositivo' => []]],
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El reclamo.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve reclamos.'),
            new OA\Response(response: 404, description: 'No hay reclamo en esa solicitud o no es tuya.'),
        ],
    )]
    public function deLaSolicitud(Request $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $reclamo = $this->consultar->deLaSolicitud($this->usuarioId(), $parte, $id);
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo))->resolve($request)]);
    }

    #[OA\Get(
        path: '/api/reclamos',
        operationId: 'listarReclamos',
        description: 'Tus reclamos (los de tu cuenta o los de tu proveedor), de la más nueva a la más vieja. `estado` filtra por un estado exacto.',
        summary: 'Mis reclamos',
        security: [['tokenDispositivo' => []]],
        tags: ['Reclamos'],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['abierta', 'en_revision', 'resuelta', 'retirada'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Página de reclamos.',
                content: new OA\JsonContent(
                    required: ['reclamos', 'pagina', 'paginas', 'total'],
                    properties: [
                        new OA\Property(property: 'reclamos', type: 'array', items: new OA\Items(ref: '#/components/schemas/Reclamo')),
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
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json([
            'reclamos' => ReclamoResource::collection($pagina->getCollection())->resolve($request),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    #[OA\Get(
        path: '/api/reclamos/{id}',
        operationId: 'verReclamo',
        description: 'Un reclamo tuyo con su conversación y sus resoluciones. Una de otra cuenta responde 404.',
        summary: 'Detalle de un reclamo',
        security: [['tokenDispositivo' => []]],
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El reclamo.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $reclamo = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/reclamos/{id}/mensajes',
        operationId: 'enviarMensajeDeReclamo',
        description: 'Agrega un mensaje a la conversación de un reclamo vivo (`abierta` o `en_revision`): tu respuesta a la contraparte o a soporte. Un reclamo resuelto se reabre apelando, no escribiendo.',
        summary: 'Escribir en un reclamo',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['texto'], properties: [new OA\Property(property: 'texto', type: 'string', example: 'Adjunto la hora del vuelo: terminó a las 15:40')], type: 'object'),
        ),
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Mensaje guardado; devuelve el reclamo con su conversación.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no escribe en reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
            new OA\Response(response: 409, description: 'El reclamo ya no admite mensajes.'),
            new OA\Response(response: 422, description: 'Falta el texto.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function mensaje(MensajeDeReclamoRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $this->conversacion->comoParte($this->usuarioId(), $parte, $id, (string) $request->validated('texto'));
            $reclamo = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo))->resolve($request)], 201);
    }

    #[OA\Post(
        path: '/api/reclamos/{id}/retirar',
        operationId: 'retirarReclamo',
        description: 'Quien abrió el reclamo lo retira mientras está vivo. Si había movido la solicitud a `en_reclamo`, la solicitud se completa como si el reclamo no se hubiera hecho. Retirar dos veces devuelve el mismo reclamo.',
        summary: 'Retirar un reclamo',
        security: [['tokenDispositivo' => []]],
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reclamo retirado.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
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
            $reclamo = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo))->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/reclamos/{id}/apelar',
        operationId: 'apelarReclamo',
        description: 'Apela la resolución con pruebas nuevas: una sola vez y dentro de 7 días desde la resolución (`apelable_hasta`). El reclamo vuelve a revisión en su segunda ronda y un administrador lo resuelve de nuevo; la primera resolución rige hasta entonces.',
        summary: 'Apelar una resolución',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['pruebas'], properties: [new OA\Property(property: 'pruebas', type: 'string', example: 'El registro del dron muestra la franja norte sin cubrir')], type: 'object'),
        ),
        tags: ['Reclamos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Apelación registrada.', content: new OA\JsonContent(required: ['reclamo'], properties: [new OA\Property(property: 'reclamo', ref: '#/components/schemas/Reclamo')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona reclamos.'),
            new OA\Response(response: 404, description: 'No existe o no es tuya.'),
            new OA\Response(response: 409, description: 'El reclamo no está resuelto o ya se apeló.'),
            new OA\Response(response: 422, description: 'Faltan las pruebas o el plazo de 7 días venció.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function apelar(ApelarReclamoRequest $request, int $id): JsonResponse
    {
        $parte = $this->parte($request);

        try {
            $this->apelacion->ejecutar($this->usuarioId(), $parte, $id, (string) $request->validated('pruebas'));
            $reclamo = $this->consultar->una($this->usuarioId(), $parte, $id);
        } catch (ReclamoInvalido $excepcion) {
            return RespuestaDeReclamo::de($excepcion);
        }

        return response()->json(['reclamo' => (new ReclamoResource($reclamo))->resolve($request)]);
    }

    /** El lado de la cuenta según el permiso del rol del token; sin ninguno, 403. */
    private function parte(Request $request): ParteDeReclamo
    {
        if ($this->identidad->tienePermiso($request, self::PERMISO_CLIENTE)) {
            return ParteDeReclamo::Cliente;
        }

        abort_unless($this->identidad->tienePermiso($request, self::PERMISO_PROVEEDOR), 403, __('aprobacion.errores.sin_permiso'));

        return ParteDeReclamo::Proveedor;
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
