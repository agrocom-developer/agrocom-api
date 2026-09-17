<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Aplicacion\RevocarTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\EmitirTokenDispositivoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\DispositivoResource;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\RolActivoResource;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\UsuarioCampoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Sesión de la app de campo (HU-03): emitir el token del dispositivo,
 * consultarlo y cerrarlo. Adaptador delgado (ADR 0008) — la regla de negocio
 * (con qué rol queda operando el dispositivo, qué pasa con el token anterior)
 * vive en {@see EmitirTokenDispositivo}, no acá.
 *
 * La verificación de `username` + password sí vive en el controlador, igual
 * que en el login del panel ({@see IniciarSesionPanel} declara explícitamente
 * que es plumbing de autenticación, no una regla de dominio). Se valida
 * contra el provider `usuarios_internos` SIN iniciar sesión: `/api/*` no
 * tiene sesión ni cookies (ADR 0008), solo el bearer token que se emite acá.
 */
#[OA\SecurityScheme(
    securityScheme: 'tokenDispositivo',
    type: 'http',
    description: 'Token por dispositivo emitido por `POST /api/auth/token` (HU-03). Se envía como '
        .'`Authorization: Bearer {token}`. No caduca por tiempo: vale hasta que se lo revoque desde '
        .'el panel, o hasta que la cuenta o el rol con el que se emitió dejen de estar vigentes.',
    scheme: 'bearer',
)]
#[OA\Tag(
    name: 'Seguridad',
    description: 'Sesión de la app de campo por token de dispositivo (espec §4.6, HU-03).',
)]
final class SesionCampoController
{
    public function __construct(
        private readonly EmitirTokenDispositivo $emitirToken,
        private readonly RevocarTokenDispositivo $revocarToken,
    ) {}

