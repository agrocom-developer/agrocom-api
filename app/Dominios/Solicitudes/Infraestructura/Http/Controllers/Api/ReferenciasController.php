<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Solicitudes\Aplicacion\PrecioDeReferencia;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * Precio de referencia por hectárea (`negociacion-contrato.md` §3.1): una guía para el
 * cliente al proponer y para el piloto al ofertar. Es un dato del catálogo, sin datos de
 * ninguna cuenta; basta con una sesión de cliente o de proveedor.
 */
final class ReferenciasController
{
    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly PrecioDeReferencia $referencia,
    ) {}

    #[OA\Get(
        path: '/api/referencias/precio',
        operationId: 'precioDeReferencia',
        description: 'El precio por hectárea de referencia para un tipo de aplicación, de lo más específico a lo más general: la zona, su macrorregión o el precio general del tipo. '
            .'`nivel` dice de dónde salió (`zona`, `macrorregion`, `general` o `respaldo`). Es una guía, no un límite: el precio máximo de sanidad es otro parámetro. '
            .'Sin `zona_id` ni `macrorregion` devuelve el precio general. Importe como decimal exacto en string.',
        summary: 'Precio de referencia por hectárea',
        security: [['tokenDispositivo' => []]],
        tags: ['Solicitudes'],
        parameters: [
            new OA\Parameter(name: 'tipo_aplicacion', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro'])),
            new OA\Parameter(name: 'zona_id', in: 'query', required: false, description: 'Ver `GET /api/zonas`.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'macrorregion', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'chiquitania')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'El precio de referencia.',
                content: new OA\JsonContent(
                    required: ['tipo_aplicacion', 'precio_ha', 'nivel'],
                    properties: [
                        new OA\Property(property: 'tipo_aplicacion', type: 'string', example: 'herbicida'),
                        new OA\Property(property: 'zona_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'macrorregion', type: 'string', nullable: true, example: 'chiquitania'),
                        new OA\Property(property: 'precio_ha', type: 'string', nullable: true, example: '132.0000'),
                        new OA\Property(property: 'nivel', type: 'string', enum: ['zona', 'macrorregion', 'general', 'respaldo'], nullable: true),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente ni de proveedor.'),
            new OA\Response(response: 422, description: 'Tipo de aplicación, zona o macrorregión inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function precio(Request $request): JsonResponse
    {
        abort_unless(
            $this->identidad->tienePermiso($request, 'solicitudes.solicitud.gestionar')
                || $this->identidad->tienePermiso($request, 'negociacion.mercado.ver'),
            403,
            __('aprobacion.errores.sin_permiso'),
        );

        $datos = $request->validate([
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacionServicio::class)],
            'zona_id' => ['nullable', 'integer', Rule::exists(Zona::class, 'id')->whereNull('deleted_at')],
            'macrorregion' => ['nullable', 'string', 'max:30', Rule::exists(Zona::class, 'macrorregion')->whereNull('deleted_at')],
        ]);

        return response()->json($this->referencia->para($datos['tipo_aplicacion'], isset($datos['zona_id']) ? (int) $datos['zona_id'] : null, $datos['macrorregion'] ?? null));
    }
}
