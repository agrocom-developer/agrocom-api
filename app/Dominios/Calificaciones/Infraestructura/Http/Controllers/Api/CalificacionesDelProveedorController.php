<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Calificaciones\Aplicacion\CalificarComoProveedor;
use App\Dominios\Calificaciones\Aplicacion\ConsultarCalificacionesDelServicio;
use App\Dominios\Calificaciones\Aplicacion\ListarCalificacionesRecibidas;
use App\Dominios\Calificaciones\Aplicacion\ParametrosDeCalificacion;
use App\Dominios\Calificaciones\Aplicacion\ReportarCalificacion;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Infraestructura\Http\Requests\CalificarClienteRequest;
use App\Dominios\Calificaciones\Infraestructura\Http\Requests\ReportarCalificacionRequest;
use App\Dominios\Calificaciones\Infraestructura\Http\Resources\CalificacionResource;
use App\Dominios\Calificaciones\Infraestructura\Http\RespuestaDeCalificacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Calificaciones del lado del proveedor (especificación §10.2, ADR 0031): califica al
 * cliente en privado, ve las calificaciones que recibe y reporta las abusivas.
 * Requiere red. El titular de un proveedor y sus pilotos hablan por el proveedor;
 * todo parte del proveedor de la cuenta y un registro ajeno responde 404
 * (invariante 5 de CLAUDE.md).
 */
final class CalificacionesDelProveedorController
{
    private const PERMISO_CALIFICAR = 'calificaciones.calificacion.calificar_cliente';

    private const PERMISO_VER = 'calificaciones.calificacion.ver_recibidas';

