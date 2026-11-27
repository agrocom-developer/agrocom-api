<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\EliminarMiCuenta;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\ContrasenaActualIncorrecta;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Dominio\Excepciones\EliminacionBloqueada;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\SolicitarCodigoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\SolicitarEliminacionDeCuentaRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\EliminacionResource;
use App\Dominios\Seguridad\Infraestructura\Http\RespuestasDeCodigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * «Eliminar mi cuenta» desde la app (ADR 0047). Adaptador delgado: la regla vive en {@see EliminarMiCuenta}.
 */
final class EliminacionDeCuentaController
{
    public function __construct(private readonly EliminarMiCuenta $eliminar) {}

    #[OA\Get(
        path: '/api/cuenta/eliminacion',
        operationId: 'verEliminacionDeMiCuenta',
        description: 'Estado de la eliminación de la cuenta del token y lo que la impediría hoy. Responde también con la cuenta ya en eliminación '
            .'(es una de las pocas rutas que esa sesión puede usar). `eliminacion` es `null` si no hay una solicitud vigente; `impedimentos` lista los motivos por los '
            .'que pedirla respondería `409 eliminacion_bloqueada`: `servicio_en_curso` (un servicio adjudicado o en ejecución), `reclamo_abierto` y `pilotos_vinculados`.',
        summary: 'Estado de la eliminación de mi cuenta',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado.',
                content: new OA\JsonContent(
                    required: ['eliminacion', 'impedimentos'],
                    properties: [
                        new OA\Property(property: 'eliminacion', ref: '#/components/schemas/Eliminacion', nullable: true),
                        new OA\Property(property: 'impedimentos', type: 'array', items: new OA\Items(type: 'string', enum: ['servicio_en_curso', 'reclamo_abierto', 'pilotos_vinculados']), example: []),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
        ],
    )]
    public function estado(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);
        $vigente = $this->eliminar->vigente($usuario);

        return response()->json([
            'eliminacion' => EliminacionResource::de($vigente),
            'impedimentos' => $vigente === null ? $this->eliminar->impedimentos($usuario) : [],
        ]);
    }

    #[OA\Post(
        path: '/api/cuenta/eliminacion/codigo',
        operationId: 'pedirCodigoParaEliminarMiCuenta',
        description: 'Para quien olvidó su contraseña: envía un código de 6 dígitos por el canal que la persona elige (`whatsapp`, `sms` o `correo`). '
            .'Con ese código, en lugar de la contraseña, se pide la eliminación. Mismos límites que la recuperación del acceso.',
        summary: 'Pedir un código para eliminar mi cuenta',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['canal'],
                properties: [new OA\Property(property: 'canal', type: 'string', enum: ['whatsapp', 'sms', 'correo'], example: 'whatsapp')],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Código enviado.',
                content: new OA\JsonContent(
                    required: ['message', 'destino'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Te enviamos un código. Revisa el medio que elegiste.'),
                        new OA\Property(property: 'destino', description: 'Adónde fue, enmascarado.', type: 'string', example: '+591 ••••• 45'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 422, description: '`sin_destino` (la cuenta no tiene teléfono o correo para ese canal) o canal inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
            new OA\Response(response: 429, description: '`demasiados_codigos` (la cuenta ya pidió el máximo de códigos: 3 por hora y 10 por día) o `reenvio_demasiado_pronto` (pidió otro por el mismo canal hace menos de 30 segundos). Trae `Retry-After` con los segundos que faltan para poder pedir otro.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo'), headers: [new OA\Header(header: 'Retry-After', description: 'Segundos para reintentar, según la ventana real del límite (cupo por hora o día, intervalo entre códigos, bloqueo del login o frecuencia por IP).', schema: new OA\Schema(type: 'integer', minimum: 1, example: 30))]),
            new OA\Response(response: 503, description: '`canal_no_disponible`: ese medio no está configurado; ofrecer otro.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function codigo(SolicitarCodigoRequest $request): JsonResponse
    {
        $canal = CanalDeCodigo::from((string) $request->validated('canal'));

        try {
            $destino = $this->eliminar->pedirCodigo($this->usuario($request), $canal);
        } catch (CanalNoDisponible|DemasiadosCodigos $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return $destino === null
            ? RespuestasDeCodigo::sinDestino($canal->esDeTelefono() ? 'teléfono' : 'correo')
            : response()->json(['message' => __('seguridad.codigos.codigo_enviado'), 'destino' => $destino], 202);
    }

    #[OA\Post(
        path: '/api/cuenta/eliminacion',
        operationId: 'eliminarMiCuenta',
        description: 'Pide eliminar la cuenta del token (ADR 0047). Se confirma con la `password` o con un `codigo` (basta uno). Si algo la impide responde '
            .'`409 eliminacion_bloqueada` con los `motivos`. Si no: cierra todas las sesiones y revoca los tokens de dispositivo (también el de este '
            .'request), cancela las solicitudes abiertas y retira las ofertas vigentes (no se restauran), y deja la cuenta `solicitada` durante 7 días '
            .'(`ejecutar_desde`): puede iniciar sesión, pero la sesión solo ve el estado, cancela y cierra sesión (lo demás responde `403 cuenta_en_eliminacion`). '
            .'Vencida la gracia, una tarea diaria anonimiza los datos personales. Es idempotente: si ya hay una vigente, la devuelve.',
        summary: 'Eliminar mi cuenta',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'password', description: 'La contraseña de la cuenta. Obligatoria si no se envía `codigo`.', type: 'string', format: 'password', nullable: true),
                    new OA\Property(property: 'codigo', description: 'El código de `POST /api/cuenta/eliminacion/codigo`. Obligatorio si no se envía `password`.', type: 'string', example: '482913', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Eliminación solicitada; el token usado deja de valer.',
                content: new OA\JsonContent(required: ['eliminacion'], properties: [new OA\Property(property: 'eliminacion', ref: '#/components/schemas/Eliminacion')], type: 'object'),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(
                response: 409,
                description: '`eliminacion_bloqueada`: algo la impide; la app explica cada motivo y su salida.',
                content: new OA\JsonContent(
                    required: ['message', 'codigo', 'motivos'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No puedes eliminar tu cuenta todavía. Resuelve lo pendiente y vuelve a intentarlo.'),
                        new OA\Property(property: 'codigo', type: 'string', enum: ['eliminacion_bloqueada'], example: 'eliminacion_bloqueada'),
                        new OA\Property(property: 'motivos', type: 'array', items: new OA\Items(type: 'string', enum: ['servicio_en_curso', 'reclamo_abierto', 'pilotos_vinculados']), example: ['servicio_en_curso']),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: '`contrasena_incorrecta`, `codigo_invalido` o datos inválidos (sin contraseña ni código).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
            new OA\Response(response: 429, description: 'Demasiados intentos; reintentar tras `Retry-After` segundos.', headers: [new OA\Header(header: 'Retry-After', description: 'Segundos para reintentar, según la ventana real del límite (cupo por hora o día, intervalo entre códigos, bloqueo del login o frecuencia por IP).', schema: new OA\Schema(type: 'integer', minimum: 1, example: 30))]),
        ],
    )]
    public function store(SolicitarEliminacionDeCuentaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        try {
            $eliminacion = $this->eliminar->solicitar(
                $this->usuario($request),
                isset($datos['password']) ? (string) $datos['password'] : null,
                isset($datos['codigo']) ? (string) $datos['codigo'] : null,
            );
        } catch (EliminacionBloqueada $falla) {
            return response()->json($falla->cuerpo(), 409);
        } catch (ContrasenaActualIncorrecta) {
            return response()->json(['message' => __('seguridad.eliminacion.contrasena_incorrecta'), 'codigo' => 'contrasena_incorrecta'], 422);
        } catch (CodigoInvalido $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return response()->json(['eliminacion' => EliminacionResource::de($eliminacion)], 202);
    }

    #[OA\Post(
        path: '/api/cuenta/eliminacion/cancelar',
        operationId: 'cancelarEliminacionDeMiCuenta',
        description: 'Dentro de los 7 días de gracia, la persona se arrepiente («Conservar mi cuenta»): la cuenta vuelve a operar. Lo que se canceló al pedir la '
            .'eliminación (solicitudes abiertas, ofertas vigentes) no se restaura. Disponible para la sesión de una cuenta en eliminación.',
        summary: 'Conservar mi cuenta (cancelar la eliminación)',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Eliminación cancelada.',
                content: new OA\JsonContent(required: ['eliminacion'], properties: [new OA\Property(property: 'eliminacion', ref: '#/components/schemas/Eliminacion')], type: 'object'),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 404, description: 'No hay una eliminación vigente que cancelar.'),
        ],
    )]
    public function cancelar(Request $request): JsonResponse
    {
        $eliminacion = $this->eliminar->cancelar($this->usuario($request));

        abort_if($eliminacion === null, 404, __('seguridad.eliminacion.sin_solicitud'));

        return response()->json(['eliminacion' => EliminacionResource::de($eliminacion)]);
    }

    private function usuario(Request $request): SecUser
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
