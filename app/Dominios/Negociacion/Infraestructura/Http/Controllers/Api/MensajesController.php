<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\EnviarMensajeRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\MarcarMensajesLeidosRequest;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Chat sencillo entre el cliente y el proveedor adjudicado (`negociacion-contrato.md` §5).
 * Consulta incremental por cursor: la app sondea cada pocos segundos con el chat abierto
 * y recibe un aviso al llegar un mensaje. Requiere red.
 */
#[OA\Tag(name: 'Mensajes', description: 'Mensajes entre el cliente y el proveedor adjudicado, desde la adjudicación. Texto de hasta 1000 caracteres, sin adjuntos: las fotos van como evidencia.')]
#[OA\Schema(
    schema: 'Mensaje',
    title: 'Mensaje del chat',
    required: ['id', 'uuid_cliente', 'solicitud_id', 'autor_rol', 'propio', 'texto', 'creado_en'],
    properties: [
        new OA\Property(property: 'id', description: 'Crece con el orden de creación; es el cursor.', type: 'integer', example: 120),
        new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
        new OA\Property(property: 'solicitud_id', type: 'integer'),
        new OA\Property(property: 'autor_rol', type: 'string', enum: ['cliente', 'proveedor']),
        new OA\Property(property: 'propio', description: 'Verdadero si lo escribió la cuenta que consulta.', type: 'boolean'),
        new OA\Property(property: 'texto', type: 'string', example: 'Llego el lunes temprano.'),
        new OA\Property(property: 'leido_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'creado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class MensajesController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly Mensajeria $mensajeria,
    ) {}

    #[OA\Get(
        path: '/api/solicitudes/{id}/mensajes',
        operationId: 'listarMensajes',
        description: 'Los mensajes posteriores a `despues` (el `cursor` de la respuesta anterior), en orden de creación. `solo_lectura` es verdadero cuando el plazo de escritura cerró. Solo las dos partes del servicio; otra cuenta recibe 404.',
        summary: 'Leer los mensajes',
        security: [['tokenDispositivo' => []]],
        tags: ['Mensajes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'despues', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limite', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mensajes.', content: new OA\JsonContent(required: ['mensajes', 'cursor', 'solo_lectura'], properties: [
                new OA\Property(property: 'mensajes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Mensaje')),
                new OA\Property(property: 'cursor', type: 'integer', nullable: true),
                new OA\Property(property: 'solo_lectura', type: 'boolean'),
            ], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no usa el chat.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
        ],
    )]
    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mensaje.usar');

        try {
            $pagina = $this->mensajeria->listar($this->usuarioId(), $id, $request->query('despues') === null ? null : (int) $request->query('despues'), (int) $request->query('limite', 50));
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json([
            'mensajes' => array_map(fn (Mensaje $m): array => Presentacion::mensaje($m, $this->usuarioId()), $pagina['mensajes']),
            'cursor' => $pagina['cursor'],
            'solo_lectura' => $pagina['solo_lectura'],
        ]);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/mensajes',
        operationId: 'enviarMensaje',
        description: 'Escribe un mensaje a la otra parte. Idempotente por `uuid_cliente`: reintentar responde 200 con el mismo mensaje. Se avisa a la otra parte. Con el plazo cerrado responde 409 `chat_cerrado`.',
        summary: 'Enviar un mensaje',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['uuid_cliente', 'texto'], properties: [
            new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
            new OA\Property(property: 'texto', type: 'string', maxLength: 1000),
        ], type: 'object')),
        tags: ['Mensajes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Mensaje creado.', content: new OA\JsonContent(required: ['mensaje'], properties: [new OA\Property(property: 'mensaje', ref: '#/components/schemas/Mensaje')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: el mensaje ya existía.', content: new OA\JsonContent(required: ['mensaje'], properties: [new OA\Property(property: 'mensaje', ref: '#/components/schemas/Mensaje')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no usa el chat.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
            new OA\Response(response: 409, description: '`chat_cerrado`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Texto vacío o de más de 1000 caracteres.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(EnviarMensajeRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mensaje.usar');
        $datos = $request->validated();

        try {
            [$mensaje, $creado] = $this->mensajeria->enviar($this->usuarioId(), $id, $datos['uuid_cliente'], $datos['texto']);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['mensaje' => Presentacion::mensaje($mensaje, $this->usuarioId())], $creado ? 201 : 200);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/mensajes/leidos',
        operationId: 'marcarMensajesLeidos',
        description: 'Marca como leídos los mensajes de la otra parte hasta `hasta` (un cursor).',
        summary: 'Marcar mensajes como leídos',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['hasta'], properties: [new OA\Property(property: 'hasta', type: 'integer', example: 120)], type: 'object')),
        tags: ['Mensajes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Cuántos mensajes se marcaron.', content: new OA\JsonContent(required: ['marcados'], properties: [new OA\Property(property: 'marcados', type: 'integer')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no usa el chat.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
        ],
    )]
    public function leidos(MarcarMensajesLeidosRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mensaje.usar');
        $datos = $request->validated();

        try {
            return response()->json(['marcados' => $this->mensajeria->marcarLeidos($this->usuarioId(), $id, (int) $datos['hasta'])]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }
}
