<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Calificaciones\Aplicacion\CalificarComoCliente;
use App\Dominios\Calificaciones\Aplicacion\ConsultarCalificacionesDelServicio;
use App\Dominios\Calificaciones\Aplicacion\ParametrosDeCalificacion;
use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\EtiquetaCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Calificaciones\Infraestructura\Http\Requests\CalificarServicioRequest;
use App\Dominios\Calificaciones\Infraestructura\Http\Resources\CalificacionResource;
use App\Dominios\Calificaciones\Infraestructura\Http\RespuestaDeCalificacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Calificación del servicio por el cliente (especificación §10.1, ADR 0031). Requiere
 * red: es la superficie online del cliente, sin outbox (ADR 0028). Adaptador delgado
 * (ADR 0008): el alcance (solo los servicios propios, uno ajeno es 404, invariante 5
 * de CLAUDE.md) y las reglas viven en los casos de uso.
 */
#[OA\Tag(
    name: 'Calificaciones',
    description: 'El cliente califica al proveedor, al piloto y al auxiliar de un servicio completado, durante 7 días; el proveedor califica al cliente en privado y ve las calificaciones que recibe (ADR 0031).',
)]
#[OA\Schema(
    schema: 'CalificacionesDelServicio',
    title: 'Estado de la calificación de un servicio',
    description: 'A quiénes se puede calificar en el servicio, a quiénes ya se calificó y hasta cuándo.',
    required: ['puede_calificar', 'plazo_dias', 'sujetos', 'etiquetas'],
    properties: [
        new OA\Property(property: 'puede_calificar', description: 'Verdadero si el servicio está completado, el plazo sigue abierto y falta calificar a alguien.', type: 'boolean'),
        new OA\Property(property: 'vence_en', description: 'Cuándo vence el plazo; nulo si el servicio todavía no se completó.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'plazo_dias', type: 'integer', example: 7),
        new OA\Property(
            property: 'sujetos',
            type: 'array',
            items: new OA\Items(
                required: ['tipo', 'id', 'nombre'],
                properties: [
                    new OA\Property(property: 'tipo', type: 'string', enum: ['proveedor', 'piloto', 'auxiliar', 'cliente']),
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'calificacion', nullable: true, allOf: [new OA\Schema(ref: '#/components/schemas/Calificacion')]),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(property: 'etiquetas', description: 'Catálogo cerrado de etiquetas que puedes usar.', type: 'array', items: new OA\Items(type: 'string')),
    ],
    type: 'object',
)]
final class CalificacionesDelClienteController
{
    private const PERMISO = 'calificaciones.calificacion.calificar_proveedor';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly CalificarComoCliente $calificar,
        private readonly ConsultarCalificacionesDelServicio $consultar,
        private readonly ParametrosDeCalificacion $parametros,
    ) {}

    #[OA\Get(
        path: '/api/solicitudes/{id}/calificaciones',
        operationId: 'verCalificacionesDelServicio',
        description: 'A quiénes puedes calificar en un servicio tuyo (el proveedor, el piloto y, si el equipo lo llevaba, el auxiliar), a quiénes ya calificaste y hasta cuándo. '
            .'Solo un servicio `completada` se califica, durante 7 días desde que se completó.',
        summary: 'Estado de la calificación de un servicio',
        security: [['tokenDispositivo' => []]],
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Estado de la calificación.', content: new OA\JsonContent(ref: '#/components/schemas/CalificacionesDelServicio')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $estado = $this->consultar->paraCliente($this->usuarioId(), $id);
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        return response()->json(self::cuerpo($estado, AutorCalificacion::Cliente, $this->parametros->plazoDias(), $request));
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/calificaciones',
        operationId: 'calificarServicio',
        description: 'Califica al proveedor, al piloto y al auxiliar de un servicio completado: de 1 a 5 estrellas, etiquetas de un catálogo cerrado y un comentario opcional. '
            .'Cada calificación lleva su `uuid_cliente` (idempotente: reintentar el mismo devuelve la calificación ya guardada) y se guarda una sola vez por sujeto: no se edita. '
            .'Las de una misma llamada se guardan juntas o ninguna. Responde 201 si se creó alguna y 200 si todas eran reintentos.',
        summary: 'Calificar un servicio completado',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['calificaciones'],
                properties: [
                    new OA\Property(
                        property: 'calificaciones',
                        type: 'array',
                        minItems: 1,
                        maxItems: 3,
                        items: new OA\Items(
                            required: ['uuid_cliente', 'sujeto_tipo', 'estrellas'],
                            properties: [
                                new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'sujeto_tipo', type: 'string', enum: ['proveedor', 'piloto', 'auxiliar']),
                                new OA\Property(property: 'estrellas', type: 'integer', minimum: 1, maximum: 5, example: 5),
                                new OA\Property(property: 'etiquetas', type: 'array', items: new OA\Items(type: 'string', enum: ['puntualidad', 'cobertura_pareja', 'comunicacion', 'cuidado_entorno', 'orden_lote']), nullable: true),
                                new OA\Property(property: 'comentario', type: 'string', nullable: true),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
            ),
        ),
        tags: ['Calificaciones'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Calificaciones guardadas.', content: new OA\JsonContent(required: ['calificaciones'], properties: [new OA\Property(property: 'calificaciones', type: 'array', items: new OA\Items(ref: '#/components/schemas/Calificacion'))], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: todas las calificaciones ya estaban guardadas.', content: new OA\JsonContent(required: ['calificaciones'], properties: [new OA\Property(property: 'calificaciones', type: 'array', items: new OA\Items(ref: '#/components/schemas/Calificacion'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
            new OA\Response(response: 409, description: 'Ya calificaste a ese sujeto en este servicio.'),
            new OA\Response(response: 422, description: 'El servicio no está completado, el plazo venció, el sujeto o las etiquetas no corresponden, o los datos son inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CalificarServicioRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request);

        try {
            $resultado = $this->calificar->ejecutar($this->usuarioId(), $id, array_values((array) $request->validated('calificaciones')));
        } catch (CalificacionInvalida $excepcion) {
            return RespuestaDeCalificacion::de($excepcion);
        }

        $creada = array_filter($resultado, fn (array $r): bool => $r['creada']) !== [];

        return response()->json([
            'calificaciones' => array_map(fn (array $r): array => (new CalificacionResource($r['calificacion']))->resolve($request), $resultado),
        ], $creada ? 201 : 200);
    }

    /**
     * @param  array{puede_calificar: bool, vence_en: ?string, sujetos: list<array{tipo: string, id: int, nombre: string, calificacion: ?Calificacion}>}  $estado
     * @return array<string, mixed>
     */
    public static function cuerpo(array $estado, AutorCalificacion $autor, int $plazoDias, Request $request): array
    {
        return [
            'puede_calificar' => $estado['puede_calificar'],
            'vence_en' => $estado['vence_en'],
            'plazo_dias' => $plazoDias,
            'sujetos' => array_map(fn (array $sujeto): array => [
                'tipo' => $sujeto['tipo'],
                'id' => $sujeto['id'],
                'nombre' => $sujeto['nombre'],
                'calificacion' => $sujeto['calificacion'] === null ? null : (new CalificacionResource($sujeto['calificacion']))->resolve($request),
            ], $estado['sujetos']),
            'etiquetas' => array_map(fn (EtiquetaCalificacion $etiqueta): string => $etiqueta->value, $autor->etiquetas()),
        ];
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
