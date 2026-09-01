<?php

namespace App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Sincronizacion\Aplicacion\SincronizarLote;
use App\Dominios\Sincronizacion\Infraestructura\Http\Requests\SincronizarLoteRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Adaptador delgado (ADR 0008): valida el sobre del lote, invoca el caso de
 * uso y devuelve la respuesta — ninguna regla de negocio vive acá.
 */
#[OA\Tag(
    name: 'Sincronizacion',
    description: 'Push de escritura offline de la app de campo (espec §2.1, puntos 3 a 5; TE-05). '
        .'Recorte de alcance de la tarea 09: solo `trabajo` y `sesion` — `mezcla`, `recarga`, '
        .'`incidencia` y `acta` llegan con sus propias tareas (ver docs/gestion/cola_tareas.md).',
)]
#[OA\Schema(
    schema: 'RegistroSync',
    title: 'Registro de entrada del lote de sync',
    description: 'Un elemento del arreglo `registros`. La forma exacta de los campos depende de `tipo` '
        ."('trabajo' o 'sesion'); un registro con datos incompletos o inválidos se responde `rechazado` "
        .'sin frenar el resto del lote — nunca un 422 para el lote completo.',
    required: ['tipo', 'uuid_cliente'],
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['trabajo', 'sesion'], example: 'trabajo'),
        new OA\Property(property: 'uuid_cliente', type: 'string', example: 'a1b2c3d4-0000-4000-8000-000000000001'),
        new OA\Property(property: 'orden_id', description: '`trabajo`: id de servidor de la orden (del pull de catálogo).', type: 'integer', example: 1),
        new OA\Property(property: 'lote_id', description: '`trabajo`: id de servidor del lote (del pull de catálogo).', type: 'integer', example: 3),
        new OA\Property(property: 'nro_aplicacion', description: '`trabajo`.', type: 'integer', example: 1),
        new OA\Property(
            property: 'trabajo_uuid_cliente',
            description: '`sesion`: `uuid_cliente` de su trabajo — nunca el id de servidor, que puede no existir '
                .'todavía si el trabajo llegó en este mismo lote.',
            type: 'string',
            example: 'a1b2c3d4-0000-4000-8000-000000000001',
        ),
        new OA\Property(property: 'secuencia', description: '`sesion`.', type: 'integer', example: 1),
        new OA\Property(property: 'piloto_id', description: '`sesion`: id de servidor de la persona (del pull de catálogo).', type: 'integer', example: 5),
        new OA\Property(property: 'auxiliar_id', description: '`sesion`, opcional.', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'hectareas_declaradas', description: 'DECIMAL como string (invariante 6). `0` si se omite.', type: 'string', example: '0'),
        new OA\Property(property: 'inicio', type: 'string', format: 'date-time', example: '2026-09-01T10:00:00-04:00'),
        new OA\Property(property: 'fin', type: 'string', format: 'date-time', nullable: true, example: null),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ResultadoSync',
    title: 'Resultado de aplicar un registro del lote',
    description: 'Uno por registro de entrada, en el mismo orden (espec §2.1, punto 3).',
    required: ['uuid_cliente', 'tipo', 'estado'],
    properties: [
        new OA\Property(property: 'uuid_cliente', type: 'string', nullable: true, example: 'a1b2c3d4-0000-4000-8000-000000000001'),
        new OA\Property(property: 'tipo', type: 'string', nullable: true, example: 'trabajo'),
        new OA\Property(property: 'estado', type: 'string', enum: ['aplicado', 'duplicado', 'rechazado'], example: 'aplicado'),
        new OA\Property(property: 'motivo', description: 'Presente solo cuando `estado` es `rechazado`.', type: 'string', example: 'el trabajo referenciado no existe todavía'),
    ],
    type: 'object',
)]
final class SyncController
{
    #[OA\Post(
        path: '/api/sync',
        operationId: 'sincronizarLote',
        description: 'Push en lote de la cola offline (espec §2.1). Cada registro se procesa en su propia '
            .'transacción —nunca el lote completo en una— y se responde con uno de tres estados: `aplicado`, '
            .'`duplicado` (reintento de un `uuid_cliente` ya aplicado, se trata como éxito) o `rechazado` con '
            .'motivo. El servidor agrupa por tipo y aplica siempre `trabajo` antes que `sesion`, sin importar '
            .'el orden del arreglo recibido, así que una `sesion` puede referenciar un `trabajo` del mismo '
            .'lote aunque venga antes en el arreglo.',
        summary: 'Push de sincronización en lote (trabajo, sesión)',
        security: [['tokenDispositivo' => []]],
        tags: ['Sincronizacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['registros'],
                properties: [
                    new OA\Property(
                        property: 'registros',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/RegistroSync'),
                    ),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resultado por registro, en el mismo orden del arreglo de entrada.',
                content: new OA\JsonContent(
                    required: ['resultados'],
                    properties: [
                        new OA\Property(
                            property: 'resultados',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ResultadoSync'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 422, description: 'Falta `registros`, o no es un arreglo.'),
        ],
    )]
    public function store(
        SincronizarLoteRequest $request,
        SincronizarLote $sincronizarLote,
        IdentidadOperarioToken $identidadOperario,
    ): JsonResponse {
        /** @var array<string, mixed> $datos */
        $datos = $request->validated();

        /** @var list<mixed> $registros */
        $registros = is_array($datos['registros']) ? $datos['registros'] : [];

        return response()->json([
            'resultados' => $sincronizarLote->ejecutar($registros, $identidadOperario->personaId($request)),
        ]);
    }
}