    private const PERMISO_REPORTAR = 'calificaciones.calificacion.reportar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly CalificarComoProveedor $calificar,
        private readonly ConsultarCalificacionesDelServicio $consultar,
        private readonly ListarCalificacionesRecibidas $recibidas,
        private readonly ReportarCalificacion $reportar,
        private readonly ParametrosDeCalificacion $parametros,
    ) {}

    #[OA\Get(
        path: '/api/solicitudes/{id}/calificar-cliente',
        operationId: 'verCalificacionDelCliente',
        description: 'Si puedes calificar al cliente de un servicio que prestó tu proveedor, hasta cuándo y la calificación que ya diste. Es privada: el cliente nunca la ve. '
            .'Solo un servicio `completada` se califica, durante 7 días desde que se completó.',
        summary: 'Estado de la calificación del cliente',
        security: [['tokenDispositivo' => []]],
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Estado de la calificación.', content: new OA\JsonContent(ref: '#/components/schemas/CalificacionesDelServicio')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no califica clientes.'),
            new OA\Response(response: 404, description: 'No existe o no lo prestó tu proveedor.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_CALIFICAR);

        try {
            $estado = $this->consultar->paraProveedor($this->usuarioId(), $id);
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        return response()->json(CalificacionesDelClienteController::cuerpo($estado, AutorCalificacion::Proveedor, $this->parametros->plazoDias(), $request));
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/calificar-cliente',
        operationId: 'calificarCliente',
        description: 'Califica en privado al cliente de un servicio completado que prestó tu proveedor: de 1 a 5 estrellas, etiquetas (puntualidad, comunicacion, acceso_lote, claridad_pedido) y un comentario opcional. '
            .'No se publica: sirve a moderación y a soporte y alimenta la reputación del cliente. Idempotente por `uuid_cliente`; se guarda una sola vez por servicio y no se edita.',
        summary: 'Calificar al cliente',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuid_cliente', 'estrellas'],
                properties: [
                    new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'estrellas', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'etiquetas', type: 'array', items: new OA\Items(type: 'string', enum: ['puntualidad', 'comunicacion', 'acceso_lote', 'claridad_pedido']), nullable: true),
                    new OA\Property(property: 'comentario', type: 'string', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Calificación guardada.', content: new OA\JsonContent(required: ['calificacion'], properties: [new OA\Property(property: 'calificacion', ref: '#/components/schemas/Calificacion')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: la calificación ya estaba guardada.', content: new OA\JsonContent(required: ['calificacion'], properties: [new OA\Property(property: 'calificacion', ref: '#/components/schemas/Calificacion')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no califica clientes.'),
            new OA\Response(response: 404, description: 'No existe o no lo prestó tu proveedor.'),
            new OA\Response(response: 409, description: 'Ya calificaste al cliente en este servicio.'),
            new OA\Response(response: 422, description: 'El servicio no está completado, el plazo venció, las etiquetas no corresponden o los datos son inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CalificarClienteRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_CALIFICAR);

        try {
            $resultado = $this->calificar->ejecutar($this->usuarioId(), $id, $request->validated());
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        return response()->json(['calificacion' => (new CalificacionResource($resultado['calificacion']))->resolve($request)], $resultado['creada'] ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/calificaciones/recibidas',
        operationId: 'listarCalificacionesRecibidas',
        description: 'Las calificaciones que recibe tu proveedor (él, su piloto y su auxiliar), de la más nueva a la más vieja, sin las que un administrador ocultó. '
            .'Incluye el resumen que muestra la app: promedio y conteo (los de tu reputación), distribución por estrellas y etiquetas más frecuentes de las últimas 100.',
        summary: 'Calificaciones que recibo',
        security: [['tokenDispositivo' => []]],
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Página de calificaciones.',
                content: new OA\JsonContent(
                    required: ['calificaciones', 'pagina', 'paginas', 'total', 'resumen'],
                    properties: [
                        new OA\Property(property: 'calificaciones', type: 'array', items: new OA\Items(ref: '#/components/schemas/Calificacion')),
                        new OA\Property(property: 'pagina', type: 'integer', example: 1),
                        new OA\Property(property: 'paginas', type: 'integer', example: 3),
                        new OA\Property(property: 'total', type: 'integer', example: 41),
                        new OA\Property(
                            property: 'resumen',
                            required: ['calificacion_promedio', 'calificaciones_conteo', 'por_estrellas', 'etiquetas'],
                            properties: [
                                new OA\Property(property: 'calificacion_promedio', type: 'string', example: '4.60'),
                                new OA\Property(property: 'calificaciones_conteo', type: 'integer', example: 24),
                                new OA\Property(property: 'por_estrellas', description: 'Cuántas calificaciones al proveedor tienen cada cantidad de estrellas (claves "1" a "5").', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer')),
                                new OA\Property(property: 'etiquetas', description: 'Cuántas veces aparece cada etiqueta, de la más frecuente a la menos.', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer')),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve calificaciones.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor.'),
        ],
    )]
    public function recibidas(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_VER);

        try {
            $datos = $this->recibidas->ejecutar($this->usuarioId(), max(1, (int) $request->query('page', 1)));
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        $pagina = $datos['pagina'];

        return response()->json([
            'calificaciones' => CalificacionResource::collection($pagina->getCollection())->resolve($request),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'total' => $pagina->total(),
            'resumen' => [
                'calificacion_promedio' => $datos['proveedor']->calificacionPromedio,
                'calificaciones_conteo' => $datos['proveedor']->calificacionesConteo,
                'por_estrellas' => (object) $datos['resumen']['por_estrellas'],
                'etiquetas' => (object) $datos['resumen']['etiquetas'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/calificaciones/{id}/reportar',
        operationId: 'reportarCalificacion',
        description: 'Reporta con motivo una calificación que recibiste y consideras abusiva (insultos, datos personales, factores ajenos como el clima, represalia tras una disputa). '
            .'Queda `en_moderacion` y un administrador decide; reportar no la oculta ni la saca del promedio. Reportar dos veces la misma devuelve la misma calificación.',
        summary: 'Reportar una calificación',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['motivo'], properties: [new OA\Property(property: 'motivo', type: 'string', example: 'El comentario incluye insultos')], type: 'object'),
        ),
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Calificación en moderación.', content: new OA\JsonContent(required: ['calificacion'], properties: [new OA\Property(property: 'calificacion', ref: '#/components/schemas/Calificacion')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no reporta calificaciones.'),
            new OA\Response(response: 404, description: 'No existe o no es de tu proveedor.'),
            new OA\Response(response: 422, description: 'Falta el motivo o la calificación ya está oculta.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function reportar(ReportarCalificacionRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, self::PERMISO_REPORTAR);

        try {
            $calificacion = $this->reportar->ejecutar($this->usuarioId(), $id, (string) $request->validated('motivo'));
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        return response()->json(['calificacion' => (new CalificacionResource($calificacion))->resolve($request)]);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request, string $permiso): void
    {
        abort_unless($this->identidad->tienePermiso($request, $permiso), 403, __('aprobacion.errores.sin_permiso'));
    }
}
