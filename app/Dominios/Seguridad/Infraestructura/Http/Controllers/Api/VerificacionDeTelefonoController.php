<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\VerificarTelefonoDeCuenta;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ConfirmarCodigoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\SolicitarVerificacionDeTelefonoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\RespuestasDeCodigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Verificar el teléfono de la cuenta con un código por WhatsApp o SMS (ADR 0046, punto 29). */
final class VerificacionDeTelefonoController
{
    public function __construct(private readonly VerificarTelefonoDeCuenta $verificar) {}

    #[OA\Post(
        path: '/api/cuenta/telefono/verificacion',
        operationId: 'pedirCodigoParaVerificarTelefono',
        description: 'Envía un código de 6 dígitos al teléfono de la cuenta por `whatsapp` (WhatsApp Business, el canal principal) o `sms`. '
            .'Con el código, `POST /api/cuenta/telefono/verificacion/confirmar` marca el teléfono como verificado. La app lo pide mientras '
            .'`telefono_verificado_en` sea nulo. El código vence a los 10 minutos, se usa una vez y admite 5 intentos; 3 códigos por hora y '
            .'10 por día por cuenta.',
        summary: 'Pedir un código para verificar mi teléfono',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['canal'],
                properties: [new OA\Property(property: 'canal', type: 'string', enum: ['whatsapp', 'sms'], example: 'whatsapp')],
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
            new OA\Response(response: 409, description: '`telefono_ya_verificado`: la cuenta ya tiene su teléfono verificado.'),
            new OA\Response(response: 422, description: '`sin_destino` (la cuenta no tiene teléfono) o canal inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
            new OA\Response(response: 429, description: 'La cuenta ya pidió el máximo de códigos; reintentar tras `Retry-After` segundos.'),
            new OA\Response(response: 503, description: '`canal_no_disponible`: ese medio no está configurado; ofrecer el otro.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function solicitar(SolicitarVerificacionDeTelefonoRequest $request): JsonResponse
    {
        $usuario = $this->usuario($request);

        if ($usuario->telefono_verificado_en !== null) {
            return response()->json(['message' => __('seguridad.codigos.telefono_verificado'), 'codigo' => 'telefono_ya_verificado'], 409);
        }

        try {
            $destino = $this->verificar->solicitar($usuario, CanalDeCodigo::from((string) $request->validated('canal')));
        } catch (CanalNoDisponible|DemasiadosCodigos $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return $destino === null
            ? RespuestasDeCodigo::sinDestino('teléfono')
            : response()->json(['message' => __('seguridad.codigos.codigo_enviado'), 'destino' => $destino], 202);
    }

    #[OA\Post(
        path: '/api/cuenta/telefono/verificacion/confirmar',
        operationId: 'confirmarVerificacionDeTelefono',
        description: 'Confirma el código recibido y marca `telefono_verificado_en` de la cuenta. Un código errado gasta un intento; al quinto o al '
            .'vencer, hay que pedir otro.',
        summary: 'Confirmar el código de verificación del teléfono',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo'],
                properties: [new OA\Property(property: 'codigo', type: 'string', example: '482913')],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Teléfono verificado.',
                content: new OA\JsonContent(
                    required: ['message', 'telefono_verificado_en'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tu teléfono quedó verificado.'),
                        new OA\Property(property: 'telefono_verificado_en', type: 'string', format: 'date-time'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 422, description: '`codigo_invalido` (errado, vencido, usado o con los intentos agotados).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function confirmar(ConfirmarCodigoRequest $request): JsonResponse
    {
        try {
            $usuario = $this->verificar->confirmar($this->usuario($request), (string) $request->validated('codigo'));
        } catch (CodigoInvalido $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return response()->json([
            'message' => __('seguridad.codigos.telefono_verificado'),
            'telefono_verificado_en' => $usuario->telefono_verificado_en?->toIso8601String(),
        ]);
    }

    private function usuario(Request $request): SecUser
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