    #[OA\Post(
        path: '/api/auth/token',
        operationId: 'emitirTokenDispositivo',
        description: 'Autentica al operario y emite el token con el que ese dispositivo opera sin '
            .'volver a loguearse. El token no caduca por tiempo (sesión persistente offline): deja '
            .'de valer cuando se lo revoca desde el panel. Reintentar desde el mismo '
            .'`uuid_dispositivo` revoca el token anterior y emite uno nuevo — nunca acumula '
            .'credenciales vivas. El valor en claro se devuelve UNA sola vez.',
        summary: 'Emite el token de un dispositivo de campo',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'uuid_dispositivo'],
                properties: [
                    new OA\Property(property: 'username', description: 'Login del operario (nunca un correo).', type: 'string', example: 'camila.rojas'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'uuid_dispositivo', description: 'UUID generado en el dispositivo.', type: 'string', format: 'uuid', example: '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31'),
                    new OA\Property(property: 'nombre_dispositivo', description: 'Nombre legible del equipo, para que el panel sepa cuál revoca.', type: 'string', example: 'Moto G84 — piloto 2', nullable: true),
                    new OA\Property(property: 'role_id', description: 'Rol con el que se quiere operar. Opcional si el usuario tiene un único rol vivo.', type: 'integer', example: 1, nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token emitido. `token` es el único momento en que la credencial existe en claro.',
                content: new OA\JsonContent(
                    required: ['token', 'token_type', 'usuario', 'rol', 'dispositivo'],
                    properties: [
                        new OA\Property(property: 'token', description: 'Credencial completa, para el header `Authorization: Bearer`.', type: 'string', example: '12|aB3cD4...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'usuario', ref: '#/components/schemas/UsuarioCampo'),
                        new OA\Property(property: 'rol', ref: '#/components/schemas/RolActivo'),
                        new OA\Property(property: 'dispositivo', ref: '#/components/schemas/Dispositivo'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'El `role_id` pedido no está entre los roles vivos del usuario.',
            ),
            new OA\Response(
                response: 409,
                description: 'El usuario tiene más de un rol vivo (o ninguno) y no indicó `role_id`: '
                    .'hay que elegir con cuál entra. Mismo contrato que el 409 del panel.',
                content: new OA\JsonContent(
                    required: ['message', 'roles'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(ref: '#/components/schemas/RolActivo')),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Credenciales incorrectas, cuenta deshabilitada, o payload inválido.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion'),
            ),
        ],
    )]
    public function store(EmitirTokenDispositivoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $usuario = $this->autenticar((string) $datos['username'], (string) $datos['password']);

        $resultado = $this->emitirToken->ejecutar(
            usuario: $usuario,
            uuidDispositivo: (string) $datos['uuid_dispositivo'],
            nombreDispositivo: isset($datos['nombre_dispositivo']) ? (string) $datos['nombre_dispositivo'] : null,
            idRolDeseado: isset($datos['role_id']) ? (int) $datos['role_id'] : null,
        );

        if ($resultado->requiereSeleccionDeRol) {
            return response()->json([
                'message' => __('seguridad.respuestas.rol_dispositivo_requerido'),
                'roles' => RolActivoResource::collection($resultado->rolesDisponibles),
            ], 409);
        }

        /** @var SecTokenDispositivo $token */
        $token = $resultado->token;

        return response()->json([
            'token' => $resultado->tokenPlano,
            'token_type' => 'Bearer',
            'usuario' => new UsuarioCampoResource($usuario),
            'rol' => new RolActivoResource($token->rol),
            'dispositivo' => new DispositivoResource($token),
        ], 201);
    }

    #[OA\Get(
        path: '/api/auth/sesion',
        operationId: 'verSesionCampo',
        description: 'Quién es el operario, con qué rol opera y desde qué dispositivo. La app la '
            .'consulta al reconectarse para saber si su token sigue vigente: un token revocado, una '
            .'cuenta deshabilitada o un rol que le sacaron devuelven 401 acá.',
        summary: 'Datos de la sesión del dispositivo',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión vigente.',
                content: new OA\JsonContent(
                    required: ['usuario', 'rol', 'dispositivo'],
                    properties: [
                        new OA\Property(property: 'usuario', ref: '#/components/schemas/UsuarioCampo'),
                        new OA\Property(property: 'rol', ref: '#/components/schemas/RolActivo'),
                        new OA\Property(property: 'dispositivo', ref: '#/components/schemas/Dispositivo'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $usuario = $this->usuarioAutenticado($request);
        $token = $this->tokenActual($usuario);

        return response()->json([
            'usuario' => new UsuarioCampoResource($usuario),
            'rol' => new RolActivoResource($token->rol),
            'dispositivo' => new DispositivoResource($token),
        ]);
    }

    #[OA\Delete(
        path: '/api/auth/token',
        operationId: 'cerrarSesionCampo',
        description: 'Cierra la sesión de ESTE dispositivo revocando el token con el que se hizo el '
            .'request. Un dispositivo nunca puede cerrar la sesión de otro: para eso está la '
            .'pantalla de revocación del panel.',
        summary: 'Cierra la sesión del dispositivo actual',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(response: 204, description: 'Sesión cerrada; el token deja de valer de inmediato.'),
            new OA\Response(response: 401, description: 'Token ausente o ya revocado.'),
        ],
    )]
    public function destroy(Request $request): JsonResponse
    {
        $usuario = $this->usuarioAutenticado($request);

        $this->revocarToken->ejecutar($this->tokenActual($usuario), $usuario);

        return response()->json(status: 204);
    }

    /**
     * Verifica `username` + password contra el provider `usuarios_internos`
     * sin abrir sesión: en `/api/*` no hay cookies ni estado (ADR 0008).
     *
     * Una cuenta deshabilitada (`state = false`) se rechaza acá con el MISMO
     * mensaje que una credencial incorrecta: emitirle un token sería emitir
     * una credencial muerta (la revalidación por request la rechazaría
     * igual), y distinguir los dos casos le confirmaría a un atacante que ese
     * usuario existe.
     */
    private function autenticar(string $username, string $password): SecUser
    {
        $proveedor = Auth::createUserProvider('usuarios_internos');
        $usuario = $proveedor?->retrieveByCredentials(['username' => $username]);

        if ($proveedor === null
            || ! $usuario instanceof SecUser
            || ! $proveedor->validateCredentials($usuario, ['password' => $password])
            || ! $usuario->state
        ) {
            throw ValidationException::withMessages([
                'username' => [__('seguridad.login.error_credenciales')],
            ]);
        }

        return $usuario;
    }

    private function usuarioAutenticado(Request $request): SecUser
    {
        /** @var SecUser $usuario */
        $usuario = $request->user();

        return $usuario;
    }

    /**
     * Token con el que se autenticó este request. Lo puebla el guard de
     * Sanctum vía `withAccessToken()`, así que dentro de una ruta
     * `auth:sanctum` nunca es nulo.
     */
    private function tokenActual(SecUser $usuario): SecTokenDispositivo
    {
        /** @var SecTokenDispositivo $token */
        $token = $usuario->currentAccessToken();

        return $token;
    }
}
