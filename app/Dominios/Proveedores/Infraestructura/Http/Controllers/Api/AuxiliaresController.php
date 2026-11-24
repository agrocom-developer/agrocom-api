<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Proveedores\Aplicacion\AlcanceDelSolicitante;
use App\Dominios\Proveedores\Aplicacion\ReenviarARevision;
use App\Dominios\Proveedores\Aplicacion\RegistrarAuxiliar;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RegistrarAuxiliarRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\AuxiliarResource;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Auxiliares que la cuenta puede gestionar: todos los de su proveedor si es el
 * titular; solo los que registró si es piloto (invariante 5: el alcance sale de
 * la cuenta autenticada).
 */
final class AuxiliaresController
{
    use Autorizado;

    private const PERMISO = 'proveedores.auxiliar.registrar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AlcanceDelSolicitante $alcance,
        private readonly LecturaCuentas $cuentas,
        private readonly RegistrarAuxiliar $registrar,
        private readonly ReenviarARevision $reenviar,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/auxiliares',
        operationId: 'listarAuxiliares',
        description: 'Auxiliares que la cuenta gestiona: todos los de su proveedor si es titular; los que registró si es piloto.',
        summary: 'Auxiliares que gestiono',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 200, description: 'Auxiliares.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Auxiliar'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra auxiliares.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        $usuarioId = $this->usuarioId($request);
        $proveedor = $this->alcance->proveedorDelTitular($usuarioId);

        $consulta = Auxiliar::query()->orderBy('id');

        if ($proveedor !== null) {
            $consulta->where('proveedor_id', $proveedor->id);
        } else {
            $pilotoId = Piloto::query()->where('usuario_id', $usuarioId)->value('id');
            $consulta->where('registrado_por_piloto_id', $pilotoId ?? 0);
        }

        $auxiliares = $consulta->get();
        $nombres = array_map(static fn ($cuenta): string => $cuenta->nombre, $this->cuentas->porIds($auxiliares->pluck('usuario_id')->all()));

        return response()->json([
            'data' => $auxiliares->map(fn (Auxiliar $auxiliar): array => AuxiliarResource::conNombres($auxiliar, $nombres))->all(),
        ]);
    }

    #[OA\Post(
        path: '/api/proveedores/auxiliares',
        operationId: 'registrarAuxiliar',
        description: 'Un piloto aprobado o el titular de un proveedor registra a un auxiliar: crea la cuenta (rol `auxiliar`) '
            .'y el auxiliar queda `pendiente` hasta que un administrador lo aprueba (ADR 0032, punto 2).',
        summary: 'Registra un auxiliar',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'username', 'password'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', example: 'Lucía Flores'),
                    new OA\Property(property: 'username', type: 'string', example: 'lucia.flores'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 201, description: 'Auxiliar creado `pendiente`.', content: new OA\JsonContent(required: ['auxiliar'], properties: [new OA\Property(property: 'auxiliar', ref: '#/components/schemas/Auxiliar')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra auxiliares.'),
            new OA\Response(response: 422, description: 'Datos inválidos, login o correo ya registrados, o la cuenta no puede registrar auxiliares todavía.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(RegistrarAuxiliarRequest $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        try {
            $auxiliar = $this->registrar->ejecutar($this->usuarioId($request), $request->validated());
        } catch (RegistroInvalido|CuentaDuplicada $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['auxiliar' => AuxiliarResource::conNombres($auxiliar, [$auxiliar->usuario_id => (string) $request->validated('nombre')])], 201);
    }

    #[OA\Post(
        path: '/api/proveedores/auxiliares/{id}/reenviar',
        operationId: 'reenviarAuxiliar',
        description: 'Un auxiliar rechazado, corregido, vuelve a la cola de revisión (`rechazado` → `pendiente`).',
        summary: 'Reenvía un auxiliar rechazado a revisión',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Auxiliar otra vez `pendiente`.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no registra auxiliares.'),
            new OA\Response(response: 404, description: 'El auxiliar no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'El auxiliar no está rechazado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function reenviar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        try {
            $this->reenviar->ejecutar($this->usuarioId($request), TipoSujeto::Auxiliar, $id);
        } catch (RegistroInvalido|TransicionDeAprobacionInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(status: 204);
    }
}
