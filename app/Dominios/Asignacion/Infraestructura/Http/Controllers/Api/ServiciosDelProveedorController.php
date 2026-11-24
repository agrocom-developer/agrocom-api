<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Asignacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Asignacion\Aplicacion\ReputacionDelProveedor;
use App\Dominios\Asignacion\Aplicacion\SalirEnCamino;
use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Http\Requests\CancelarComoProveedorRequest;
use App\Dominios\Asignacion\Infraestructura\Http\RespuestaDeAsignacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Lo que el proveedor hace sobre un servicio ya aceptado y sobre su reputación
 * (especificación §10.2): cancelar con motivo y consultar sus tasas y sanciones.
 */
final class ServiciosDelProveedorController
{
    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly CancelarComoProveedor $cancelar,
        private readonly SalirEnCamino $salirEnCamino,
        private readonly ReputacionDelProveedor $reputacion,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/en-camino',
        operationId: 'avisarEquipoEnCamino',
        description: 'El proveedor avisa que el equipo salió hacia el lote: la solicitud pasa de `asignada` a `en_camino` y se avisa al cliente. Solo lo hace el proveedor al que se asignó el servicio. Es idempotente: avisar de nuevo no cambia nada ni repite el aviso. Requiere red, como las demás acciones de negocio del proveedor sin outbox.',
        summary: 'El equipo salió hacia el lote',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El servicio quedó en camino.', content: new OA\JsonContent(required: ['solicitud_id', 'estado'], properties: [new OA\Property(property: 'solicitud_id', type: 'integer'), new OA\Property(property: 'estado', type: 'string', example: 'en_camino')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no responde ofertas.'),
            new OA\Response(response: 404, description: 'La solicitud no existe o no está asignada a tu proveedor.'),
            new OA\Response(response: 422, description: 'La solicitud no está asignada.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function enCamino(Request $request, int $id): JsonResponse
    {
        abort_unless($this->identidad->tienePermiso($request, 'asignacion.oferta.responder'), 403, __('aprobacion.errores.sin_permiso'));

        try {
            $this->salirEnCamino->ejecutar($this->usuarioId(), $id);
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['solicitud_id' => $id, 'estado' => 'en_camino']);
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/cancelar-proveedor',
        operationId: 'cancelarSolicitudComoProveedor',
        description: 'El proveedor cancela un servicio que ya aceptó (`asignada` o `en_camino`), con motivo. La solicitud vuelve a asignación con prioridad, sin ofrecérsela de nuevo a quien canceló, y se avisa al cliente. '
            .'Suma a su tasa de cancelación: pesa el doble con menos de 12 horas de anticipación y no pesa si la `causa` es `emergencia` o `falla_de_equipo` avisada con más de 12 horas. Tres cancelaciones en 30 días generan una advertencia; cinco, una restricción temporal de ofertas (política de cancelación).',
        summary: 'Cancelar un servicio aceptado',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['motivo'],
                properties: [
                    new OA\Property(property: 'motivo', type: 'string', example: 'El dron quedó en reparación'),
                    new OA\Property(property: 'causa', type: 'string', enum: ['otra', 'falla_de_equipo', 'emergencia'], nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Cancelación registrada.', content: new OA\JsonContent(required: ['solicitud_id', 'peso'], properties: [new OA\Property(property: 'solicitud_id', type: 'integer'), new OA\Property(property: 'peso', description: 'Peso con que cuenta en la tasa de cancelación.', type: 'integer', example: 1)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no responde ofertas.'),
            new OA\Response(response: 404, description: 'La solicitud no existe o no está asignada a tu proveedor.'),
            new OA\Response(response: 422, description: 'Falta el motivo o la solicitud ya no admite cancelación del proveedor.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cancelar(CancelarComoProveedorRequest $request, int $id): JsonResponse
    {
        abort_unless($this->identidad->tienePermiso($request, 'asignacion.oferta.responder'), 403, __('aprobacion.errores.sin_permiso'));

        try {
            $cancelacion = $this->cancelar->ejecutar(
                $this->usuarioId(),
                $id,
                (string) $request->validated('motivo'),
                CausaCancelacion::tryFrom((string) $request->validated('causa')) ?? CausaCancelacion::Otra,
            );
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        return response()->json(['solicitud_id' => $cancelacion->solicitud_id, 'peso' => $cancelacion->peso]);
    }

    #[OA\Get(
        path: '/api/reputacion',
        operationId: 'verReputacion',
        description: 'Tu calificación, tus tasas de aceptación y de cancelación (cuatro decimales, entre 0 y 1) y las sanciones vigentes con su motivo. Una sanción se apela ante un administrador (política de calificación, «Defensa»).',
        summary: 'Mi reputación',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reputación del proveedor.',
                content: new OA\JsonContent(
                    required: ['calificacion_promedio', 'calificaciones_conteo', 'tasa_aceptacion', 'tasa_cancelacion', 'sanciones'],
                    properties: [
                        new OA\Property(property: 'calificacion_promedio', type: 'string', example: '4.60'),
                        new OA\Property(property: 'calificaciones_conteo', type: 'integer', example: 24),
                        new OA\Property(property: 'tasa_aceptacion', type: 'string', example: '0.8500'),
                        new OA\Property(property: 'tasa_cancelacion', type: 'string', example: '0.0200'),
                        new OA\Property(
                            property: 'sanciones',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'tipo', type: 'string', enum: ['advertencia', 'restriccion_ofertas', 'revision_suspension']),
                                    new OA\Property(property: 'causa', type: 'string', enum: ['cancelaciones', 'calificacion', 'aceptacion']),
                                    new OA\Property(property: 'motivo', type: 'string'),
                                    new OA\Property(property: 'desde', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'hasta', type: 'string', format: 'date-time', nullable: true),
                                ],
                                type: 'object',
                            ),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no recibe ofertas.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor.'),
        ],
    )]
    public function reputacion(Request $request): JsonResponse
    {
        abort_unless($this->identidad->tienePermiso($request, 'asignacion.oferta.ver'), 403, __('aprobacion.errores.sin_permiso'));

        try {
            $datos = $this->reputacion->ejecutar($this->usuarioId());
        } catch (AsignacionInvalida $excepcion) {
            return RespuestaDeAsignacion::de($excepcion);
        }

        $proveedor = $datos['proveedor'];

        return response()->json([
            'calificacion_promedio' => $proveedor->calificacionPromedio,
            'calificaciones_conteo' => $proveedor->calificacionesConteo,
            'tasa_aceptacion' => $proveedor->tasaAceptacion,
            'tasa_cancelacion' => $proveedor->tasaCancelacion,
            'sanciones' => array_map(fn ($sancion): array => [
                'tipo' => $sancion->tipo->value,
                'causa' => $sancion->causa->value,
                'motivo' => $sancion->motivo,
                'desde' => $sancion->desde->toIso8601String(),
                'hasta' => $sancion->hasta?->toIso8601String(),
            ], $datos['sanciones']),
        ]);
    }

    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }
}
