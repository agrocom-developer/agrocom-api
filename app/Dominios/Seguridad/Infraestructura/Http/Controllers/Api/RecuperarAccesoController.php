<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\RecuperarAcceso;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ConfirmarRecuperacionRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\SolicitarRecuperacionRequest;
use App\Dominios\Seguridad\Infraestructura\Http\RespuestasDeCodigo;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Recuperar el acceso desde la app (ADR 0046, punto 27). Adaptador delgado: la regla vive en {@see RecuperarAcceso}.
 * Sin autenticación y con el límite `codigos-publicos`; nada de lo que responde distingue una cuenta que existe de una que no.
 */
final class RecuperarAccesoController
{
    public function __construct(private readonly RecuperarAcceso $recuperar) {}

    #[OA\Post(
        path: '/api/auth/recuperar',
        operationId: 'solicitarRecuperacionDeAcceso',
        description: 'La persona que olvidó su usuario o su contraseña da su usuario, teléfono o correo, la app desde la que escribe y '
            .'el canal por el que quiere recibir un código de 6 dígitos: `whatsapp` o `sms` (al teléfono de la cuenta) o `correo` '
            .'(al correo de respaldo). Responde siempre `202` con el mismo mensaje, exista o no la cuenta, esté o no bloqueada. '
            .'Solo `503 canal_no_disponible` (el medio no está configurado en el sistema, independiente de la cuenta) lo distingue. '
            .'El código vence a los 10 minutos, se usa una vez y admite 5 intentos; hay un solo código vigente por cuenta y propósito.',
        summary: 'Pedir un código para recuperar el acceso',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['identificador', 'app', 'canal'],
                properties: [
                    new OA\Property(property: 'identificador', description: 'Usuario, teléfono (en cualquier formato) o correo de respaldo.', type: 'string', example: '70012345'),
                    new OA\Property(property: 'app', description: 'App desde la que escribe; el mismo teléfono puede estar en una cuenta de cada tipo. `piloto` es alias de `proveedor`.', type: 'string', enum: ['cliente', 'proveedor', 'piloto'], example: 'cliente'),
                    new OA\Property(property: 'canal', description: 'Por dónde recibe el código.', type: 'string', enum: ['whatsapp', 'sms', 'correo'], example: 'whatsapp'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Solicitud recibida (siempre igual).',
                content: new OA\JsonContent(required: ['message'], properties: [new OA\Property(property: 'message', type: 'string', example: 'Si existe una cuenta con esos datos, enviamos un código. Revisa el medio que elegiste.')], type: 'object'),
            ),
            new OA\Response(response: 422, description: 'Datos inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 429, description: 'Demasiadas solicitudes desde la misma IP; reintentar tras `Retry-After` segundos.'),
            new OA\Response(response: 503, description: '`canal_no_disponible`: ese medio no está configurado; la app ofrece otro.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
        ],
    )]
    public function solicitar(SolicitarRecuperacionRequest $request): JsonResponse
    {
        $datos = $request->validated();

        try {
            $this->recuperar->solicitar(
                (string) $datos['identificador'],
                $this->app((string) $datos['app']),
                CanalDeCodigo::from((string) $datos['canal']),
            );
        } catch (CanalNoDisponible $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return response()->json(['message' => __('seguridad.codigos.solicitud_recibida')], 202);
    }

    #[OA\Post(
        path: '/api/auth/recuperar/confirmar',
        operationId: 'confirmarRecuperacionDeAcceso',
        description: 'Con el código recibido fija una contraseña nueva (mínimo 4 caracteres, sin otras reglas) y devuelve el usuario de la '
            .'cuenta, para que la app se lo muestre. Confirmar el código prueba la posesión del canal (marca el teléfono o el correo como '
            .'verificado). Al cambiarla se cierran las sesiones y se revocan todos los tokens de dispositivo; la app inicia sesión con '
            .'`POST /api/auth/token`. Cualquier falla del código responde el mismo `422 codigo_invalido`.',
        summary: 'Fijar una contraseña nueva con el código de recuperación',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['identificador', 'app', 'codigo', 'password_nueva'],
                properties: [
                    new OA\Property(property: 'identificador', type: 'string', example: '70012345'),
                    new OA\Property(property: 'app', type: 'string', enum: ['cliente', 'proveedor', 'piloto'], example: 'cliente'),
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
                description: 'Contraseña cambiada.',
                content: new OA\JsonContent(
                    required: ['message', 'username'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tu contraseña se cambió. Ya puedes iniciar sesión con tu usuario.'),
                        new OA\Property(property: 'username', description: 'El usuario de la cuenta, para que la persona lo recuerde.', type: 'string', example: 'camila.rojas'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: '`codigo_invalido` (el mismo cuerpo para cuenta inexistente, código errado, vencido, usado o con los intentos agotados) o datos inválidos (`errors`, por ejemplo una contraseña de menos de 4 caracteres).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorDeCodigo')),
            new OA\Response(response: 429, description: 'Demasiados intentos desde la misma IP; reintentar tras `Retry-After` segundos.'),
        ],
    )]
    public function confirmar(ConfirmarRecuperacionRequest $request): JsonResponse
    {
        $datos = $request->validated();

        try {
            $usuario = $this->recuperar->confirmar(
                (string) $datos['identificador'],
                $this->app((string) $datos['app']),
                (string) $datos['codigo'],
                (string) $datos['password_nueva'],
            );
        } catch (CodigoInvalido $falla) {
            return RespuestasDeCodigo::de($falla);
        }

        return response()->json(['message' => __('seguridad.codigos.acceso_recuperado'), 'username' => $usuario->username]);
    }

    private function app(string $app): TipoCuenta
    {
        return TipoCuenta::dePedidoDeApp($app) ?? TipoCuenta::Cliente;
    }
}
