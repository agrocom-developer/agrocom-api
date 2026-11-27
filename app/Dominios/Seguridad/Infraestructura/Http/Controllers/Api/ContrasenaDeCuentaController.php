<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\CambiarContrasenaDeCuenta;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\CambiarContrasenaDeCuentaRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\SolicitarCodigoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\RespuestasDeCodigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Cambiar la contraseña con la sesión iniciada, con un código por el canal que la persona elige (ADR 0046, punto 27). */
final class ContrasenaDeCuentaController
{
    public function __construct(private readonly CambiarContrasenaDeCuenta $cambiar) {}

    #[OA\Post(
        path: '/api/cuenta/contrasena/codigo',
        operationId: 'pedirCodigoParaCambiarContrasena',
        description: 'La cuenta del token pide un código de 6 dígitos para cambiar su contraseña, por el canal que elige: `whatsapp` o `sms` '
            .'(al teléfono de la cuenta) o `correo` (al correo de respaldo). El código vence a los 10 minutos, se usa una vez y admite 5 '
            .'intentos; pedir otro anula el anterior. Cada cuenta puede pedir 3 códigos por hora y 10 por día (entre todos los propósitos).',
        summary: 'Pedir un código para cambiar mi contraseña',
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
            new OA\Response(response: 429, description: 'La cuenta ya pidió el máximo de códigos; reintentar tras `Retry-After` segundos.'),
            new OA\Response(response: 503, description: '`canal_no_disponible`: ese medio no está configurado; ofrecer otro.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function codigo(SolicitarCodigoRequest $request): JsonResponse
    {
        $canal = CanalDeCodigo::from((string) $request->validated('canal'));

        try {
            $destino = $this->cambiar->solicitarCodigo($this->usuario($request), $canal);
        } catch (CanalNoDisponible|DemasiadosCodigos $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return $destino === null
            ? RespuestasDeCodigo::sinDestino($canal->esDeTelefono() ? 'teléfono' : 'correo')
            : response()->json(['message' => __('seguridad.codigos.codigo_enviado'), 'destino' => $destino], 202);
    }

    #[OA\Put(
        path: '/api/cuenta/contrasena',
        operationId: 'cambiarMiContrasena',
        description: 'Cambia la contraseña de la cuenta del token con el código recibido (mínimo 4 caracteres, sin otras reglas). Revoca los '
            .'tokens de los demás dispositivos y conserva el actual. Confirmar el código prueba la posesión del canal.',
        summary: 'Cambiar mi contraseña con un código',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo', 'password_nueva'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string', example: '482913'),
                    new OA\Property(property: 'password_nueva', type: 'string', format: 'password', example: 'abcd'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contraseña cambiada; los demás dispositivos quedaron sin sesión.',
                content: new OA\JsonContent(required: ['message'], properties: [new OA\Property(property: 'message', type: 'string', example: 'Tu contraseña se cambió. Tus otros dispositivos deberán iniciar sesión de nuevo.')], type: 'object'),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 422, description: '`codigo_invalido` o datos inválidos (`errors`).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function update(CambiarContrasenaDeCuentaRequest $request): JsonResponse
    {
        $usuario = $this->usuario($request);

        /** @var SecTokenDispositivo $dispositivo */
        $dispositivo = $usuario->currentAccessToken();

        try {
            $this->cambiar->cambiar($usuario, $dispositivo, (string) $request->validated('codigo'), (string) $request->validated('password_nueva'));
        } catch (CodigoInvalido $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return response()->json(['message' => __('seguridad.codigos.contrasena_cambiada')]);
    }

    private function usuario(Request $request): SecUser
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
