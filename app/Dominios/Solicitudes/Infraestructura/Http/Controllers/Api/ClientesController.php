<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\RegistrarCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\RegistrarClienteRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Resources\ClienteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Registro público del cliente y su propio perfil (especificación §10.1).
 * Adaptador delgado (ADR 0008): la regla vive en {@see RegistrarCliente}.
 */
#[OA\Tag(
    name: 'Clientes',
    description: 'Registro y perfil del cliente del marketplace, que solicita el servicio y califica al proveedor (ADR 0027).',
)]
final class ClientesController
{
    public function __construct(
        private readonly RegistrarCliente $registrar,
        private readonly IdentidadOperarioToken $identidad,
    ) {}

    #[OA\Post(
        path: '/api/clientes/registro',
        operationId: 'registrarCliente',
        description: 'Alta pública de un cliente: crea la cuenta (rol `cliente`) y su ficha. El cliente no pasa por aprobación: '
            .'puede pedir servicios desde el primer momento. Una empresa debe enviar razón social y NIT. No devuelve token: '
            .'la persona ingresa luego por `POST /api/auth/token`. Limita los intentos por IP: superado el límite responde 429.',
        summary: 'Registro público de un cliente',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tipo', 'nombre', 'username', 'password', 'telefono'],
                properties: [
                    new OA\Property(property: 'tipo', type: 'string', enum: ['persona', 'empresa'], example: 'empresa'),
                    new OA\Property(property: 'nombre', description: 'Nombre de quien usa la cuenta.', type: 'string', example: 'Rosa Condori Vargas'),
                    new OA\Property(property: 'username', description: 'Login (nunca un correo): letras, números, punto, guion.', type: 'string', example: 'rosa.condori'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'password', description: 'Mínimo 4 caracteres, sin otras reglas.', type: 'string', format: 'password'),
                    new OA\Property(property: 'telefono', description: 'Teléfono de la cuenta: un celular de Bolivia de 8 dígitos o un número con su código de país. Se guarda en E.164 y es único entre las cuentas de cliente (el mismo teléfono puede ser además de una cuenta de proveedor).', type: 'string', example: '+591 76543210'),
                    new OA\Property(property: 'documento_tipo', description: 'Tipo del documento de la persona (por defecto `ci`). TRANSITORIO: el documento es opcional hasta que la app lo envíe; con él, `nombres` y `apellidos` son obligatorios.', type: 'string', enum: ['ci', 'pasaporte', 'licencia_conducir'], example: 'ci', nullable: true),
                    new OA\Property(property: 'documento_numero', description: 'Número del documento: la persona es única por tipo y número normalizado (mayúsculas, sin espacios ni puntos) y tiene a lo sumo una cuenta de cliente.', type: 'string', example: '7845123 SC', nullable: true),
                    new OA\Property(property: 'nombres', type: 'string', example: 'Rosa', nullable: true),
                    new OA\Property(property: 'apellidos', type: 'string', example: 'Condori Vargas', nullable: true),
                    new OA\Property(property: 'fecha_nacimiento', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'razon_social', description: 'Obligatoria para una empresa; si falta, se usa el nombre.', type: 'string', nullable: true),
                    new OA\Property(property: 'documento_fiscal', description: 'Obligatorio para una empresa (NIT).', type: 'string', nullable: true),
                    new OA\Property(property: 'contactos', description: 'Contactos de referencia. Una empresa que no los envía queda con quien registra la cuenta (`nombre` y `telefono`) como contacto principal.', type: 'array', items: new OA\Items(ref: '#/components/schemas/ContactoEntrada'), nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Clientes'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cliente creado y activo.',
                content: new OA\JsonContent(required: ['cliente'], properties: [new OA\Property(property: 'cliente', ref: '#/components/schemas/Cliente')], type: 'object'),
            ),
            new OA\Response(response: 409, description: 'Ese teléfono o esa persona (documento) ya tiene una cuenta de cliente: `codigo: cuenta_ya_registrada`. No crea nada ni dice cuál de los dos datos chocó; la persona inicia sesión o recupera su acceso.', content: new OA\JsonContent(ref: '#/components/schemas/CuentaYaRegistrada')),
            new OA\Response(response: 422, description: 'Datos inválidos o login, correo o documento ya registrados.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
            new OA\Response(response: 429, description: 'Demasiados registros desde la misma IP, o demasiados `409` seguidos para esa IP, ese teléfono o ese documento; reintentar tras `Retry-After` segundos.', headers: [new OA\Header(header: 'Retry-After', description: 'Segundos para reintentar, según la ventana real del límite (cupo por hora o día, intervalo entre códigos, bloqueo del login o frecuencia por IP).', schema: new OA\Schema(type: 'integer', minimum: 1, example: 30))]),
        ],
    )]
    public function store(RegistrarClienteRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();
            if ($datos['tipo'] === TipoCliente::Empresa->value && empty($datos['contactos'])) {
                $datos['contactos'] = [['nombre' => $datos['nombre'], 'telefono' => $datos['telefono']]];
            }

            $cliente = $this->registrar->ejecutar($datos)->load('contactos');
        } catch (CuentaYaRegistrada $excepcion) {
            return response()->json($excepcion->cuerpo(), 409);
        } catch (CuentaDuplicada|ClienteDuplicado|SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['cliente' => new ClienteResource($cliente)], 201);
    }

    #[OA\Get(
        path: '/api/clientes/mi',
        operationId: 'verMiCliente',
        description: 'La ficha del cliente de la cuenta del token. El alcance sale de la cuenta autenticada, nunca de un id del request.',
        summary: 'Mi perfil de cliente',
        security: [['tokenDispositivo' => []]],
        tags: ['Clientes'],
        responses: [
            new OA\Response(response: 200, description: 'Perfil del cliente.', content: new OA\JsonContent(required: ['cliente'], properties: [new OA\Property(property: 'cliente', ref: '#/components/schemas/Cliente')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La cuenta no tiene ficha de cliente.'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        abort_unless($this->identidad->tienePermiso($request, 'solicitudes.cliente.gestionar'), 403, __('aprobacion.errores.sin_permiso'));

        $cliente = Cliente::query()->with('contactos')->where('usuario_id', (int) Auth::guard('sanctum')->id())->first();

        abort_if($cliente === null, 404, __('solicitudes.errores.sin_cliente'));

        return response()->json(['cliente' => new ClienteResource($cliente)]);
    }
}
