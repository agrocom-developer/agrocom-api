<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\ActualizarPreferenciasRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Preferencias de aviso (`negociacion-contrato.md` §7): el piloto activa o desactiva los
 * avisos de nuevas solicitudes (el incentivo para recibir ofertas de
 * trabajo) y cada cuenta elige qué categorías recibe. Las obligatorias no se apagan.
 */
#[OA\Tag(name: 'Preferencias', description: 'Qué avisos recibe la cuenta. Las categorías obligatorias (adjudicación, cierre, expiración, cuenta y aprobación, reclamos, emergencias) no aparecen y no se pueden desactivar.')]
#[OA\Schema(
    schema: 'PreferenciasDeAviso',
    title: 'Preferencias de aviso',
    required: ['nuevas_solicitudes', 'ofertas', 'mensajes', 'avance', 'calificaciones'],
    properties: [
        new OA\Property(property: 'nuevas_solicitudes', description: 'Solo aplica a cuentas de proveedor.', properties: [
            new OA\Property(property: 'activo', type: 'boolean', example: true),
            new OA\Property(property: 'hectareas_minimas', type: 'string', nullable: true, example: '50.0000'),
        ], type: 'object'),
        new OA\Property(property: 'ofertas', type: 'boolean'),
        new OA\Property(property: 'mensajes', type: 'boolean'),
        new OA\Property(property: 'avance', type: 'boolean'),
        new OA\Property(property: 'calificaciones', type: 'boolean'),
    ],
    type: 'object',
)]
final class PreferenciasController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly PreferenciasDeAviso $preferencias,
    ) {}

    #[OA\Get(
        path: '/api/notificaciones/preferencias',
        operationId: 'verPreferenciasDeAviso',
        description: 'Las preferencias de aviso de la cuenta. Sin nada guardado: todo activo y sin mínimo de hectáreas.',
        summary: 'Mis preferencias de aviso',
        security: [['tokenDispositivo' => []]],
        tags: ['Preferencias'],
        responses: [
            new OA\Response(response: 200, description: 'Las preferencias.', content: new OA\JsonContent(required: ['preferencias'], properties: [new OA\Property(property: 'preferencias', ref: '#/components/schemas/PreferenciasDeAviso')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona preferencias.'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.preferencias.gestionar');

        return response()->json(['preferencias' => $this->preferencias->de($this->usuarioId())]);
    }

    #[OA\Put(
        path: '/api/notificaciones/preferencias',
        operationId: 'guardarPreferenciasDeAviso',
        description: 'Guarda las preferencias; lo que no se envía conserva su valor. Un piloto activa o desactiva los avisos de nuevas solicitudes y fija las hectáreas mínimas.',
        summary: 'Guardar mis preferencias de aviso',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PreferenciasDeAviso')),
        tags: ['Preferencias'],
        responses: [
            new OA\Response(response: 200, description: 'Las preferencias guardadas.', content: new OA\JsonContent(required: ['preferencias'], properties: [new OA\Property(property: 'preferencias', ref: '#/components/schemas/PreferenciasDeAviso')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona preferencias.'),
            new OA\Response(response: 422, description: 'Datos mal formados.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(ActualizarPreferenciasRequest $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.preferencias.gestionar');
        $datos = $request->validated();

        return response()->json(['preferencias' => $this->preferencias->guardar($this->usuarioId(), $datos)]);
    }
}
