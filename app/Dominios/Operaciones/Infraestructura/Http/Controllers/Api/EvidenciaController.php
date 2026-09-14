<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api;

use App\Dominios\Operaciones\Aplicacion\RegistrarEvidencia;
use App\Dominios\Operaciones\Contratos\RegistroEvidencia;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\SubirEvidenciaRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;

/**
 * `POST /api/evidencias` (espec §2.1 punto 7, TE-07 parte servidor, tarea
 * 19): cola separada de evidencias, aparte del lote de `POST /api/sync` —
 * ver el docblock de la migración de `ope_evidencias` para el porqué de un
 * endpoint dedicado. Adaptador delgado (ADR 0008): valida el sobre, invoca
 * el caso de uso y traduce el resultado — ninguna regla de negocio vive acá.
 */
#[OA\Tag(
    name: 'Operaciones',
    description: 'Trabajos, sesiones, condiciones, recepción de caldo y evidencias de campo.',
)]
final class EvidenciaController
{
    public function __construct(private readonly RegistrarEvidencia $registrarEvidencia) {}

    #[OA\Post(
        path: '/api/evidencias',
        operationId: 'subirEvidencia',
        description: 'Sube un archivo de evidencia (captura de RC, foto de campo, foto de incidencia, '
            .'comprobante o firma de acta) y responde `aplicado`, `duplicado` (reintento del mismo '
            .'`uuid_cliente`, se trata como éxito) o `rechazado` con motivo — mismo vocabulario que '
            .'`POST /api/sync`, aunque este endpoint vive aparte porque su "sobre" lleva un binario. '
            .'El servidor SIEMPRE recalcula el hash SHA-256 sobre el contenido recibido (es el que queda '
            .'persistido); si el dispositivo declara `hash_dispositivo` (calculado al capturar, ADR 0009), '
            .'se compara contra ese cálculo y se rechaza si no coincide — detecta corrupción en tránsito, '
            .'sin confiar ciegamente en el valor declarado. La compresión a <300 KB y la cola de reintentos '
            .'son responsabilidad de la app de campo (TE-07 lado app, repo `agrocom-field`) — este endpoint '
            .'no las repite.',
        summary: 'Sube una evidencia (multipart/form-data)',
        security: [['tokenDispositivo' => []]],
        tags: ['Operaciones'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['uuid_cliente', 'tipo', 'fecha', 'archivo'],
                    properties: [
                        new OA\Property(property: 'uuid_cliente', type: 'string', example: 'a1b2c3d4-0000-4000-8000-000000000010'),
                        new OA\Property(property: 'tipo', type: 'string', enum: ['captura_rc', 'imagen_campo', 'foto_incidencia', 'comprobante', 'firma_acta', 'foto_control', 'foto_ciclo_bateria_balanceo', 'foto_dron_limpio'], example: 'imagen_campo'),
                        new OA\Property(property: 'fecha', type: 'string', format: 'date-time', example: '2026-09-01T10:00:00-04:00'),
                        new OA\Property(property: 'hash_dispositivo', description: 'SHA-256 calculado en el dispositivo al capturar (ADR 0009), opcional — si se declara, debe coincidir con el hash recalculado por el servidor.', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                    ],
                    type: 'object',
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resultado de aplicar el registro.',
                content: new OA\JsonContent(
                    required: ['uuid_cliente', 'estado'],
                    properties: [
                        new OA\Property(property: 'uuid_cliente', type: 'string', nullable: true, example: 'a1b2c3d4-0000-4000-8000-000000000010'),
                        new OA\Property(property: 'estado', type: 'string', enum: ['aplicado', 'duplicado', 'rechazado'], example: 'aplicado'),
                        new OA\Property(property: 'motivo', description: 'Presente solo cuando `estado` es `rechazado`.', type: 'string', example: 'archivo ausente o vacío'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente, revocado o ya sin rol válido.'),
            new OA\Response(response: 422, description: 'El archivo supera el tope de tamaño de plataforma (20 MB).'),
        ],
    )]
    public function store(SubirEvidenciaRequest $request, IdentidadOperarioToken $identidadOperario): JsonResponse
    {
        $uuidCliente = is_string($request->input('uuid_cliente')) ? $request->input('uuid_cliente') : null;

        $datos = RegistroEvidencia::intentarDesdeArreglo($request->only(['uuid_cliente', 'tipo', 'fecha', 'hash_dispositivo']));

        if ($datos === null) {
            return $this->respuesta($uuidCliente, ResultadoSincronizacion::rechazado('evidencia con datos incompletos o inválidos'));
        }

        $archivo = $request->file('archivo');
        $archivo = $archivo instanceof UploadedFile ? $archivo : null;

        $resultado = $this->registrarEvidencia->ejecutar(
            $datos,
            $archivo,
            $identidadOperario->personaId($request),
        );

        return $this->respuesta($datos->uuidCliente, $resultado);
    }

    private function respuesta(?string $uuidCliente, ResultadoSincronizacion $resultado): JsonResponse
    {
        $cuerpo = [
            'uuid_cliente' => $uuidCliente,
            'estado' => $resultado->estado,
        ];

        if ($resultado->motivo !== null) {
            $cuerpo['motivo'] = $resultado->motivo;
        }

        return response()->json($cuerpo);
    }
}
