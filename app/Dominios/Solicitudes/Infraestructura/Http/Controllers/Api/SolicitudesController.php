<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\CambiarPrecioPropuesto;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\EstimarSolicitudDelCliente;
use App\Dominios\Solicitudes\Aplicacion\ListarSolicitudesDelCliente;
use App\Dominios\Solicitudes\Aplicacion\ObtenerSolicitudDelCliente;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CambiarPrecioRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CancelarSolicitudRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CrearSolicitudRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Resources\SolicitudServicioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Solicitudes de servicio del cliente (especificación §10.1). Requiere red: es
 * la superficie online del cliente, sin outbox (ADR 0028). Adaptador delgado
 * (ADR 0008): las reglas y el alcance (solo lo propio, un id ajeno es 404,
 * invariante 5 de CLAUDE.md) viven en los casos de uso.
 */
#[OA\Tag(
    name: 'Solicitudes',
    description: 'El cliente pide, consulta, publica y cancela servicios de fumigación (ADR 0027). Sin cobro en la app en la versión 1.',
)]
#[OA\Schema(
    schema: 'EstimacionSolicitud',
    title: 'Estimación de una solicitud',
    required: ['precio_acordado', 'hectareas'],
    properties: [
        new OA\Property(property: 'precio_ha', description: 'Precio por hectárea vigente: el acordado si ya se adjudicó; si no, el propuesto.', type: 'string', nullable: true, example: '125.0000'),
        new OA\Property(property: 'precio_acordado', description: 'Verdadero si la solicitud ya se adjudicó y el precio no cambia.', type: 'boolean'),
        new OA\Property(property: 'hectareas', type: 'string', example: '80.0000'),
        new OA\Property(property: 'monto_estimado', type: 'string', nullable: true, example: '10000.00'),
        new OA\Property(property: 'precio_referencia_ha', description: 'Guía de mercado para el tipo de aplicación; no es un límite.', type: 'string', nullable: true, example: '120.00'),
    ],
    type: 'object',
)]
final class SolicitudesController
{
    private const PERMISO = 'solicitudes.solicitud.gestionar';

