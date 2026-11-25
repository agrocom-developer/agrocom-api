<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\ConfirmarPrecioFinal;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Precio final del servicio: lo calcula el cierre del piloto (hectáreas ejecutadas por precio
 * acordado) y el cliente, que es quien paga, tiene la última palabra: lo confirma aquí o abre
 * una disputa. No procesa cobros ni cambia el estado (ADR 0034).
 */
#[OA\Schema(
    schema: 'PrecioFinal',
    title: 'Precio final del servicio y su confirmación',
    description: 'Los valores del cierre del piloto. `confirmado` pasa a `true` cuando el cliente lo confirma; `confirmado_en` es nulo hasta entonces.',
    required: ['hectareas_ejecutadas', 'precio_ha_acordado', 'monto_final', 'confirmado', 'confirmado_en'],
    properties: [
        new OA\Property(property: 'hectareas_ejecutadas', type: 'string', example: '78.5000'),
        new OA\Property(property: 'precio_ha_acordado', type: 'string', example: '118.0000'),
        new OA\Property(property: 'monto_final', type: 'string', example: '9263.00'),
        new OA\Property(property: 'confirmado', type: 'boolean'),
        new OA\Property(property: 'confirmado_en', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class PrecioFinalController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly ConfirmarPrecioFinal $precios,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/precio-final/confirmar',
        operationId: 'confirmarPrecioFinalDeSolicitud',
        description: 'El cliente de la solicitud confirma el precio final (hectáreas ejecutadas por precio acordado) cuando el piloto ya cerró el servicio: solo con la solicitud `finalizada` o `calificada`. '
            .'Sin cuerpo: el servidor copia las hectáreas, el precio y el monto del cierre. Idempotente por solicitud (repetirlo responde 200 con el mismo precio final) y lo confirmado no se reescribe. '
            .'No cambia el estado ni cobra nada: el pago es directo. Si el cliente no está de acuerdo, la salida es abrir una disputa. Avisa al proveedor adjudicado.',
        summary: 'Confirmar el precio final',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Precio final confirmado.', content: new OA\JsonContent(required: ['precio_final'], properties: [new OA\Property(property: 'precio_final', ref: '#/components/schemas/PrecioFinal')], type: 'object')),
            new OA\Response(response: 200, description: 'Ya estaba confirmado.', content: new OA\JsonContent(required: ['precio_final'], properties: [new OA\Property(property: 'precio_final', ref: '#/components/schemas/PrecioFinal')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no confirma el precio final (solo `message`), o la cuenta es del proveedor (`message` y `codigo`: `precio_final_solo_cliente`).'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
            new OA\Response(response: 409, description: 'El piloto aún no cerró el servicio, o hay una disputa abierta (`estado_invalido`).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function confirmar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.precio_final.confirmar');

        try {
            [$precio, $creado] = $this->precios->ejecutar($this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['precio_final' => Presentacion::precioFinal($precio)], $creado ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/precio-final',
        operationId: 'verPrecioFinalDeSolicitud',
        description: 'El precio final del servicio y si el cliente ya lo confirmó: lo consultan el cliente de la solicitud y el proveedor adjudicado. Responde 200 con `precio_final: null` mientras el piloto no cierre el servicio.',
        summary: 'Ver el precio final',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El precio final, o `null`.', content: new OA\JsonContent(required: ['precio_final'], properties: [new OA\Property(property: 'precio_final', allOf: [new OA\Schema(ref: '#/components/schemas/PrecioFinal')], nullable: true)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es de la cuenta.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.ver');

        try {
            $precio = $this->precios->ver($this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['precio_final' => $precio === null ? null : Presentacion::precioFinal($precio)]);
    }
}
