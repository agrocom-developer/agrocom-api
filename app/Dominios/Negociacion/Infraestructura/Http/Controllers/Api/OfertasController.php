<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\ConsultarOfertas;
use App\Dominios\Negociacion\Aplicacion\Negociar;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\ContraofertarRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\OfertarRequest;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Ofertas y contraofertas (`negociacion-contrato.md` §2 y §3). Requieren red: la
 * negociación es una conversación entre dos personas y no pasa por el outbox. Adaptador
 * delgado (ADR 0008): el lado (cliente o proveedor) lo decide el rol del token; el
 * alcance y las reglas viven en los casos de uso. Una oferta ajena responde 404.
 */
#[OA\Tag(name: 'Ofertas', description: 'El proveedor ofrece su precio por hectárea, cada parte contraoferta a la otra y el cliente elige: aceptar una oferta adjudica la solicitud.')]
#[OA\Schema(
    schema: 'Oferta',
    title: 'Oferta o contraoferta',
    description: 'El precio por hectárea que una parte pone sobre una solicitud. El autor alterna: número impar es del proveedor, par es del cliente. El cliente la recibe además con `proveedor` (calificación, tasa de cancelación, dron y fotos aprobadas) y, en el comparador, con `historial`.',
    required: ['id', 'uuid_cliente', 'solicitud_id', 'numero', 'autor', 'tipo', 'precio_ha', 'monto_estimado', 'estado', 'es_definitiva', 'vence_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 41),
        new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
        new OA\Property(property: 'solicitud_id', type: 'integer', example: 31),
        new OA\Property(property: 'proveedor_id', type: 'integer', example: 3),
        new OA\Property(property: 'equipo_id', type: 'integer', nullable: true),
        new OA\Property(property: 'numero', description: 'Posición en la negociación: 1 es la primera oferta del proveedor.', type: 'integer', example: 1),
        new OA\Property(property: 'autor', type: 'string', enum: ['proveedor', 'cliente']),
        new OA\Property(property: 'tipo', type: 'string', enum: ['acepta_propuesta', 'contraoferta']),
        new OA\Property(property: 'precio_ha', type: 'string', example: '118.0000'),
        new OA\Property(property: 'monto_estimado', description: 'Hectáreas solicitadas por este precio.', type: 'string', example: '9440.00'),
        new OA\Property(property: 'estado', type: 'string', enum: ['vigente', 'aceptada', 'reemplazada', 'rechazada', 'retirada', 'vencida', 'cerrada']),
        new OA\Property(property: 'es_definitiva', description: 'La última de la negociación: solo se acepta o se rechaza.', type: 'boolean'),
        new OA\Property(property: 'nota', type: 'string', nullable: true),
        new OA\Property(property: 'oferta_anterior_id', type: 'integer', nullable: true),
        new OA\Property(property: 'distancia_km', type: 'string', nullable: true, example: '42.50'),
        new OA\Property(property: 'vence_en', type: 'string', format: 'date-time'),
        new OA\Property(property: 'respondida_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'creada_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'zona', description: 'Solo para el proveedor: nombre de la zona de la solicitud.', type: 'string', nullable: true, example: 'Valle de Culiacán'),
        new OA\Property(property: 'zona_id', description: 'Solo para el proveedor: id de la zona de la solicitud.', type: 'integer', nullable: true),
        new OA\Property(property: 'lote', description: 'Solo para el proveedor: nombre del lote del cliente. Viene solo cuando la solicitud quedó adjudicada a ese proveedor; antes es `null`.', type: 'string', nullable: true, example: 'Lote Norte'),
        new OA\Property(property: 'proveedor', description: 'Solo para el cliente: `nombre`, `foto_url` (foto de perfil del piloto del equipo o del titular, URL firmada de corta vida que se pide sin token; `null` si no hay), calificación, tasa de cancelación y dron con fotos aprobadas.', type: 'object', nullable: true),
        new OA\Property(property: 'historial', description: 'Solo en el comparador del cliente: las ofertas de esa negociación.', type: 'array', items: new OA\Items(type: 'object'), nullable: true),
    ],
    type: 'object',
)]
final class OfertasController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly Negociar $negociar,
        private readonly ConsultarOfertas $consultar,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/ofertas',
        operationId: 'ofertarSolicitud',
        description: 'La primera oferta del proveedor sobre una solicitud `abierta` o `con_ofertas`. `tipo=acepta_propuesta` acepta exactamente el precio propuesto; `tipo=contraoferta` indica `precio_ha`, distinto del propuesto. Sin `equipo_id` usa el primer equipo apto. Idempotente por `uuid_cliente`: reintentar responde 200 con la misma oferta. '
            .'Solo cuentas de proveedor aprobadas y con un equipo apto; un proveedor no oferta sobre solicitudes de su propia cuenta de cliente ni de otra cuenta de la misma persona.',
        summary: 'Ofertar sobre una solicitud',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['uuid_cliente', 'tipo'],
                properties: [
                    new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'tipo', type: 'string', enum: ['acepta_propuesta', 'contraoferta']),
                    new OA\Property(property: 'precio_ha', description: 'Obligatorio en `contraoferta`.', type: 'string', nullable: true, example: '118'),
                    new OA\Property(property: 'equipo_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'nota', type: 'string', nullable: true, example: 'Puedo ir la primera semana.'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Oferta creada.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento: ya existía una oferta con ese `uuid_cliente`.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol no oferta, el proveedor no tiene un equipo apto (`proveedor_no_habilitado`) o la cuenta todavía no está aprobada (`cuenta_no_aprobada`, ADR 0046: entra y ve sus pantallas, pero no oferta).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 404, description: 'La solicitud no existe o la cuenta no es de un proveedor.'),
            new OA\Response(response: 409, description: 'La solicitud ya no está abierta, o ya hay una negociación vigente o cerrada con este proveedor.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Precio inválido, sin equipo apto o sobre su propia cuenta.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(OfertarRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.oferta.proveedor');

        try {
            [$oferta, $creada] = $this->negociar->ofertar($this->usuarioId(), $id, $request->validated());
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['oferta' => Presentacion::oferta($oferta)], $creada ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/ofertas',
        operationId: 'compararOfertas',
        description: 'El comparador del cliente: una fila por proveedor con su oferta más reciente, el historial de esa negociación y los datos del proveedor para decidir (calificación, tasa de cancelación, distancia, modelo del dron y fotos aprobadas). Solo el cliente de la solicitud; la de otro cliente responde 404.',
        summary: 'Comparar las ofertas recibidas',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Ofertas.', content: new OA\JsonContent(required: ['ofertas'], properties: [new OA\Property(property: 'ofertas', type: 'array', items: new OA\Items(ref: '#/components/schemas/Oferta'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function deLaSolicitud(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.oferta.cliente');

        try {
            return response()->json(['ofertas' => $this->consultar->delCliente($this->usuarioId(), $id)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }

    #[OA\Get(
        path: '/api/ofertas',
        operationId: 'listarOfertas',
        description: 'Las ofertas de la cuenta, de la más nueva a la más vieja. Para el proveedor: las de su proveedor (sin precios de otros proveedores). Para el cliente: con `solicitud_id`, las de esa solicitud propia.',
        summary: 'Mis ofertas',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['vigente', 'aceptada', 'reemplazada', 'rechazada', 'retirada', 'vencida', 'cerrada'])),
            new OA\Parameter(name: 'solicitud_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ofertas.', content: new OA\JsonContent(required: ['ofertas'], properties: [new OA\Property(property: 'ofertas', type: 'array', items: new OA\Items(ref: '#/components/schemas/Oferta'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor o la solicitud no es suya.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $lado = $this->lado($request);
        $solicitudId = $request->query('solicitud_id') === null ? null : (int) $request->query('solicitud_id');
        $estado = is_string($request->query('estado')) ? $request->query('estado') : null;

        try {
            $ofertas = $lado === Negociar::LADO_PROVEEDOR
                ? $this->consultar->delProveedor($this->usuarioId(), $estado, $solicitudId)
                : $this->consultar->delCliente($this->usuarioId(), $solicitudId ?? 0);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['ofertas' => $ofertas]);
    }

    #[OA\Get(
        path: '/api/ofertas/{id}',
        operationId: 'verOferta',
        description: 'Una oferta de la negociación, para su proveedor o para el cliente de la solicitud. Otra cuenta recibe 404.',
        summary: 'Detalle de una oferta',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La oferta.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $lado = $this->lado($request);

        try {
            return response()->json(['oferta' => $this->consultar->una($lado, $this->usuarioId(), $id)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/contraofertar',
        operationId: 'contraofertarOferta',
        description: 'Responde a la oferta vigente de la contraparte con otro precio por hectárea: la oferta anterior pasa a `reemplazada` y se crea la siguiente (`numero + 1`), vigente para la otra parte. La oferta número 5 es definitiva y ya no admite contraoferta. Idempotente por `uuid_cliente` (opcional).',
        summary: 'Contraofertar',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['precio_ha'],
                properties: [
                    new OA\Property(property: 'precio_ha', type: 'string', example: '122'),
                    new OA\Property(property: 'nota', type: 'string', nullable: true),
                    new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid', nullable: true),
                ],
                type: 'object',
            ),
        ),
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'La oferta nueva.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 200, description: 'Reintento con el mismo `uuid_cliente`.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia, o la cuenta todavía no está aprobada (`cuenta_no_aprobada`, ADR 0046).', content: new OA\JsonContent(ref: '#/components/schemas/CuentaNoAprobada')),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
            new OA\Response(response: 409, description: 'La oferta ya no está vigente, no es su turno o es definitiva.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Precio inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function contraofertar(ContraofertarRequest $request, int $id): JsonResponse
    {
        $lado = $this->lado($request);

        try {
            [$oferta, $creada] = $this->negociar->contraofertar($lado, $this->usuarioId(), $id, (string) $request->validated('precio_ha'), $request->validated('nota'), $request->validated('uuid_cliente'));
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['oferta' => Presentacion::oferta($oferta)], $creada ? 201 : 200);
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/aceptar',
        operationId: 'aceptarOferta',
        description: 'Acepta la oferta vigente de la contraparte (el cliente acepta una oferta del proveedor; el proveedor acepta la contraoferta del cliente). Es atómico sobre la solicitud: adjudica, fija el precio acordado y el monto estimado, cierra las demás ofertas vigentes y abre el contacto y el chat. '
            .'Dos aceptaciones simultáneas de ofertas distintas dejan una `aceptada` y la otra con 409 `solicitud_ya_adjudicada`. Aceptar dos veces la misma oferta devuelve la misma oferta.',
        summary: 'Aceptar una oferta',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Oferta aceptada: la solicitud queda `adjudicada`.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia, o la cuenta todavía no está aprobada (`cuenta_no_aprobada`, ADR 0046).', content: new OA\JsonContent(ref: '#/components/schemas/CuentaNoAprobada')),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
            new OA\Response(response: 409, description: '`oferta_no_vigente`, `solicitud_ya_adjudicada`, `equipo_no_disponible`, `no_es_su_turno` o `solicitud_no_abierta`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function aceptar(Request $request, int $id): JsonResponse
    {
        $lado = $this->lado($request);

        try {
            $oferta = $this->negociar->aceptar($lado, $this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['oferta' => Presentacion::oferta($oferta)]);
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/rechazar',
        operationId: 'rechazarOferta',
        description: 'Rechaza la oferta vigente de la contraparte: cierra la negociación y el proveedor no puede abrir otra en esa solicitud. Si era la última vigente, la solicitud vuelve a `abierta`.',
        summary: 'Rechazar una oferta',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Oferta rechazada.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
            new OA\Response(response: 409, description: 'La oferta ya no está vigente o no es su turno.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $lado = $this->lado($request);

        try {
            $oferta = $this->negociar->rechazar($lado, $this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['oferta' => Presentacion::oferta($oferta)]);
    }

    #[OA\Post(
        path: '/api/ofertas/{id}/retirar',
        operationId: 'retirarOferta',
        description: 'El autor retira su oferta mientras sigue vigente. Si era la última vigente, la solicitud vuelve a `abierta`. Un proveedor que retira puede abrir otra negociación mientras la solicitud siga abierta.',
        summary: 'Retirar una oferta propia',
        security: [['tokenDispositivo' => []]],
        tags: ['Ofertas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Oferta retirada.', content: new OA\JsonContent(required: ['oferta'], properties: [new OA\Property(property: 'oferta', ref: '#/components/schemas/Oferta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no negocia.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
            new OA\Response(response: 409, description: 'La oferta ya no está vigente o no es del solicitante.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function retirar(Request $request, int $id): JsonResponse
    {
        $lado = $this->lado($request);

        try {
            $oferta = $this->negociar->retirar($lado, $this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['oferta' => Presentacion::oferta($oferta)]);
    }
}
