<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\EnviarFotoDeMensajeRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\EnviarMensajeRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\MarcarMensajesLeidosRequest;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Chat sencillo entre el cliente y el proveedor adjudicado (`negociacion-contrato.md` §5).
 * Consulta incremental por cursor: la app sondea cada pocos segundos con el chat abierto
 * y recibe un aviso al llegar un mensaje. Requiere red.
 */
#[OA\Tag(name: 'Mensajes', description: 'Mensajes entre el cliente y el proveedor adjudicado, desde la adjudicación. Texto de hasta 1000 caracteres, fotos de menos de 300 KB y líneas de sistema que el servidor escribe al cambiar la solicitud.')]
#[OA\Schema(
    schema: 'Mensaje',
    title: 'Mensaje del chat',
    description: 'Tres tipos: `texto`; `foto` (`url` apunta al archivo privado, `texto` es el pie y puede ir vacío); `sistema` (`evento` dice qué pasó: `solicitud_adjudicada`, `servicio_iniciado`, `servicio_finalizado` o `precio_final_confirmado`; `autor_rol` es `sistema`, `propio` es falso y nunca se marca como leído).',
    required: ['id', 'uuid_cliente', 'solicitud_id', 'autor_rol', 'propio', 'tipo', 'texto', 'creado_en'],
    properties: [
        new OA\Property(property: 'id', description: 'Crece con el orden de creación; es el cursor.', type: 'integer', example: 120),
        new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
        new OA\Property(property: 'solicitud_id', type: 'integer'),
        new OA\Property(property: 'autor_rol', type: 'string', enum: ['cliente', 'proveedor', 'sistema']),
        new OA\Property(property: 'propio', description: 'Verdadero si lo escribió la cuenta que consulta.', type: 'boolean'),
        new OA\Property(property: 'tipo', type: 'string', enum: ['texto', 'foto', 'sistema']),
        new OA\Property(property: 'evento', description: 'Solo en los mensajes de sistema.', type: 'string', enum: ['solicitud_adjudicada', 'servicio_iniciado', 'servicio_finalizado', 'precio_final_confirmado'], nullable: true),
        new OA\Property(property: 'texto', description: 'El texto, el pie de la foto (puede ir vacío) o la línea del evento, ya en español.', type: 'string', example: 'Llego el lunes temprano.'),
        new OA\Property(property: 'url', description: 'Solo en las fotos: ruta autenticada del archivo original.', type: 'string', example: '/api/mensajes/121/archivo', nullable: true),
        new OA\Property(property: 'url_miniatura', description: 'Solo en las fotos: derivado chico para la lista del chat; pide `url` solo en el visor.', type: 'string', example: '/api/mensajes/121/archivo?variante=miniatura', nullable: true),
        new OA\Property(property: 'url_media', description: 'Solo en las fotos: derivado medio para la vista previa.', type: 'string', example: '/api/mensajes/121/archivo?variante=media', nullable: true),
        new OA\Property(property: 'bytes', description: 'Peso de la foto.', type: 'integer', nullable: true),
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
        description: 'Escribe un mensaje de texto a la otra parte (las fotos van por `POST /api/solicitudes/{id}/mensajes/fotos`). Idempotente por `uuid_cliente`: reintentar responde 200 con el mismo mensaje. Se avisa a la otra parte. Con el plazo cerrado responde 409 `chat_cerrado`.',
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
        path: '/api/solicitudes/{id}/mensajes/fotos',
        operationId: 'enviarFotoDeMensaje',
        description: 'Envía una foto a la otra parte (multipart): menos de 300 KB (la app la comprime antes; si no, 422 `evidencia_muy_grande`), jpg, png o webp, con un pie opcional de hasta 280 caracteres. Se guarda en el disco privado y se descarga con `GET /api/mensajes/{id}/archivo`. Idempotente por `uuid_cliente`: reintentar responde 200 con el mismo mensaje. Se avisa a la otra parte. Con el plazo cerrado responde 409 `chat_cerrado`.',
        summary: 'Enviar una foto',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['uuid_cliente', 'archivo'], properties: [
            new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
            new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
            new OA\Property(property: 'texto', type: 'string', maxLength: 280, nullable: true),
        ], type: 'object'))),
        tags: ['Mensajes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Foto enviada.', content: new OA\JsonContent(required: ['mensaje'], properties: [new OA\Property(property: 'mensaje', ref: '#/components/schemas/Mensaje')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: el mensaje ya existía.', content: new OA\JsonContent(required: ['mensaje'], properties: [new OA\Property(property: 'mensaje', ref: '#/components/schemas/Mensaje')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no usa el chat.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
            new OA\Response(response: 409, description: '`chat_cerrado`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Archivo inválido o de 300 KB o más, pie de más de 280 caracteres, o el chat ya tiene el máximo de fotos (`GET /api/medios/limites`).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function foto(EnviarFotoDeMensajeRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mensaje.usar');
        $datos = $request->validated();

        try {
            [$mensaje, $creado] = $this->mensajeria->enviarFoto($this->usuarioId(), $id, $datos['uuid_cliente'], $request->file('archivo'), $datos['texto'] ?? null);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['mensaje' => Presentacion::mensaje($mensaje, $this->usuarioId())], $creado ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/mensajes/{id}/archivo',
        operationId: 'descargarFotoDeMensaje',
        description: 'El archivo de una foto del chat, solo para las dos partes del servicio. Se sirve del disco privado, nunca por una URL pública.',
        summary: 'Descargar una foto del chat',
        security: [['tokenDispositivo' => []]],
        tags: ['Mensajes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variante', in: 'query', required: false, description: 'Tamaño: `miniatura` o `media` (derivados reducidos, para listas y vistas previas) u `original` (por omisión). Si el derivado aún no se generó, se entrega el original.', schema: new OA\Schema(type: 'string', enum: ['miniatura', 'media', 'original'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'La imagen.', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no usa el chat.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
        ],
    )]
    public function archivo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mensaje.usar');

        try {
            [, $ruta] = $this->mensajeria->archivoDeFoto($this->usuarioId(), $id, VarianteDeMedio::desde($request->query('variante')));
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=3600']);
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
