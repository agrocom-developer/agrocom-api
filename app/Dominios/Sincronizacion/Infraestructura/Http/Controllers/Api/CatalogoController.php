<?php

namespace App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Sincronizacion\Aplicacion\ObtenerCatalogoDesdeCursor;
use App\Dominios\Sincronizacion\Infraestructura\Http\Requests\ObtenerCatalogoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Adaptador delgado (ADR 0008): valida la query string, invoca el caso de
 * uso y devuelve la respuesta — ninguna regla de negocio vive acá.
 */
#[OA\Tag(
    name: 'Sincronizacion',
    description: 'Pull de catálogo con cursor para la app de campo (espec §2.1, punto 6): personas operativas, '
        .'servicios asignados al equipo del operario (solicitudes aceptadas) y límites de condiciones de vuelo. '
        .'Recetas y productos de mezcla quedan fuera a propósito (ver docblock de '
        .'`ObtenerCatalogoDesdeCursor`, HU-78/tarea 94): el piloto transcribe el producto por '
        .'nombre en el propio evento `mezcla` de `POST /api/sync`, sin necesitar bajarlo antes.',
)]
#[OA\Schema(
    schema: 'PersonaCatalogo',
    title: 'Persona operativa (catálogo)',
    description: 'Persona vigente (no borrada) para el pull de catálogo (espec §4.2).',
    required: ['id', 'nombre', 'rol', 'base_id', 'activo', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'nombre', type: 'string', example: 'Piloto Uno'),
        new OA\Property(property: 'rol', type: 'string', example: 'piloto'),
        new OA\Property(property: 'base_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'activo', type: 'boolean', example: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-08-26T12:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TerrenoServicioCatalogo',
    title: 'Terreno de un servicio (catálogo)',
    description: 'El terreno sobre el que se ejecuta el servicio: dónde volar y qué cuidar. Hectáreas como decimal exacto en string (invariante 6).',
    required: ['nombre', 'hectareas', 'acceso_dificil'],
    properties: [
        new OA\Property(property: 'nombre', type: 'string', example: 'Lote Norte'),
        new OA\Property(property: 'hectareas', type: 'string', example: '120.5000'),
        new OA\Property(property: 'geometria', description: 'Polígono GeoJSON, o null si no está cargado.', type: 'object', nullable: true),
        new OA\Property(property: 'referencias_acceso', type: 'string', nullable: true),
        new OA\Property(property: 'colindantes_sensibles', description: 'Apiarios, cultivos orgánicos, viviendas.', type: 'string', nullable: true),
        new OA\Property(property: 'acceso_dificil', type: 'boolean', example: false),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ServicioAsignadoCatalogo',
    title: 'Servicio asignado (catálogo)',
    description: 'Solicitud de servicio aceptada que el equipo del operario lleva al lote (ADR 0037). Solo estados de ejecución: '
        .'`asignada`, `en_camino`, `en_ejecucion` o `suspendida_por_clima`. FILTRADO por el operario del token: solo los servicios de '
        .'los equipos de flota en los que su cuenta es piloto o auxiliar con vigencia HOY; una cuenta sin equipo recibe la lista vacía. '
        .'El `id` es el `solicitud_id` que la app envía en el registro `trabajo` de `POST /api/sync`. Sin datos de contacto del cliente. '
        .'Límite conocido del cursor: si el operario entra a un equipo nuevo, los servicios de ese equipo que no cambiaron desde el '
        .'último pull no llegan en el incremental; los trae un pull completo (`desde` vacío).',
    required: ['id', 'uuid_cliente', 'estado', 'equipo_id', 'tipo_aplicacion', 'hectareas_solicitadas', 'producto_indicado', 'aporta_producto', 'ventana_desde', 'ventana_hasta', 'urgente', 'terreno', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'uuid_cliente', type: 'string', example: '9a1b7e3e-2f7a-4b3d-8c1e-6f2a1d9c4b0a'),
        new OA\Property(property: 'estado', type: 'string', enum: ['asignada', 'en_camino', 'en_ejecucion', 'suspendida_por_clima'], example: 'asignada'),
        new OA\Property(property: 'equipo_id', type: 'integer', example: 7),
        new OA\Property(property: 'tipo_aplicacion', type: 'string', enum: ['herbicida', 'insecticida', 'fungicida', 'fertilizante_foliar', 'desecante', 'otro']),
        new OA\Property(property: 'hectareas_solicitadas', type: 'string', example: '80.0000'),
        new OA\Property(property: 'cultivo', type: 'string', nullable: true, example: 'Soya'),
        new OA\Property(property: 'producto_indicado', type: 'string', example: 'Glifosato 480 SL, 2 L/ha'),
        new OA\Property(property: 'aporta_producto', type: 'string', enum: ['cliente', 'proveedor']),
        new OA\Property(property: 'ventana_desde', type: 'string', format: 'date', example: '2026-12-01'),
        new OA\Property(property: 'ventana_hasta', type: 'string', format: 'date', example: '2026-12-10'),
        new OA\Property(property: 'urgente', type: 'boolean', example: false),
        new OA\Property(property: 'observaciones', type: 'string', nullable: true),
        new OA\Property(property: 'terreno', ref: '#/components/schemas/TerrenoServicioCatalogo'),
        new OA\Property(property: 'updated_at', description: 'Posición del cursor de esta sección.', type: 'string', format: 'date-time', example: '2026-12-01T12:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ServicioRetiradoCatalogo',
    title: 'Servicio retirado (catálogo)',
    description: 'Servicio que el equipo del operario tuvo y ya no debe llevar al lote. La app lo OCULTA, no lo borra: puede tener '
        .'trabajo capturado sin sincronizar. `motivo`: `cancelada`, `reasignada` (volvió a asignación: el proveedor canceló o soporte lo reasignó) '
        .'o `ejecutada` (cerrado por el servidor, a la espera de conformidad o terminado). Un servicio nunca viaja a la vez en '
        .'`servicios_asignados` y acá.',
    required: ['id', 'uuid_cliente', 'motivo', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 31),
        new OA\Property(property: 'uuid_cliente', type: 'string', example: '9a1b7e3e-2f7a-4b3d-8c1e-6f2a1d9c4b0a'),
        new OA\Property(property: 'motivo', type: 'string', enum: ['cancelada', 'reasignada', 'ejecutada'], example: 'cancelada'),
        new OA\Property(property: 'updated_at', description: 'Posición del cursor de esta sección.', type: 'string', format: 'date-time', example: '2026-12-02T11:00:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LimitesCondicionesCatalogo',
    title: 'Límites de condiciones de vuelo (catálogo)',
    description: 'Los límites con los que el servidor valida el registro `condiciones` de `POST /api/sync` (espec §9.2); '
        .'la app, que es offline, los muestra para que lo que ve y lo que se exige nunca se contradigan. DECIMAL como string (invariante 6). '
        .'`viento_max_kmh`, `temperatura_max_c` y `humedad_max_pct` siempre traen valor; los demás son `null` si el sistema no fija un límite, '
        .'y la app no debe aplicarlo.',
    required: ['viento_max_kmh', 'temperatura_max_c', 'humedad_max_pct'],
    properties: [
        new OA\Property(property: 'humedad_min_pct', type: 'string', nullable: true),
        new OA\Property(property: 'viento_max_kmh', type: 'string', example: '17.00'),
        new OA\Property(property: 'temperatura_max_c', type: 'string', example: '30.00'),
        new OA\Property(property: 'humedad_max_pct', type: 'string', example: '90.00'),
        new OA\Property(property: 'altura_vuelo_m', type: 'string', nullable: true),
        new OA\Property(property: 'velocidad_vuelo_kmh', type: 'string', nullable: true),
        new OA\Property(property: 'ancho_pasada_m', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class CatalogoController
{
    #[OA\Get(
        path: '/api/sync/catalogo',
        operationId: 'obtenerCatalogoSincronizacion',
        description: 'Baja el catálogo de personas, los servicios asignados al equipo del operario del token y los límites de '
            .'condiciones de vuelo, modificado desde la posición del cursor recibido, con paginación por cursor (`updated_at`, `id`) '
            .'— nunca por número de página, para no perder ni repetir registros entre pulls. `desde` vacío o ausente trae todo lo vigente '
            .'(primera sincronización). Un `desde` no decodificable se trata igual que vacío, nunca como error de validación. '
            .'Cada sección, `servicios_retirados` incluida, avanza su propia posición dentro del mismo cursor opaco y entrega como '
            .'máximo 200 filas por pull; no hay flag "hay más": la sección que llenó su página se completa en el pull siguiente con el '
            .'cursor devuelto. Con cursor vacío `servicios_retirados` viene vacía y su posición arranca en el momento del pull (un '
            .'retiro en ese mismo segundo puede llegar en el pull siguiente aunque el dispositivo nunca haya tenido la fila: se prefiere '
            .'repetir a perder). Un cursor anterior a esa sección la trae desde el principio, paginada.',
        summary: 'Pull de catálogo con cursor (personas, servicios asignados y retirados, límites)',
        security: [['tokenDispositivo' => []]],
        tags: ['Sincronizacion'],
        parameters: [
            new OA\Parameter(
                name: 'desde',
                description: 'Cursor opaco devuelto por el pull anterior. Ausente o vacío en la primera sincronización.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo modificado desde el cursor, y el cursor de continuación para el próximo pull.',
                content: new OA\JsonContent(
                    required: ['personas', 'servicios_asignados', 'servicios_retirados', 'limites', 'cursor'],
                    properties: [
                        new OA\Property(
                            property: 'personas',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/PersonaCatalogo'),
                        ),
                        new OA\Property(
                            property: 'servicios_asignados',
                            description: 'Solicitudes aceptadas que el equipo del operario lleva al lote, modificadas desde el cursor.',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ServicioAsignadoCatalogo'),
                        ),
                        new OA\Property(
                            property: 'servicios_retirados',
                            description: 'Servicios que el operario tuvo y ya no debe llevar, desde el cursor. Vacía en el primer pull (cursor vacío) y sin equipo.',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ServicioRetiradoCatalogo'),
                            example: [['id' => 31, 'uuid_cliente' => '9a1b7e3e-2f7a-4b3d-8c1e-6f2a1d9c4b0a', 'motivo' => 'cancelada', 'updated_at' => '2026-12-02T11:00:00+00:00']],
                        ),
                        new OA\Property(property: 'limites', ref: '#/components/schemas/LimitesCondicionesCatalogo'),
                        new OA\Property(
                            property: 'cursor',
                            description: 'Cursor opaco a reenviar tal cual en `desde` en el próximo pull.',
                            type: 'string',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
        ],
    )]
    public function index(
        ObtenerCatalogoRequest $request,
        ObtenerCatalogoDesdeCursor $obtenerCatalogo,
        IdentidadOperarioToken $identidadOperario,
    ): JsonResponse {
        /** @var array<string, mixed> $filtros */
        $filtros = $request->validated();

        return response()->json($obtenerCatalogo->ejecutar(
            isset($filtros['desde']) ? (string) $filtros['desde'] : null,
            $identidadOperario->personaId($request),
            Auth::guard('sanctum')->id() === null ? null : (int) Auth::guard('sanctum')->id(),
        ));
    }
}
