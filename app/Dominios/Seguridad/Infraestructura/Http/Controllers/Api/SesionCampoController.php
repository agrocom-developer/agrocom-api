<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Aplicacion\RevocarTokenDispositivo;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioApp;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\EmitirTokenDispositivoRequest;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\DispositivoResource;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\RolActivoResource;
use App\Dominios\Seguridad\Infraestructura\Http\Resources\UsuarioCampoResource;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
 * contra el provider `usuarios_app` SIN iniciar sesión: `/api/*` no
 * tiene sesión ni cookies (ADR 0008), solo el bearer token que se emite acá.
 */
#[OA\SecurityScheme(
    securityScheme: 'tokenDispositivo',
    type: 'http',
    description: 'Token por dispositivo emitido por `POST /api/auth/token` (HU-03). Se envía como '
        .'`Authorization: Bearer {token}`. Caduca a los `SANCTUM_EXPIRATION` minutos de emitido '
        .'(30 días por defecto), o antes si se lo revoca desde el panel o si la cuenta o el rol con '
        .'el que se emitió dejan de estar vigentes.',
    scheme: 'bearer',
)]
#[OA\Tag(
    name: 'Seguridad',
    description: 'Sesión de la app de campo por token de dispositivo (especificación §13).',
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
            .'volver a loguearse. El token caduca a los 30 días por defecto (configurable) y '
            .'deja de valer antes si se lo revoca desde el panel. Limita los intentos por usuario e '
            .'IP: superado el límite responde 429. Reintentar desde el mismo '
            .'`uuid_dispositivo` revoca el token anterior y emite uno nuevo — nunca acumula '
            .'credenciales vivas. El valor en claro se devuelve UNA sola vez.',
        summary: 'Emite el token de un dispositivo de campo',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'uuid_dispositivo'],
                properties: [
                    new OA\Property(property: 'username', description: 'Usuario de la cuenta (nunca un correo) o su teléfono, en cualquier formato (`70012345`, `+591 700-12345`). El teléfono es único por tipo de cuenta: sin `app`, un teléfono que sirve a dos cuentas (cliente y proveedor) no entra.', type: 'string', example: 'camila.rojas'),
                    new OA\Property(property: 'password', description: 'Mínimo 4 caracteres, sin otras reglas de composición.', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'uuid_dispositivo', description: 'UUID generado en el dispositivo.', type: 'string', format: 'uuid', example: '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31'),
                    new OA\Property(property: 'nombre_dispositivo', description: 'Nombre legible del equipo, para que el panel sepa cuál revoca.', type: 'string', example: 'Moto G84 — piloto 2', nullable: true),
                    new OA\Property(property: 'role_id', description: 'Rol con el que se quiere operar. Opcional si el usuario tiene un único rol vivo.', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'app', description: 'App desde la que se ingresa (ADR 0046): `cliente` o `proveedor`; `piloto` se acepta como alias de `proveedor` (es el nombre del flavor). Si no coincide con el tipo de la cuenta, responde `403 tipo_de_cuenta_incorrecto` y no emite token. TRANSITORIO: si falta, el tipo se deduce de la cuenta.', type: 'string', enum: ['cliente', 'proveedor', 'piloto'], example: 'proveedor', nullable: true),
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
                description: 'Dos casos. Con `codigo: tipo_de_cuenta_incorrecto`: la cuenta es del otro tipo '
                    .'que la app (ADR 0046: «Esta cuenta es de proveedor: ingresa en la app Agrocom Piloto»); '
                    .'no se emite token. Sin `codigo`: el `role_id` pedido no está entre los roles vivos del usuario.',
                content: new OA\JsonContent(
                    required: ['message'],
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'codigo', description: 'Presente solo en `tipo_de_cuenta_incorrecto`.', type: 'string', example: 'tipo_de_cuenta_incorrecto', nullable: true),
                        new OA\Property(property: 'tipo_cuenta', description: 'Tipo de la cuenta que ingresó, para decirle a qué app ir.', type: 'string', enum: ['cliente', 'proveedor'], example: 'proveedor', nullable: true),
                    ],
                    type: 'object',
                ),
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
            new OA\Response(response: 429, description: 'Demasiados intentos (límite por minuto) o bloqueo temporal por fallos (10 fallos en 15 minutos por usuario o teléfono, 30 por IP; el mismo mensaje exista o no la cuenta); reintentar tras `Retry-After` segundos.'),
        ],
    )]
    public function store(EmitirTokenDispositivoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // La app declara su tipo (ADR 0046). TRANSITORIO: mientras la app no envíe `app`, el
        // tipo se deduce de la propia cuenta y el login no falla.
        $tipoDeApp = isset($datos['app']) ? TipoCuenta::dePedidoDeApp((string) $datos['app']) : null;

        $usuario = $this->autenticar((string) $datos['username'], (string) $datos['password'], $tipoDeApp, (string) $request->ip());

        if ($tipoDeApp !== null && $usuario->tipo_cuenta !== $tipoDeApp) {
            return response()->json([
                'message' => __('seguridad.respuestas.tipo_de_cuenta_incorrecto', [
                    'tipo' => __('seguridad.tipos_de_cuenta.'.$usuario->tipo_cuenta->value),
                    'app' => $usuario->tipo_cuenta->nombreDeApp(),
                ]),
                'codigo' => 'tipo_de_cuenta_incorrecto',
                'tipo_cuenta' => $usuario->tipo_cuenta->value,
            ], 403);
        }

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

    #[OA\Post(
        path: '/api/auth/renovar',
        operationId: 'renovarTokenDispositivo',
        description: 'Emite un token nuevo, con la vigencia completa, para el MISMO dispositivo y revoca el token con el que se hizo el '
            .'request. La app lo usa para que la sesión por biometría no pida la contraseña cada 30 días: tras el desbloqueo local '
            .'(la biometría nunca llega al servidor) reutiliza el token guardado y, cuando le quedan pocos días (`dispositivo.expira_en`), '
            .'lo renueva. Un token ya caducado o revocado no se renueva (`401`): la persona vuelve a entrar con su contraseña o con el código '
            .'de recuperación. La cuenta debe seguir habilitada. El valor en claro se devuelve UNA sola vez.',
        summary: 'Renueva el token del dispositivo actual',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguridad'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token nuevo; el anterior dejó de valer.',
                content: new OA\JsonContent(
                    required: ['token', 'token_type', 'usuario', 'rol', 'dispositivo'],
                    properties: [
                        new OA\Property(property: 'token', description: 'Credencial completa, para el header `Authorization: Bearer`.', type: 'string', example: '13|aB3cD4...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'usuario', ref: '#/components/schemas/UsuarioCampo'),
                        new OA\Property(property: 'rol', ref: '#/components/schemas/RolActivo'),
                        new OA\Property(property: 'dispositivo', ref: '#/components/schemas/Dispositivo'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, caducado o revocado, o la cuenta ya no puede entrar.'),
        ],
    )]
    public function renovar(Request $request): JsonResponse
    {
        $usuario = $this->usuarioAutenticado($request);
        $actual = $this->tokenActual($usuario);

        $resultado = $this->emitirToken->ejecutar(
            usuario: $usuario,
            uuidDispositivo: $actual->uuid_dispositivo,
            nombreDispositivo: $actual->nombre_dispositivo,
            idRolDeseado: $actual->role_id,
        );

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
     * Verifica `username` + password contra el provider `usuarios_app` (solo
     * cuentas `cliente` y `proveedor`) sin abrir sesión: en `/api/*` no hay
     * cookies ni estado (ADR 0008). Una cuenta de panel no existe para este
     * provider: recibe el mismo rechazo que una credencial inválida (ADR 0046).
     *
     * Una cuenta deshabilitada (`state = false`) se rechaza acá con el MISMO
     * mensaje que una credencial incorrecta: emitirle un token sería emitir
     * una credencial muerta (la revalidación por request la rechazaría
     * igual), y distinguir los dos casos le confirmaría a un atacante que ese
     * usuario existe.
     */
    private function autenticar(string $identificador, string $password, ?TipoCuenta $tipoDeApp, string $ip): SecUser
    {
        $this->exigirSinBloqueo($identificador, $ip);

        $proveedor = Auth::createUserProvider('usuarios_app');
        $usuario = $proveedor?->retrieveByCredentials(['username' => $identificador]) ?? $this->cuentaPorTelefono($identificador, $tipoDeApp);

        if ($proveedor === null
            || ! $usuario instanceof SecUser
            || ! $proveedor->validateCredentials($usuario, ['password' => $password])
            || ! $usuario->state
        ) {
            $this->registrarFallo($identificador, $ip);

            throw ValidationException::withMessages([
                'username' => [__('seguridad.login.error_credenciales')],
            ]);
        }

        RateLimiter::clear($this->claveDeBloqueo($identificador));

        return $usuario;
    }

    /**
     * Bloqueo temporal por fallos (ADR 0046, punto 31): la contraseña pide solo 4 caracteres, así que los intentos fallidos
     * cuentan por identificador (usuario o teléfono normalizado, exista o no la cuenta) y por IP. Al pasar de 10 fallos en 15
     * minutos por identificador o de 30 por IP, el login responde `429` con `Retry-After` y el mismo mensaje para cualquier cuenta.
     */
    private function exigirSinBloqueo(string $identificador, string $ip): void
    {
        $claveIdentificador = $this->claveDeBloqueo($identificador);
        $claveIp = 'login-bloqueo|ip|'.$ip;

        $bloqueada = match (true) {
            RateLimiter::tooManyAttempts($claveIdentificador, (int) config('verificacion.login.fallos_por_identificador', 10)) => $claveIdentificador,
            RateLimiter::tooManyAttempts($claveIp, (int) config('verificacion.login.fallos_por_ip', 30)) => $claveIp,
            default => null,
        };

        if ($bloqueada !== null) {
            throw new ThrottleRequestsException(__('seguridad.codigos.login_bloqueado'), null, ['Retry-After' => (string) RateLimiter::availableIn($bloqueada)]);
        }
    }

    private function registrarFallo(string $identificador, string $ip): void
    {
        $segundos = (int) config('verificacion.login.ventana_minutos', 15) * 60;

        RateLimiter::hit($this->claveDeBloqueo($identificador), $segundos);
        RateLimiter::hit('login-bloqueo|ip|'.$ip, $segundos);
    }

    private function claveDeBloqueo(string $identificador): string
    {
        $crudo = trim($identificador);

        return 'login-bloqueo|id|'.sha1(NumeroDeTelefono::normalizar($crudo) ?? mb_strtolower($crudo));
    }

    /**
     * El login acepta el teléfono de la cuenta en lugar del usuario (ADR 0046, punto 17), en
     * cualquier formato (`70012345`, `+591 700-12345`). El teléfono es único POR TIPO de cuenta,
     * así que el mismo número puede estar en una cuenta de cliente y en una de proveedor: con
     * `app` se busca en ese tipo; sin ella, solo se acepta si identifica a una única cuenta, y
     * si no, se rechaza igual que una credencial inválida (nunca se adivina de cuál es).
     */
    private function cuentaPorTelefono(string $identificador, ?TipoCuenta $tipoDeApp): ?SecUser
    {
        $telefono = NumeroDeTelefono::normalizar($identificador);

        if ($telefono === null) {
            return null;
        }

        $cuentas = SecUsuarioApp::query()
            ->where('telefono', $telefono)
            ->when($tipoDeApp !== null, fn ($consulta) => $consulta->where('tipo_cuenta', $tipoDeApp?->value))
            ->limit(2)
            ->get();

        return $cuentas->count() === 1 ? $cuentas->first() : null;
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
