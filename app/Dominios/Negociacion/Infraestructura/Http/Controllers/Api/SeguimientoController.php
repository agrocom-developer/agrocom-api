<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Negociacion\Aplicacion\Presentacion;
use App\Dominios\Negociacion\Aplicacion\ReputacionDelProveedor;
use App\Dominios\Negociacion\Aplicacion\Seguimiento;
use App\Dominios\Negociacion\Aplicacion\UbicacionDelPiloto;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Avance;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\CerrarServicioRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\EnviarUbicacionRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\IniciarServicioRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\RegistrarAvanceRequest;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\SubirEvidenciaRequest;
use App\Dominios\Negociacion\Infraestructura\Http\RespuestaDeNegociacion;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La ejecución del servicio (`negociacion-contrato.md` §4 y §6): el piloto marca el inicio,
 * sube fotos y marca el avance, y cierra; el cliente ve el avance, las fotos y la última
 * ubicación del piloto. Los registros del piloto son idempotentes por `uuid_cliente`, para
 * que la app los encole en el outbox y los reintente sin duplicar. La ubicación no va por
 * el outbox: sin red, se omite.
 */
#[OA\Schema(
    schema: 'RegistroDeAvance',
    title: 'Registro de seguimiento',
    description: 'Un registro del piloto: `inicio`, `avance` o `cierre`. Nace en el dispositivo con su `uuid_cliente`, así que aplicarlo dos veces deja la base idéntica.',
    required: ['id', 'uuid_cliente', 'tipo', 'registrado_en'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
        new OA\Property(property: 'tipo', type: 'string', enum: ['inicio', 'avance', 'cierre']),
        new OA\Property(property: 'hectareas_ejecutadas', type: 'string', nullable: true, example: '40.0000'),
        new OA\Property(property: 'porcentaje', type: 'string', nullable: true, example: '50.00'),
        new OA\Property(property: 'nota', type: 'string', nullable: true),
        new OA\Property(property: 'registrado_en', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Tag(name: 'Seguimiento', description: 'Inicio, avance con fotos, cierre y ubicación del piloto. El piloto solo sube fotos y marca el avance; lo operativo se acuerda en persona.')]
final class SeguimientoController
{
    use ActuaDeLaCuenta;

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly Seguimiento $seguimiento,
        private readonly UbicacionDelPiloto $ubicacion,
        private readonly CancelarComoProveedor $cancelarComoProveedor,
        private readonly ReputacionDelProveedor $reputacion,
    ) {}

    #[OA\Post(
        path: '/api/solicitudes/{id}/inicio',
        operationId: 'iniciarServicio',
        description: 'El piloto marca el inicio: la solicitud `adjudicada` pasa a `en_ejecucion` (una sola vez) y se avisa al cliente. Idempotente por `uuid_cliente`. Solo la cuenta del equipo adjudicado.',
        summary: 'Marcar el inicio del servicio',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['uuid_cliente'], properties: [new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'), new OA\Property(property: 'secuencia', type: 'integer', nullable: true), new OA\Property(property: 'registrado_en', type: 'string', format: 'date-time', nullable: true)], type: 'object')),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Registro aplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 200, description: 'Duplicado: el registro ya estaba aplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es del equipo de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud no está `adjudicada`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
        ],
    )]
    public function inicio(IniciarServicioRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.registrar');
        $datos = $request->validated();

        return $this->registro(fn () => $this->seguimiento->iniciar($this->usuarioId(), $id, $datos));
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/avance',
        operationId: 'registrarAvance',
        description: 'Marca el avance con `hectareas_ejecutadas` o `porcentaje` (uno de los dos; el otro se deriva de las hectáreas solicitadas). No puede pasar de las hectáreas solicitadas más la tolerancia (5 %); un valor menor al último exige `motivo_correccion`. Idempotente por `uuid_cliente`.',
        summary: 'Marcar el avance',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['uuid_cliente'], properties: [
            new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
            new OA\Property(property: 'secuencia', type: 'integer', nullable: true),
            new OA\Property(property: 'hectareas_ejecutadas', type: 'string', nullable: true, example: '40'),
            new OA\Property(property: 'porcentaje', type: 'string', nullable: true, example: '50'),
            new OA\Property(property: 'nota', type: 'string', nullable: true),
            new OA\Property(property: 'motivo_correccion', type: 'string', nullable: true),
            new OA\Property(property: 'registrado_en', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object')),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Registro aplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 200, description: 'Duplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es del equipo de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud no está `en_ejecucion`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: '`hectareas_exceden_tolerancia` o `correccion_sin_motivo`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function avance(RegistrarAvanceRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.registrar');
        $datos = $request->validated();

        return $this->registro(fn () => $this->seguimiento->avanzar($this->usuarioId(), $id, $datos));
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/cierre',
        operationId: 'cerrarServicio',
        description: 'El piloto pide el cierre con las hectáreas realmente ejecutadas. Con las fotos mínimas ya subidas (`foto_avance` o `foto_final`) la solicitud pasa a `finalizada`, se congelan hectáreas ejecutadas y `monto_final` (hectáreas × precio acordado), se elimina la última ubicación y se avisa al cliente; si faltan fotos, finaliza al subir la que completa el mínimo, en el orden en que lleguen.',
        summary: 'Cerrar el servicio',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['uuid_cliente', 'hectareas_ejecutadas'], properties: [
            new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
            new OA\Property(property: 'hectareas_ejecutadas', type: 'string', example: '78.5'),
            new OA\Property(property: 'secuencia', type: 'integer', nullable: true),
            new OA\Property(property: 'nota', type: 'string', nullable: true),
            new OA\Property(property: 'registrado_en', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object')),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Registro aplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 200, description: 'Duplicado.', content: new OA\JsonContent(required: ['registro'], properties: [new OA\Property(property: 'registro', ref: '#/components/schemas/RegistroDeAvance')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es del equipo de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud no está `en_ejecucion`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: '`hectareas_exceden_tolerancia`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cierre(CerrarServicioRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.registrar');
        $datos = $request->validated();

        return $this->registro(fn () => $this->seguimiento->cerrar($this->usuarioId(), $id, $datos));
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/avance',
        operationId: 'verAvance',
        description: 'El seguimiento de una solicitud adjudicada, para el cliente y para el proveedor adjudicado: estado, porcentaje y hectáreas ejecutadas, monto estimado y final, registros del piloto y fotos de avance (con URL para pedirlas con el mismo token). Otra cuenta recibe 404.',
        summary: 'Ver el avance del servicio',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El seguimiento.', content: new OA\JsonContent(required: ['avance'], properties: [new OA\Property(property: 'avance', type: 'object')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve seguimiento.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
        ],
    )]
    public function mostrar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.ver');

        try {
            return response()->json(['avance' => $this->seguimiento->consultar($this->usuarioId(), $id)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/evidencias',
        operationId: 'subirEvidenciaDeSolicitud',
        description: 'Sube una foto de avance, final o de incidencia (multipart, menos de 300 KB: la app la comprime antes). Cola separada de los registros livianos. Idempotente por `uuid_cliente`. Puede subir el piloto del equipo adjudicado. El cierre pendiente se revisa al subirla.',
        summary: 'Subir una foto del servicio',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['uuid_cliente', 'tipo', 'archivo'], properties: [
            new OA\Property(property: 'uuid_cliente', type: 'string', format: 'uuid'),
            new OA\Property(property: 'tipo', type: 'string', enum: ['foto_avance', 'foto_final', 'foto_incidencia']),
            new OA\Property(property: 'avance_uuid_cliente', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
        ], type: 'object'))),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Foto guardada.', content: new OA\JsonContent(required: ['evidencia'], properties: [new OA\Property(property: 'evidencia', type: 'object')], type: 'object')),
            new OA\Response(response: 200, description: 'Duplicado.', content: new OA\JsonContent(required: ['evidencia'], properties: [new OA\Property(property: 'evidencia', type: 'object')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no sube evidencias.'),
            new OA\Response(response: 404, description: 'La solicitud no es del equipo de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud no está en ejecución.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Archivo inválido o de 300 KB o más.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function subirEvidencia(SubirEvidenciaRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.evidencia.subir');
        $datos = $request->validated();

        try {
            [$evidencia, $creada] = $this->seguimiento->subirEvidencia($this->usuarioId(), $id, $datos, $request->file('archivo'));
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['evidencia' => Presentacion::evidencia($evidencia)], $creada ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/evidencias/{id}/archivo',
        operationId: 'descargarEvidencia',
        description: 'El archivo de una foto de avance, para el cliente de la solicitud y para el proveedor adjudicado. Se sirve del disco privado, nunca por una URL pública.',
        summary: 'Descargar una foto del servicio',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La imagen.', content: new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no ve seguimiento.'),
            new OA\Response(response: 404, description: 'No existe o es de otra cuenta.'),
        ],
    )]
    public function archivo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.ver');

        try {
            [, $ruta] = $this->seguimiento->archivoDeEvidencia($this->usuarioId(), $id);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=3600']);
    }

    #[OA\Put(
        path: '/api/ubicacion',
        operationId: 'enviarUbicacion',
        description: 'La app del piloto envía su última posición (cada 30 s con el servicio abierto). Solo se acepta con la solicitud `en_ejecucion` y desde la cuenta del equipo; reemplaza la fila única de la solicitud. No va por el outbox: sin red, se omite. Se elimina al finalizar o cancelar y no se guarda en historial ni en logs.',
        summary: 'Enviar la ubicación del piloto',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['solicitud_id', 'latitud', 'longitud'], properties: [
            new OA\Property(property: 'solicitud_id', type: 'integer'),
            new OA\Property(property: 'latitud', type: 'number', example: -17.7833),
            new OA\Property(property: 'longitud', type: 'number', example: -63.1821),
            new OA\Property(property: 'precision_m', type: 'number', nullable: true),
            new OA\Property(property: 'capturado_en', type: 'string', format: 'date-time', nullable: true),
        ], type: 'object')),
        tags: ['Seguimiento'],
        responses: [
            new OA\Response(response: 204, description: 'Ubicación guardada.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra seguimiento.'),
            new OA\Response(response: 404, description: 'La solicitud no es del equipo de la cuenta.'),
            new OA\Response(response: 409, description: '`ubicacion_no_permitida`: la solicitud no está `en_ejecucion`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Coordenadas inválidas.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function enviarUbicacion(EnviarUbicacionRequest $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.seguimiento.registrar');
        $datos = $request->validated();

        try {
            $this->ubicacion->registrar($this->usuarioId(), (int) $datos['solicitud_id'], (float) $datos['latitud'], (float) $datos['longitud'], isset($datos['precision_m']) ? (float) $datos['precision_m'] : null, $datos['capturado_en'] ?? null);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/solicitudes/{id}/ubicacion-piloto',
        operationId: 'verUbicacionDelPiloto',
        description: 'La última ubicación de la app del piloto, solo para el cliente de la solicitud y solo mientras está `en_ejecucion`: un punto que se refresca, sin ruta ni tiempo estimado. `vigente` es falso si pasaron 5 minutos desde la captura. Sin ubicación responde 200 con `ubicacion: null`.',
        summary: 'Ver dónde está el piloto',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'La ubicación o `null`.', content: new OA\JsonContent(required: ['ubicacion'], properties: [new OA\Property(property: 'ubicacion', type: 'object', nullable: true)], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de cliente.'),
            new OA\Response(response: 404, description: 'No existe o es de otro cliente.'),
        ],
    )]
    public function verUbicacion(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.oferta.cliente');

        try {
            return response()->json(['ubicacion' => $this->ubicacion->delCliente($this->usuarioId(), $id)]);
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }
    }

    #[OA\Post(
        path: '/api/solicitudes/{id}/cancelar-proveedor',
        operationId: 'cancelarComoProveedor',
        description: 'El proveedor adjudicado devuelve la solicitud al mercado (vuelve a `abierta`) con motivo obligatorio. Libera al proveedor y al equipo, queda registrado en su tasa de cancelación y no puede volver a ofertar en esa solicitud. Sin penalidad en dinero.',
        summary: 'Cancelar un servicio adjudicado (proveedor)',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['motivo'], properties: [new OA\Property(property: 'motivo', type: 'string', example: 'El dron quedó en mantenimiento.')], type: 'object')),
        tags: ['Seguimiento'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Solicitud devuelta al mercado.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de proveedor.'),
            new OA\Response(response: 404, description: 'La solicitud no es del proveedor de la cuenta.'),
            new OA\Response(response: 409, description: 'La solicitud ya no está `adjudicada`.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorConflicto')),
            new OA\Response(response: 422, description: 'Falta el motivo.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cancelarProveedor(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.oferta.proveedor');

        try {
            $this->cancelarComoProveedor->ejecutar($this->usuarioId(), $id, (string) $request->input('motivo', ''));
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/reputacion',
        operationId: 'verReputacion',
        description: 'La reputación del proveedor de la cuenta: calificación y tasa de cancelación (cancelaciones tras adjudicar sobre servicios adjudicados, últimos 90 días). Sin sanciones ni tasa de aceptación: si una cifra pasa el umbral se alerta al panel y decide el administrador.',
        summary: 'Mi reputación',
        security: [['tokenDispositivo' => []]],
        tags: ['Seguimiento'],
        responses: [
            new OA\Response(response: 200, description: 'La reputación.', content: new OA\JsonContent(required: ['reputacion'], properties: [new OA\Property(property: 'reputacion', type: 'object')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no es de proveedor.'),
            new OA\Response(response: 404, description: 'La cuenta no pertenece a ningún proveedor.'),
        ],
    )]
    public function reputacion(Request $request): JsonResponse
    {
        $this->exigirPermiso($request, 'negociacion.oferta.proveedor');

        try {
            $r = $this->reputacion->deLaCuenta($this->usuarioId());
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['reputacion' => [
            'calificacion_promedio' => $r['proveedor']->calificacionPromedio,
            'calificaciones_conteo' => $r['proveedor']->calificacionesConteo,
            'tasa_cancelacion' => $r['tasa_cancelacion'],
            'adjudicadas' => $r['adjudicadas'],
            'canceladas' => $r['canceladas'],
            'alerta_cancelacion' => $r['alerta_cancelacion'],
        ]]);
    }

    /** @param  callable(): array{0: Avance, 1: bool}  $accion */
    private function registro(callable $accion): JsonResponse
    {
        try {
            [$registro, $creado] = $accion();
        } catch (NegociacionInvalida $excepcion) {
            return RespuestaDeNegociacion::de($excepcion);
        }

        return response()->json(['registro' => Presentacion::avance($registro)], $creado ? 201 : 200);
    }
}
