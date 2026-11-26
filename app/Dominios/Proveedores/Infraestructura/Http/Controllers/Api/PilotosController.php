<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Proveedores\Aplicacion\AlcanceDelSolicitante;
use App\Dominios\Proveedores\Aplicacion\CambiarDisponibilidad;
use App\Dominios\Proveedores\Aplicacion\ReenviarARevision;
use App\Dominios\Proveedores\Aplicacion\RegistrarPiloto;
use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\CambiarDisponibilidadRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RegistrarPilotoRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\PilotoResource;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Pilotos del proveedor del token: el alcance sale del titular autenticado (invariante 5). */
final class PilotosController
{
    use Autorizado;

    private const PERMISO = 'proveedores.cuenta.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AlcanceDelSolicitante $alcance,
        private readonly LecturaCuentas $cuentas,
        private readonly RegistrarPiloto $registrar,
        private readonly ReenviarARevision $reenviar,
        private readonly CambiarDisponibilidad $disponibilidad,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/pilotos',
        operationId: 'listarPilotos',
        description: 'Pilotos del proveedor del que la cuenta es titular, con su estado de aprobación. Nunca lista los de otro proveedor.',
        summary: 'Pilotos de mi proveedor',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 200, description: 'Pilotos del proveedor.', content: new OA\JsonContent(required: ['data'], properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Piloto'))], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        $proveedor = $this->alcance->proveedorDelTitular($this->usuarioId($request));

        $pilotos = $proveedor === null
            ? collect()
            : Piloto::query()->where('proveedor_id', $proveedor->id)->orderBy('id')->get();

        $nombres = array_map(static fn ($cuenta): string => $cuenta->nombre, $this->cuentas->porIds($pilotos->pluck('usuario_id')->all()));

        return response()->json([
            'data' => $pilotos->map(fn (Piloto $piloto): array => PilotoResource::conNombres($piloto, $nombres))->all(),
        ]);
    }

    #[OA\Post(
        path: '/api/proveedores/pilotos',
        operationId: 'registrarPiloto',
        description: 'El titular de un proveedor `tercero` registra a un piloto suyo: crea la cuenta (rol `piloto`) y el piloto '
            .'queda `pendiente` hasta que un administrador lo aprueba. El proveedor `independiente` es su propio piloto y no '
            .'suma otros.',
        summary: 'Registra un piloto de mi proveedor',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre', 'username', 'password', 'licencia_numero', 'licencia_vence', 'categoria_habilitacion'],
                properties: [
                    new OA\Property(property: 'nombre', type: 'string', example: 'Marcelo Terrazas'),
                    new OA\Property(property: 'username', type: 'string', example: 'marcelo.terrazas'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'licencia_numero', type: 'string', example: 'RPA-2291'),
                    new OA\Property(property: 'licencia_vence', type: 'string', format: 'date', example: '2027-06-30'),
                    new OA\Property(property: 'categoria_habilitacion', type: 'string', example: 'RPAS agrícola'),
                ],
                type: 'object',
            ),
        ),
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 201, description: 'Piloto creado `pendiente`.', content: new OA\JsonContent(required: ['piloto'], properties: [new OA\Property(property: 'piloto', ref: '#/components/schemas/Piloto')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 422, description: 'Datos inválidos, login o correo ya registrados, o el proveedor no admite pilotos.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function store(RegistrarPilotoRequest $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        try {
            $piloto = $this->registrar->ejecutar($this->usuarioId($request), $request->validated());
        } catch (RegistroInvalido|CuentaDuplicada $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['piloto' => PilotoResource::conNombres($piloto, [$piloto->usuario_id => (string) $request->validated('nombre')])], 201);
    }

    #[OA\Post(
        path: '/api/proveedores/pilotos/{id}/reenviar',
        operationId: 'reenviarPiloto',
        description: 'Un piloto rechazado, corregido, vuelve a la cola de revisión (`rechazado` → `pendiente`). Lo puede reenviar el titular de su proveedor o el propio piloto.',
        summary: 'Reenvía un piloto rechazado a revisión',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Piloto otra vez `pendiente`.'),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El piloto no existe o no es de esta cuenta.'),
            new OA\Response(response: 422, description: 'El piloto no está rechazado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function reenviar(Request $request, int $id): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        try {
            $this->reenviar->ejecutar($this->usuarioId($request), TipoSujeto::Piloto, $id);
        } catch (RegistroInvalido|TransicionDeAprobacionInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(status: 204);
    }

    #[OA\Put(
        path: '/api/proveedores/pilotos/{id}/disponibilidad',
        operationId: 'cambiarDisponibilidadDePiloto',
        description: 'El encargado (titular del proveedor) marca a un piloto suyo como `disponible`, `ocupado` o `en_trabajo`. '
            .'Solo silencia o reactiva los avisos de ese piloto. Gana el último cambio entre el encargado y el piloto; la respuesta '
            .'dice quién lo cambió y la app del piloto lo muestra («Tu empresa te marcó como ocupado»). El piloto de otro proveedor es 404.',
        summary: 'Cambia la disponibilidad de un piloto de mi proveedor',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['disponibilidad'], properties: [new OA\Property(property: 'disponibilidad', type: 'string', enum: ['disponible', 'ocupado', 'en_trabajo'], example: 'ocupado')], type: 'object')),
        tags: ['Proveedores'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'El piloto con su interruptor tras el cambio.', content: new OA\JsonContent(required: ['piloto'], properties: [new OA\Property(property: 'piloto', ref: '#/components/schemas/Piloto')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'El piloto no existe o no es de este proveedor.'),
            new OA\Response(response: 422, description: 'Valor de disponibilidad inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cambiarDisponibilidad(CambiarDisponibilidadRequest $request, int $id): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        try {
            $piloto = $this->disponibilidad->deUnPiloto($this->usuarioId($request), $id, Disponibilidad::from((string) $request->validated('disponibilidad')));
        } catch (RegistroInvalido $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        $nombres = array_map(static fn ($cuenta): string => $cuenta->nombre, $this->cuentas->porIds([$piloto->usuario_id]));

        return response()->json(['piloto' => PilotoResource::conNombres($piloto, $nombres)]);
    }
}
