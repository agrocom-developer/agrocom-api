<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\ConfirmarPago;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * Confirmación del pago fuera de la aplicación (ADR 0034). El proveedor informa que
 * recibió el pago; el cliente solo lo consulta. No procesa cobros ni cambia el estado.
 */
#[OA\Schema(
    schema: 'PagoConfirmado',
    title: 'Pago confirmado por el proveedor',
    description: 'Registro informativo: el pago ocurrió fuera de la aplicación y lo informa el proveedor. `monto` es el monto final del servicio al momento de confirmar.',
    required: ['id', 'uuid_cliente', 'solicitud_id', 'monto', 'confirmado_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
        new OA\Property(property: 'solicitud_id', type: 'integer'),
        new OA\Property(property: 'monto', type: 'string', example: '9440.00'),
        new OA\Property(property: 'metodo', type: 'string', enum: ['efectivo', 'transferencia', 'qr', 'otro'], nullable: true),
        new OA\Property(property: 'nota', type: 'string', nullable: true),
        new OA\Property(property: 'confirmado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class PagoController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly ConfirmarPago $pagos,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/pago-confirmado',
        operationId: 'confirmarPagoDeSolicitud',
        description: 'El titular del proveedor adjudicado informa que recibió el pago, que ocurre fuera de la aplicación. Solo con la solicitud `finalizada`, `calificada` o `disputada`. '
            .'El monto lo fija el servidor: es el monto final congelado. Un solo registro por solicitud, idempotente por `uuid_cliente` (repetirlo responde 200 con el mismo registro); '
            .'una vez confirmado no se reescribe y otro `uuid_cliente` responde 409 `pago_ya_confirmado`. No cambia el estado de la solicitud. '
            .'Lo escribe solo el proveedor (titular); el cliente lo lee con `GET /api/solicitudes/{id}/pago-confirmado`.',
        summary: 'Informar que recibí el pago',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuid_cliente'],
                properties: [
                    new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'metodo', type: 'string', enum: ['efectivo', 'transferencia', 'qr', 'otro'], nullable: true),
                    new OA\Property(property: 'nota', type: 'string', maxLength: 280, nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Pago confirmado.', content: new OA\JsonContent(required: ['pago'], properties: [new OA\Property(property: 'pago', ref: '#/components/schemas/PagoConfirmado')], type: 'object')),
            new OA\Response(response: 200, description: 'Duplicado: ya estaba confirmado con ese `uuid_cliente`.', content: new OA\JsonContent(required: ['pago'], properties: [new OA\Property(property: 'pago', ref: '#/components/schemas/PagoConfirmado')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no confirma pagos (solo `message`), o la cuenta no es el titular del proveedor adjudicado (`message` y `codigo`: `pago_solo_titular`).'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud no está finalizada o el pago ya fue confirmado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Datos inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.pago.confirmar');
        $datos = $request->validate([
            'uuid_cliente' => ['required', 'uuid'],
            'metodo' => ['nullable', Rule::in(ConfirmarPago::METODOS)],
            'nota' => ['nullable', 'string', 'max:280'],
        ]);

        try {
            [$pago, $creado] = $this->pagos->ejecutar($this->usuarioId(), $id, $datos);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['pago' => Presentacion::pago($pago)], $creado ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/pago-confirmado',
        operationId: 'verPagoConfirmadoDeSolicitud',
        description: 'Si el proveedor ya informó que recibió el pago: el cliente de la solicitud y el proveedor adjudicado lo consultan. Sin confirmar responde 200 con `pago: null`.',
        summary: 'Ver la confirmación del pago',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El pago, o `null`.', content: new OA\JsonContent(required: ['pago'], properties: [new OA\Property(property: 'pago', allOf: [new OA\Schema(ref: '#/components/schemas/PagoConfirmado')], nullable: true)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.ver');

        try {
            $pago = $this->pagos->ver($this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['pago' => $pago === null ? null : Presentacion::pago($pago)]);
    }
}
