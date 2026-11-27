<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\ActualizarPreferenciasRequest;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Preferencias de aviso (`negociacion-contrato.md` §7): el piloto activa o desactiva los
 * avisos de nuevas solicitudes y los filtra con los suyos (el incentivo para recibir ofertas de
 * trabajo) y cada cuenta elige qué categorías recibe. Las obligatorias no se apagan.
 */
#[OA\Tag(name: 'Preferencias', description: 'Qué avisos recibe la cuenta. Las categorías obligatorias (adjudicación, cierre, cuenta y aprobación, reclamos, emergencias) no aparecen y no se pueden desactivar.')]
#[OA\Schema(
    schema: 'PreferenciasDeAviso',
    title: 'Preferencias de aviso',
    required: ['nuevas_solicitudes', 'ofertas', 'mensajes', 'avance', 'calificaciones'],
    properties: [
        new OA\Property(property: 'nuevas_solicitudes', description: 'Solo aplica a cuentas de proveedor. Además de `activo`, cada filtro del proveedor es una clave (ADR 0045): vacío o ausente no restringe, y gobiernan solo los avisos, no el mapa. La lista de filtros no está cerrada: `filtros_disponibles` dice cuáles conoce el servidor; una clave que no conoce se rechaza con 422 al guardar.', properties: [
            new OA\Property(property: 'activo', type: 'boolean', example: true),
            new OA\Property(property: 'hectareas_minimas', description: 'No avisar de solicitudes con menos hectáreas. Decimal exacto en string (hasta 4 decimales); null no restringe.', type: 'string', nullable: true, example: '50.0000'),
            new OA\Property(property: 'departamentos_excluidos', description: 'Códigos de departamento (dos dígitos, del catálogo de ubicaciones) de los que no se avisa.', type: 'array', items: new OA\Items(type: 'string', example: '09'), example: ['04', '09']),
            new OA\Property(property: 'tipos_aplicacion_excluidos', description: 'Tipos de aplicación de los que no se avisa (`herbicida`, `desecante`…).', type: 'array', items: new OA\Items(type: 'string'), example: ['desecante']),
        ], type: 'object'),
        new OA\Property(property: 'ofertas', type: 'boolean'),
        new OA\Property(property: 'mensajes', type: 'boolean'),
        new OA\Property(property: 'avance', type: 'boolean'),
        new OA\Property(property: 'calificaciones', type: 'boolean'),
        new OA\Property(property: 'filtros_disponibles', description: 'Solo en la respuesta: los filtros que el servidor conoce y el tipo de su valor (`decimal`, `departamentos`, `tipos_aplicacion`), para que la app pinte los que sabe y ignore los que no.', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'clave', type: 'string', example: 'hectareas_minimas'),
            new OA\Property(property: 'tipo', type: 'string', example: 'decimal'),
        ], type: 'object'), readOnly: true),
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
        description: 'Las preferencias de aviso de la cuenta. Sin nada guardado: todo activo y sin filtros.',
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
        description: 'Guarda las preferencias; lo que no se envía conserva su valor. Un proveedor activa o desactiva los avisos de nuevas solicitudes y los filtra (`hectareas_minimas`, `departamentos_excluidos`, `tipos_aplicacion_excluidos`); enviar un filtro como null o vacío lo quita. Un proveedor no disponible (`ocupado` o `en_trabajo`) no recibe avisos aunque este activo.',
        summary: 'Guardar mis preferencias de aviso',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PreferenciasDeAviso')),
        tags: ['Preferencias'],
        responses: [
            new OA\Response(response: 200, description: 'Las preferencias guardadas.', content: new OA\JsonContent(required: ['preferencias'], properties: [new OA\Property(property: 'preferencias', ref: '#/components/schemas/PreferenciasDeAviso')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona preferencias.'),
            new OA\Response(response: 422, description: 'Filtro desconocido, valor de un filtro inválido o datos mal formados.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function update(ActualizarPreferenciasRequest $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.preferencias.gestionar');
        $datos = $request->validated();
        // Los filtros del proveedor no están en las reglas del request (los valida cada filtro): se leen tal cual llegan.
        $datos['nuevas_solicitudes'] = (array) $request->input('nuevas_solicitudes', []);

        try {
            return response()->json(['preferencias' => $this->preferencias->guardar($this->usuarioId(), $datos)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }
}
