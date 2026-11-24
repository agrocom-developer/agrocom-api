<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\MercadoDeSolicitudes;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * El mapa de solicitudes abiertas del piloto (`negociacion-contrato.md` §8): todas las
 * solicitudes `abierta` y `con_ofertas` de Santa Cruz con filtros del usuario, sin radio
 * fijo de operación y sin datos privados del cliente. Requiere red.
 */
#[OA\Tag(name: 'Mercado', description: 'El piloto ve en un mapa las solicitudes abiertas para decidir a qué zona ir con su equipo (los valles frente a la Chiquitania) y a cuáles ofertar.')]
#[OA\Schema(
    schema: 'SolicitudAbierta',
    title: 'Solicitud abierta (mapa)',
    description: 'Lo que un piloto ve de una solicitud antes de ofertar. Nunca incluye nombre, teléfono, documento ni dirección del cliente, ni los precios de las ofertas de otros proveedores.',
    required: ['id', 'hectareas_solicitadas', 'precio_ha_propuesto', 'ofertas_vigentes'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'centro', description: 'Centroide de los lotes.', properties: [new OA\Property(property: 'latitud', type: 'number', example: -17.7833), new OA\Property(property: 'longitud', type: 'number', example: -63.1821)], type: 'object', nullable: true),
        new OA\Property(property: 'hectareas_solicitadas', type: 'string', example: '80.0000'),
        new OA\Property(property: 'precio_ha_propuesto', type: 'string', example: '125.0000'),
        new OA\Property(property: 'zona', type: 'string', nullable: true, example: 'Warnes'),
        new OA\Property(property: 'zona_id', type: 'integer', nullable: true),
        new OA\Property(property: 'macrorregion', type: 'string', nullable: true, enum: ['cercado', 'norte_integrado', 'chiquitania', 'valles', 'chaco']),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true),
        new OA\Property(property: 'tipo_aplicacion', type: 'string'),
        new OA\Property(property: 'ventana_desde', type: 'string', format: 'date'),
        new OA\Property(property: 'ventana_hasta', type: 'string', format: 'date'),
        new OA\Property(property: 'ofertas_vigentes', description: 'Cuántas ofertas vigentes tiene (sin sus precios).', type: 'integer'),
        new OA\Property(property: 'mi_negociacion', description: 'Estado de la última oferta de mi proveedor, o null si no oferté.', type: 'string', nullable: true),
        new OA\Property(property: 'distancia_km', description: 'Solo si se envían `lat` y `lon`; esas coordenadas no se guardan.', type: 'string', nullable: true),
        new OA\Property(property: 'publicada_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'vence_en', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'producto_indicado', description: 'Solo en el detalle.', type: 'string'),
        new OA\Property(property: 'aporta_producto', description: 'Solo en el detalle.', type: 'string'),
        new OA\Property(property: 'urgente', description: 'Solo en el detalle.', type: 'boolean'),
        new OA\Property(property: 'observaciones', description: 'Solo en el detalle.', type: 'string', nullable: true),
        new OA\Property(property: 'terreno', description: 'Solo en el detalle: nombre, hectáreas, `geometria` GeoJSON de los lotes, referencias de acceso, colindantes sensibles, acceso difícil y `fotos` del estado del terreno con URL firmada de corta vida (se piden sin token).', type: 'object', nullable: true),
    ],
    type: 'object',
)]
final class MercadoController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly MercadoDeSolicitudes $mercado,
    ) {}

    #[OA\Get(
        path: '/api/solicitudes-abiertas',
        operationId: 'listarSolicitudesAbiertas',
        description: 'Las solicitudes `abierta` y `con_ofertas`, para pintar el mapa. Todos los filtros son del usuario: no hay un radio fijo de operación. Paginación por cursor opaco (`siguiente_cursor`). '
            .'Solo cuentas de proveedor habilitadas (aprobadas y con un equipo apto); si no, 403 `proveedor_no_habilitado`.',
        summary: 'Mapa de solicitudes abiertas',
        security: [['tokenDispositivo' => []]],
        tags: ['Mercado'],
        parameters: [
            new OA\Parameter(name: 'bbox', in: 'query', required: false, description: 'Encuadre del mapa: `oeste,sur,este,norte`.', schema: new OA\Schema(type: 'string', example: '-64.5,-18.5,-61.0,-16.5')),
            new OA\Parameter(name: 'zona_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'macrorregion', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['cercado', 'norte_integrado', 'chiquitania', 'valles', 'chaco'])),
            new OA\Parameter(name: 'hectareas_min', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hectareas_max', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'precio_ha_min', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'precio_ha_max', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, description: 'Ventana deseada que empieza antes de esa fecha.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'cultivo', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tipo_aplicacion', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orden', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['distancia', 'precio_ha', 'hectareas', 'reciente'])),
            new OA\Parameter(name: 'lat', in: 'query', required: false, description: 'Latitud del piloto para `orden=distancia`; no se guarda.', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'lon', in: 'query', required: false, description: 'Longitud del piloto para `orden=distancia`; no se guarda.', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limite', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitudes del mapa.', content: new OA\JsonContent(required: ['solicitudes', 'siguiente_cursor'], properties: [new OA\Property(property: 'solicitudes', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolicitudAbierta')), new OA\Property(property: 'siguiente_cursor', type: 'string', nullable: true)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol no ve el mapa o el proveedor no está habilitado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Encuadre inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mercado.ver');

        try {
            $pagina = $this->mercado->listar($this->usuarioId(), $request->query());
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['solicitudes' => $pagina['items'], 'siguiente_cursor' => $pagina['siguiente_cursor']]);
    }

    #[OA\Get(
        path: '/api/solicitudes-abiertas/resumen-zonas',
        operationId: 'resumenZonasAbiertas',
        description: 'Cuántas solicitudes, hectáreas y qué precio promedio hay por zona y macrorregión, con los mismos filtros del mapa: responde «cuántos clientes y cuántas hectáreas hay en los valles frente a la Chiquitania».',
        summary: 'Resumen por zona y macrorregión',
        security: [['tokenDispositivo' => []]],
        tags: ['Mercado'],
        responses: [
            new OA\Response(response: 200, description: 'Una fila por zona.', content: new OA\JsonContent(required: ['zonas'], properties: [new OA\Property(property: 'zonas', type: 'array', items: new OA\Items(type: 'object'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol no ve el mapa o el proveedor no está habilitado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function resumen(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mercado.ver');

        try {
            return response()->json(['zonas' => $this->mercado->resumenDeZonas($this->usuarioId(), $request->query())]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }

    #[OA\Get(
        path: '/api/solicitudes-abiertas/{id}',
        operationId: 'verSolicitudAbierta',
        description: 'El detalle previo a ofertar: los lotes como polígonos GeoJSON, el estado del terreno, el cultivo, el producto y las observaciones. No incluye nombre, teléfono ni documento del cliente: esos datos se entregan solo tras la adjudicación.',
        summary: 'Detalle de una solicitud abierta',
        security: [['tokenDispositivo' => []]],
        tags: ['Mercado'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La solicitud.', content: new OA\JsonContent(required: ['solicitud'], properties: [new OA\Property(property: 'solicitud', ref: '#/components/schemas/SolicitudAbierta')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol no ve el mapa o el proveedor no está habilitado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 404, description: 'No existe o ya no está abierta.'),
        ],
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.mercado.ver');

        try {
            return response()->json(['solicitud' => $this->mercado->detalle($this->usuarioId(), $id)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }
}
