<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Proveedores\Aplicacion\AlcanceDelSolicitante;
use App\Dominios\Proveedores\Aplicacion\CargarDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\CargarDocumentoDeSujetoRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\ListarDocumentosRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\DocumentoResource;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Documentos de verificación de proveedor, piloto y auxiliar (el del dron lo
 * resuelve `Flota`). Se carga o lista solo lo que la cuenta gestiona (ver
 * {@see AlcanceDelSolicitante}): un sujeto ajeno es 404.
 */
final class DocumentosController
{
    use Autorizado;

    private const PERMISO = 'proveedores.documento.cargar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AlcanceDelSolicitante $alcance,
        private readonly CargarDocumento $cargar,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/documentos',
        operationId: 'listarDocumentosProveedor',
        description: 'Documentos de un sujeto que la cuenta gestiona, del más reciente al más antiguo, con su estado de revisión. '
            .'El archivo no se descarga por la API.',
        summary: 'Documentos de un sujeto',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        parameters: [
            new OA\Parameter(name: 'sujeto_tipo', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['proveedor', 'piloto', 'auxiliar'])),
            new OA\Parameter(name: 'sujeto_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Documentos.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DocumentoProveedor'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no carga documentos.'),
            new OA\Response(response: 404, description: 'El sujeto no existe o no es de esta cuenta.'),
        ],
    )]
    public function index(ListarDocumentosRequest $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        $datos = $request->validated();

        $tipo = TipoSujeto::from($datos['sujeto_tipo']);

        abort_unless($this->alcance->puedeGestionar($this->usuarioId($request), $tipo, (int) $datos['sujeto_id']), 404, __('proveedores.errores.sujeto_ajeno'));

        $documentos = Documento::query()
            ->where('sujeto_tipo', $tipo->value)
            ->where('sujeto_id', (int) $datos['sujeto_id'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => DocumentoResource::collection($documentos)->resolve($request)]);
    }

    #[OA\Post(
        path: '/api/proveedores/documentos',
        operationId: 'cargarDocumentoProveedor',
        description: 'Carga un documento (PDF, JPG o PNG, hasta 8 MB) de un proveedor, piloto o auxiliar que la cuenta gestiona. '
            .'Queda `en_revision` hasta que un administrador lo aprueba o lo rechaza con motivo. Licencia, seguro y habilitación '
            .'exigen fecha de vencimiento: el sistema avisa a los 30 y 7 días y suspende al sujeto si vence. Va como '
            .'`multipart/form-data`.',
        summary: 'Carga un documento de verificación',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['sujeto_tipo', 'sujeto_id', 'tipo', 'archivo'],
                    properties: [
                        new OA\Property(property: 'sujeto_tipo', type: 'string', enum: ['proveedor', 'piloto', 'auxiliar']),
                        new OA\Property(property: 'sujeto_id', type: 'integer', example: 5),
                        new OA\Property(property: 'tipo', type: 'string', enum: ['identidad', 'licencia_piloto', 'habilitacion_dron', 'seguro_responsabilidad', 'registro_dron', 'certificado_fumigacion', 'antecedentes', 'otro']),
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'vence_en', type: 'string', format: 'date', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 201, description: 'Documento cargado `en_revision`.', content: new OA\JsonContent(required: ['documento'], properties: [new OA\Property(property: 'documento', ref: '#/components/schemas/DocumentoProveedor')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no carga documentos.'),
            new OA\Response(response: 404, description: 'El sujeto no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'Archivo o datos inválidos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(CargarDocumentoDeSujetoRequest $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        $datos = $request->validated();
        $tipo = TipoSujeto::from($datos['sujeto_tipo']);
        $usuarioId = $this->usuarioId($request);

        abort_unless($this->alcance->puedeGestionar($usuarioId, $tipo, (int) $datos['sujeto_id']), 404, __('proveedores.errores.sujeto_ajeno'));

        try {
            $documento = $this->cargar->ejecutar(
                $tipo,
                (int) $datos['sujeto_id'],
                TipoDocumento::from($datos['tipo']),
                $request->file('archivo'),
                $datos['vence_en'] ?? null,
                $usuarioId,
            );
        } catch (RegistroInvalido $excepcion) {
            return RechazoDeDominio::respuesta($excepcion, 'vence_en');
        }

        return response()->json(['documento' => (new DocumentoResource($documento))->resolve($request)], 201);
    }
}
