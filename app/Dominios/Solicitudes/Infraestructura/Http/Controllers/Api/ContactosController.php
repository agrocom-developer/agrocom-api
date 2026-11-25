<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\ClienteDeLaCuenta;
use App\Dominios\Solicitudes\Aplicacion\GestionarContactos;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\GuardarContactoRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Resources\ClienteContactoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Contactos de referencia del cliente de la cuenta (`/clientes/mi/contactos`).
 * Adaptador delgado (ADR 0008): las reglas y el alcance (solo lo propio, un id
 * ajeno es 404) viven en {@see GestionarContactos}.
 */
#[OA\Tag(
    name: 'Contactos del cliente',
    description: 'Personas de referencia del cliente (en una empresa, quienes coordinan el servicio con el proveedor). El proveedor recibe sus datos solo tras adjudicar.',
)]
#[OA\Schema(
    schema: 'ContactoEntrada',
    title: 'Contacto de referencia (datos que envía el cliente)',
    description: 'Cuerpo de `POST /api/clientes/mi/contactos` y de `PUT /api/clientes/mi/contactos/{id}`. El primer contacto del cliente queda como principal; marcar otro como principal releva al anterior.',
    required: ['nombre', 'telefono'],
    properties: [
        new OA\Property(property: 'nombre', type: 'string', example: 'Rosa Condori Vargas'),
        new OA\Property(property: 'telefono', type: 'string', example: '+591 76543210'),
        new OA\Property(property: 'cargo', type: 'string', nullable: true, example: 'Encargada de campo'),
        new OA\Property(property: 'es_principal', type: 'boolean', nullable: true),
    ],
    type: 'object',
)]
final class ContactosController
{
    private const PERMISO = 'solicitudes.cliente.gestionar';

    public function __construct(
        private readonly ClienteDeLaCuenta $clientes,
        private readonly GestionarContactos $contactos,
        private readonly IdentidadOperarioToken $identidad,
    ) {}

    #[OA\Get(
        path: '/api/clientes/mi/contactos',
        operationId: 'listarMisContactos',
        description: 'Los contactos de referencia vigentes del cliente de la cuenta, el principal primero.',
        summary: 'Mis contactos de referencia',
        security: [['tokenDispositivo' => []]],
        tags: ['Contactos del cliente'],
        responses: [
            new OA\Response(response: 200, description: 'Contactos vigentes.', content: new OA\JsonContent(required: ['contactos'], properties: [new OA\Property(property: 'contactos', type: 'array', items: new OA\Items(ref: '#/components/schemas/ClienteContacto'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La cuenta no tiene ficha de cliente.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $contactos = $this->contactos->listar($this->clientes->resolver($this->usuarioId()));
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['contactos' => ClienteContactoResource::collection($contactos)]);
    }

    #[OA\Post(
        path: '/api/clientes/mi/contactos',
        operationId: 'agregarContacto',
        description: 'Agrega un contacto de referencia. El primero queda como principal.',
        summary: 'Agregar un contacto de referencia',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContactoEntrada')),
        tags: ['Contactos del cliente'],
        responses: [
            new OA\Response(response: 201, description: 'Contacto creado.', content: new OA\JsonContent(required: ['contacto'], properties: [new OA\Property(property: 'contacto', ref: '#/components/schemas/ClienteContacto')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La cuenta no tiene ficha de cliente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o cliente suspendido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(GuardarContactoRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $contacto = $this->contactos->agregar($this->clientes->activo($this->usuarioId()), $request->validated());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['contacto' => new ClienteContactoResource($contacto)], 201);
    }

    #[OA\Put(
        path: '/api/clientes/mi/contactos/{id}',
        operationId: 'actualizarContacto',
        description: 'Corrige un contacto propio. Marcarlo como principal releva al anterior; desmarcar al principal no tiene efecto (se marca a otro).',
        summary: 'Actualizar un contacto de referencia',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContactoEntrada')),
        tags: ['Contactos del cliente'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Contacto actualizado.', content: new OA\JsonContent(required: ['contacto'], properties: [new OA\Property(property: 'contacto', ref: '#/components/schemas/ClienteContacto')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Datos inválidos o cliente suspendido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(GuardarContactoRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $contacto = $this->contactos->actualizar($this->clientes->activo($this->usuarioId()), $id, $request->validated());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['contacto' => new ClienteContactoResource($contacto)]);
    }

    #[OA\Delete(
        path: '/api/clientes/mi/contactos/{id}',
        operationId: 'darDeBajaContacto',
        description: 'Baja lógica de un contacto propio. Las solicitudes ya creadas conservan a la persona elegida. Una empresa no puede quedarse sin contactos: dar de baja al último responde 422. Si era el principal, lo releva el más antiguo que quede.',
        summary: 'Dar de baja un contacto de referencia',
        security: [['tokenDispositivo' => []]],
        tags: ['Contactos del cliente'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Contacto dado de baja.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Es el único contacto de una empresa.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $this->contactos->darDeBaja($this->clientes->activo($this->usuarioId()), $id);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(null, 204);
    }

    private function exigirPermiso(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