    public function __construct(
        private readonly CrearSolicitud $crear,
        private readonly ListarSolicitudesDelCliente $listar,
        private readonly ObtenerSolicitudDelCliente $obtener,
        private readonly EstimarSolicitudDelCliente $estimar,
        private readonly PublicarSolicitud $publicar,
        private readonly CancelarSolicitud $cancelar,
        private readonly CambiarPrecioPropuesto $cambiarPrecio,
        private readonly IdentidadOperarioToken $identidad,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes',
        operationId: 'crearSolicitud',
        description: 'Crea una solicitud en `borrador`, idempotente por `uuid_cliente`: reintentar el mismo uuid responde 200 con la solicitud ya creada (nunca una segunda). '
            .'Las hectáreas no pueden superar las del terreno. `precio_ha_propuesto` (precio por hectárea que propone el cliente) puede venir acá o fijarse con `POST /api/solicitudes/{id}/precio` antes de publicar.',
        summary: 'Crear una solicitud de servicio',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuid_cliente', 'terreno_id', 'tipo_aplicacion', 'hectareas_solicitadas', 'producto_indicado', 'ventana_desde', 'ventana_hasta'],
                properties: [
                    new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'terreno_id', type: 'integer', example: 12),
                    new OA\Property(property: 'contacto_id', description: 'Contacto de referencia (uno de `GET /api/clientes/mi/contactos`). Opcional: una empresa que no elige usa su contacto principal.', type: 'integer', nullable: true, example: 7),
                    new OA\Property(property: 'tipo_aplicacion', type: 'string', enum: ['herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro']),
                    new OA\Property(property: 'hectareas_solicitadas', type: 'string', example: '80'),
                    new OA\Property(property: 'cultivo', type: 'string', nullable: true),
                    new OA\Property(property: 'producto_indicado', type: 'string', example: 'Glifosato 480 SL, 2 L/ha'),
                    new OA\Property(property: 'aporta_producto', type: 'string', enum: ['cliente', 'proveedor'], nullable: true),
                    new OA\Property(property: 'ventana_desde', description: 'Fecha deseada: inicio de la ventana.', type: 'string', format: 'date'),
                    new OA\Property(property: 'ventana_hasta', description: 'Fecha deseada: fin de la ventana.', type: 'string', format: 'date'),
                    new OA\Property(property: 'urgente', type: 'boolean', nullable: true),
                    new OA\Property(property: 'observaciones', type: 'string', nullable: true),
                    new OA\Property(property: 'precio_ha_propuesto', type: 'string', nullable: true, example: '125'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Solicitudes'],
        responses: [
            new OA\Response(response: 201, description: 'Solicitud creada en `borrador`.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: el `uuid_cliente` ya estaba creado; devuelve esa misma solicitud.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'El terreno no existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Datos inválidos, contacto que no es del cliente, empresa sin contactos, hectáreas mayores a las del terreno o cliente suspendido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CrearSolicitudRequest $request): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            [$solicitud, $creada] = $this->crear->ejecutar($this->usuarioId(), $request->validated());
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        $solicitud->load(['terreno', 'contacto', 'historial']);

        return response()->json(['solicitud' => new SolicitudServicioResource($solicitud)], $creada ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/solicitudes',
        operationId: 'listarSolicitudes',
        description: 'Las solicitudes del cliente de la cuenta, de la más nueva a la más vieja. `vista=activas` deja las que siguen vivas; '
            .'`vista=historial`, las que terminaron (`calificada`, `cancelada` o `expirada`). `estado` filtra por un estado exacto.',
        summary: 'Mis solicitudes e historial',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [
            new OA\Parameter(name: 'vista', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['activas', 'historial'])),
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Página de solicitudes.',
                content: new OA\JsonContent(
                    required: ['solicitudes', 'pagina', 'paginas', 'total'],
                    properties: [
                        new OA\Property(property: 'solicitudes', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolicitudServicio')),
                        new OA\Property(property: 'pagina', type: 'integer', example: 1),
                        new OA\Property(property: 'paginas', type: 'integer', example: 1),
                        new OA\Property(property: 'total', type: 'integer', example: 4),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'La cuenta no tiene ficha de cliente.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request);

        $vista = $request->query('vista');
        $vista = in_array($vista, [ListarSolicitudesDelCliente::VISTA_ACTIVAS, ListarSolicitudesDelCliente::VISTA_HISTORIAL], true) ? $vista : null;
        $estado = EstadoSolicitud::tryFrom((string) $request->query('estado'));

        try {
            $pagina = $this->listar->ejecutar($this->usuarioId(), $vista, $estado);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json([
            'solicitudes' => SolicitudServicioResource::collection($pagina->getCollection()),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}',
        operationId: 'verSolicitud',
        description: 'El detalle de una solicitud propia con su terreno y el historial de cada cambio de estado.',
        summary: 'Detalle de una solicitud',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La solicitud.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $solicitud = $this->obtener->ejecutar($this->usuarioId(), $id);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['solicitud' => new SolicitudServicioResource($solicitud)]);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/estimacion',
        operationId: 'estimarSolicitud',
        description: 'El monto estimado: hectáreas solicitadas por el precio por hectárea vigente (el propuesto, o el acordado una vez adjudicada), con el precio de referencia como guía.',
        summary: 'Monto estimado de una solicitud',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estimación. Importes como decimal exacto en string.',
                content: new OA\JsonContent(
                    required: ['estimacion'],
                    properties: [new OA\Property(property: 'estimacion', ref: '#/components/schemas/EstimacionSolicitud')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function estimacion(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $estimacion = $this->estimar->ejecutar($this->usuarioId(), $id);
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['estimacion' => $estimacion]);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/publicar',
        operationId: 'publicarSolicitud',
        description: 'Abre la solicitud a los pilotos: `borrador` pasa a `abierta`, con su vigencia (7 días) y visible en el mapa. Exige precio por hectárea propuesto, hectáreas que no superen las del terreno, fecha deseada no pasada y un teléfono en la cuenta. '
            .'Avisa a los pilotos cuyas preferencias coinciden con la zona y las hectáreas. Una solicitud `expirada` se renueva igual, dentro del plazo de renovación.',
        summary: 'Abrir una solicitud al mercado',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Solicitud abierta.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Falta el precio o el teléfono, la solicitud no está en un estado que se pueda abrir o la fecha deseada ya pasó.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function publicar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $solicitud = $this->publicar->ejecutar($this->usuarioId(), $id);
        } catch (SolicitudInvalida|TransicionSolicitudNoPermitida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['solicitud' => new SolicitudServicioResource($solicitud)]);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/cancelar',
        operationId: 'cancelarSolicitud',
        description: 'Cancela una solicitud propia con motivo. Es gratuita y sin penalidad en dinero; si ya estaba adjudicada, queda registrada en la tasa de cancelación del cliente y el proveedor queda libre. '
            .'Una solicitud en ejecución o finalizada no se cancela por este camino: si hay desacuerdo, se abre un reclamo.',
        summary: 'Cancelar una solicitud',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['motivo'], properties: [new OA\Property(property: 'motivo', type: 'string', example: 'Cambió la fecha de siembra')], type: 'object'),
        ),
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Solicitud cancelada.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Falta el motivo o la solicitud ya no admite cancelación.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cancelar(CancelarSolicitudRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $solicitud = $this->cancelar->porCliente($this->usuarioId(), $id, (string) $request->validated('motivo'));
        } catch (SolicitudInvalida|TransicionSolicitudNoPermitida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['solicitud' => new SolicitudServicioResource($solicitud)]);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/precio',
        operationId: 'cambiarPrecioSolicitud',
        description: 'Cambia el precio por hectárea que propone el cliente, mientras la solicitud está en `borrador`, `abierta` o `con_ofertas`. Las ofertas vigentes no cambian; se avisa a los proveedores con una negociación vigente.',
        summary: 'Cambiar el precio propuesto',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['precio_ha_propuesto'], properties: [new OA\Property(property: 'precio_ha_propuesto', type: 'string', example: '130')], type: 'object'),
        ),
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Solicitud con el precio nuevo.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'Precio inválido o la solicitud ya no admite el cambio.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function precio(CambiarPrecioRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $solicitud = $this->cambiarPrecio->ejecutar($this->usuarioId(), $id, (string) $request->validated('precio_ha_propuesto'));
        } catch (SolicitudInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['solicitud' => new SolicitudServicioResource($solicitud)]);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/renovar',
        operationId: 'renovarSolicitud',
        description: 'Vuelve a abrir una solicitud `expirada`, dentro del plazo de renovación (30 días). Nueva vigencia; las ofertas anteriores siguen cerradas.',
        summary: 'Renovar una solicitud expirada',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Solicitud abierta de nuevo.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudServicio')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 422, description: 'No está expirada o pasó el plazo de renovación.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function renovar(Request $request, int $id): JsonResponse
    {
        return $this->publicar($request, $id);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request): void
    {
        abort_unless($this->identidad->tienePermiso($request, self::PERMISO), 403, __('aprobacion.errores.sin_permiso'));
    }
}
