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
        .'Recorte de alcance de la tarea 09: solo `trabajo` y `sesion` — `mezcla`, `incidencia` y '
        .'`acta` llegan con sus propias tareas (ver docs/gestion/cola_tareas.md).',
)]
#[OA\Schema(
    schema: 'RegistroSync',
    title: 'Registro de entrada del lote de sync',
    description: 'Un elemento del arreglo `registros`. La forma exacta de los campos depende de `tipo`; '
        .'un registro con datos incompletos o inválidos se responde `rechazado` sin frenar el resto del '
        .'lote — nunca un 422 para el lote completo. `cierre_trabajo`/`cierre_sesion` (HU-05) MUTAN una '
        .'fila existente en vez de crear una nueva: `uuid_cliente` identifica el EVENTO de cierre — '
        .'distinto del `uuid_cliente` de apertura del trabajo/sesión que referencian. `recepcion_caldo` '
        .'(HU-10 redefinida por CR-01, tarea 18) registra volumen de caldo entregado por el cliente — '
        .'nunca su composición (espec §7.1: sin producto, dosis ni fórmula). `recarga` (HU-13, tarea 23) '
        .'registra cada ciclo de cambio de batería/recarga de caldo durante el vuelo — temperatura de '
        .'batería > 50°C se persiste con `alerta_temperatura = true` sin rechazar el registro; sin '
        .'`mezcla_id` (CR-01: Agrocom no prepara la mezcla).',
    required: ['tipo', 'uuid_cliente'],
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['trabajo', 'recepcion_caldo', 'sesion', 'condiciones', 'recarga', 'cierre_trabajo', 'cierre_sesion'], example: 'trabajo'),
        new OA\Property(property: 'uuid_cliente', type: 'string', example: 'a1b2c3d4-0000-4000-8000-000000000001'),
        new OA\Property(property: 'orden_id', description: '`trabajo`: id de servidor de la orden (del pull de catálogo).', type: 'integer', example: 1),
        new OA\Property(property: 'lote_id', description: '`trabajo`: id de servidor del lote (del pull de catálogo).', type: 'integer', example: 3),
        new OA\Property(property: 'nro_aplicacion', description: '`trabajo`.', type: 'integer', example: 1),
        new OA\Property(
            property: 'trabajo_uuid_cliente',
            description: '`sesion`/`cierre_trabajo`: `uuid_cliente` de apertura del trabajo — nunca el id de '
                .'servidor, que puede no existir todavía si el trabajo llegó en este mismo lote.',
            type: 'string',
            example: 'a1b2c3d4-0000-4000-8000-000000000001',
        ),
        new OA\Property(
            property: 'sesion_uuid_cliente',
            description: '`cierre_sesion`: `uuid_cliente` de apertura de la sesión a cerrar.',
            type: 'string',
            example: 'a1b2c3d4-0000-4000-8000-000000000002',
        ),
        new OA\Property(property: 'secuencia', description: '`sesion`: orden de apertura. `recarga`: orden de la recarga dentro de la sesión.', type: 'integer', example: 1),
        new OA\Property(property: 'piloto_id', description: '`sesion`: id de servidor de la persona (del pull de catálogo).', type: 'integer', example: 5),
        new OA\Property(property: 'auxiliar_id', description: '`sesion`, opcional.', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'dron_id', description: '`sesion`, opcional (HU-07): id de servidor del dron (catálogo mínimo de `Operaciones`, sin pull de catálogo propio todavía).', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'hectareas_declaradas', description: 'DECIMAL como string (invariante 6). `0` si se omite.', type: 'string', example: '0'),
        new OA\Property(property: 'hectarea_inicial_acumulada', description: '`sesion`, opcional (HU-07): acumulado de DJI al abrir la sesión, control de doble conteo (espec §5). DECIMAL como string.', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'inicio', type: 'string', format: 'date-time', example: '2026-09-01T10:00:00-04:00'),
        new OA\Property(property: 'fin', type: 'string', format: 'date-time', nullable: true, description: 'Requerido en `cierre_trabajo`/`cierre_sesion`.', example: null),
        new OA\Property(property: 'litros', description: '`recepcion_caldo`: litros entregados por el cliente. DECIMAL como string (invariante 6).', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'entregado_por', description: '`recepcion_caldo`: quién, del lado del cliente, entregó el caldo (espec §7.2).', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'hora', description: '`recepcion_caldo`: cuándo se entregó. `recarga`: cuándo ocurrió el ciclo de cambio de batería/recarga de caldo.', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'litros_consumidos', description: '`cierre_sesion`, opcional: litros de caldo efectivamente rociados en la sesión (espec §7.2). DECIMAL como string.', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'litros_sobrante', description: '`cierre_trabajo`, opcional: litros que quedaron sin aplicar al cerrar el trabajo (espec §7.2). DECIMAL como string.', type: 'string', nullable: true, example: null),
        new OA\Property(
            property: 'evidencia_imagen_campo_uuid_cliente',
            description: '`cierre_trabajo`: REQUERIDO — `uuid_cliente` de una evidencia ya subida por `POST /api/evidencias` con `tipo: imagen_campo` (espec §9/§10, HU-09). Sin ella, referenciando una evidencia inexistente o de otro tipo, el cierre se rechaza ("sin captura no cierra").',
            type: 'string',
            nullable: true,
            example: null,
        ),
        new OA\Property(
            property: 'motivo_cierre',
            description: '`cierre_sesion`: catálogo espec §4.3.',
            type: 'string',
            enum: ['completado', 'relevo_piloto', 'cambio_dron', 'falla_equipo', 'clima', 'fin_jornada', 'otro'],
            nullable: true,
            example: null,
        ),
        new OA\Property(
            property: 'momento',
            description: '`condiciones`: alcance de la tarea 17, solo `inicio_sesion` — `incidencia` es HU-08.',
            type: 'string',
            enum: ['inicio_sesion'],
            nullable: true,
            example: null,
        ),
        new OA\Property(property: 'viento_kmh', description: '`condiciones`.', type: 'string', example: '12.50'),
        new OA\Property(property: 'temperatura_c', description: '`condiciones`.', type: 'string', example: '24.00'),
        new OA\Property(property: 'humedad_pct', description: '`condiciones`.', type: 'string', example: '65.00'),
        new OA\Property(
            property: 'observacion_agronomo',
            description: '`condiciones`: obligatoria junto con `firma_observacion` cuando alguna medición cae fuera de rango (viento > 17 km/h, temperatura > 30°C o humedad > 90%) — sin ambas, el registro se rechaza.',
            type: 'string',
            nullable: true,
            example: null,
        ),
        new OA\Property(property: 'firma_observacion', description: '`condiciones`: sin evidencia real todavía (TE-07), texto plano.', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'litros_caldo', description: '`recarga`: litros de caldo cargados en ese ciclo. DECIMAL como string (invariante 6).', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'bateria_saliente_id', description: '`recarga`: identificador de la batería que se retira (texto libre, sin catálogo de baterías en el esquema).', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'temperatura_bateria_c', description: '`recarga`: temperatura medida de la batería saliente. > 50°C persiste con `alerta_temperatura = true`, sin rechazar el registro.', type: 'string', nullable: true, example: null),
        new OA\Property(
            property: 'motivo_retraso_caldo',
            description: '`recarga`, opcional: motivo del retraso o rechazo por calidad del caldo — solo se completa si hubo retraso, no en cada recarga.',
            type: 'string',
            enum: ['filtro_tapado', 'grumos', 'decantacion', 'espuma', 'color_olor_anormal'],
            nullable: true,
            example: null,
        ),
        new OA\Property(property: 'hora_retraso', description: '`recarga`, opcional: cuándo ocurrió el retraso/rechazo por caldo.', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'litros_combustible_generador', description: '`recarga`, opcional: litros de combustible cargados al generador en ese ciclo. Informativo, sin costeo (Fase 3). DECIMAL como string.', type: 'string', nullable: true, example: null),
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
            .'`duplicado` (reintento del mismo `uuid_cliente` ya aplicado, se trata como éxito) o `rechazado` con '
            .'motivo. El servidor agrupa por tipo y aplica siempre en el orden `trabajo`, `sesion`, '
            .'`cierre_trabajo`, `cierre_sesion`, sin importar el orden del arreglo recibido, así que un registro '
            .'puede referenciar otro del mismo lote aunque venga antes en el arreglo.',
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
