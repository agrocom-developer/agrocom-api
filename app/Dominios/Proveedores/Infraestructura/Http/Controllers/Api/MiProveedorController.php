<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Aplicacion\AlcanceDelSolicitante;
use App\Dominios\Proveedores\Aplicacion\CambiarDisponibilidad;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Aplicacion\ReenviarARevision;
use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\CambiarDisponibilidadRequest;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\DisponibilidadResource;
use App\Dominios\Proveedores\Infraestructura\Http\Resources\ProveedorResource;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * El proveedor de la cuenta del token: su estado de aprobación, lo que le
 * falta para operar y si hoy puede recibir ofertas. El alcance sale del titular
 * autenticado, nunca de un id del request (invariante 5).
 */
final class MiProveedorController
{
    use Autorizado;

    private const PERMISO = 'proveedores.cuenta.gestionar';

    /** Solo lectura: el piloto de un proveedor ve a qué proveedor pertenece. */
    private const PERMISO_VER = 'proveedores.cuenta.ver';

    /** Cambiar el interruptor propio: lo tiene el piloto, y el titular lo cubre con `PERMISO`. */
    private const PERMISO_DISPONIBILIDAD = 'proveedores.disponibilidad.gestionar';

    public function __construct(
        private readonly IdentidadOperarioToken $identidad,
        private readonly AlcanceDelSolicitante $alcance,
        private readonly EstadoDocumentalDeSujeto $estadoDocumental,
        private readonly LecturaEquipos $equipos,
        private readonly ReenviarARevision $reenviar,
        private readonly CambiarDisponibilidad $disponibilidad,
    ) {}

    #[OA\Get(
        path: '/api/proveedores/mi',
        operationId: 'verMiProveedor',
        description: 'El proveedor del que la cuenta es titular: estado de aprobación (con el motivo si fue rechazado o '
            .'suspendido), los documentos obligatorios que aún no tienen uno aprobado y vigente, y si hoy puede '
            .'recibir ofertas (`apto_para_ofertas`: aprobado y con al menos un equipo apto). La app lo consulta para '
            .'mostrar «en revisión», «rechazado» o «listo para ponerte en línea». El piloto de un proveedor (por ejemplo, el '
            .'piloto propio de Agrocom) lo consulta en solo lectura: recibe el proveedor al que pertenece, sin el documento fiscal, '
            .'y `documentos_faltantes` son los suyos como piloto. `disponibilidad` es el interruptor de la propia cuenta '
            .'(el del proveedor para el titular, el suyo para el piloto, con quién y cuándo lo cambió) y '
            .'`disponibilidad_empresa` el de su proveedor.',
        summary: 'Mi proveedor y su estado de verificación',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil del proveedor.',
                content: new OA\JsonContent(
                    required: ['proveedor', 'documentos_faltantes', 'apto_para_ofertas', 'disponibilidad', 'disponibilidad_empresa'],
                    properties: [
                        new OA\Property(property: 'proveedor', ref: '#/components/schemas/Proveedor'),
                        new OA\Property(property: 'documentos_faltantes', description: 'Tipos de documento obligatorios del proveedor sin uno aprobado y vigente.', type: 'array', items: new OA\Items(type: 'string', example: 'seguro_responsabilidad')),
                        new OA\Property(property: 'apto_para_ofertas', type: 'boolean', example: false),
                        new OA\Property(property: 'disponibilidad', ref: '#/components/schemas/Disponibilidad'),
                        new OA\Property(property: 'disponibilidad_empresa', ref: '#/components/schemas/Disponibilidad'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona ni ve un proveedor.'),
            new OA\Response(response: 404, description: 'La cuenta no es titular ni piloto de ningún proveedor.'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        if (! $this->identidad->tienePermiso($request, self::PERMISO)) {
            return $this->verComoPiloto($request);
        }

        $proveedor = $this->alcance->proveedorDelTitular($this->usuarioId($request));

        abort_if($proveedor === null, 404, __('proveedores.errores.sin_proveedor'));

        $faltantes = array_map(
            static fn ($tipo): string => $tipo->value,
            $this->estadoDocumental->faltantesDe(TipoSujeto::Proveedor, $proveedor->id),
        );

        return response()->json([
            'proveedor' => new ProveedorResource($proveedor),
            'documentos_faltantes' => $faltantes,
            'apto_para_ofertas' => $this->equipos->tieneEquipoApto($proveedor->id),
            'disponibilidad' => DisponibilidadResource::de($proveedor),
            'disponibilidad_empresa' => DisponibilidadResource::de($proveedor),
        ]);
    }

    #[OA\Put(
        path: '/api/proveedores/mi/disponibilidad',
        operationId: 'cambiarMiDisponibilidad',
        description: 'Mueve el interruptor de la propia cuenta: el titular el de su proveedor (con `ocupado` o `en_trabajo` no se '
            .'avisa a ningún piloto suyo) y el piloto de una empresa el suyo. `ocupado` y `en_trabajo` dejan de recibir avisos de '
            .'nuevas solicitudes; la cuenta sigue viendo el mapa y puede ofertar. Al volver a `disponible` no llegan avisos '
            .'atrasados. Gana el último cambio; la fila guarda cuándo y quién lo hizo.',
        summary: 'Cambia mi disponibilidad',
        security: [['tokenDispositivo' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['disponibilidad'], properties: [new OA\Property(property: 'disponibilidad', type: 'string', enum: ['disponible', 'ocupado', 'en_trabajo'], example: 'en_trabajo')], type: 'object')),
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 200, description: 'El interruptor tras el cambio.', content: new OA\JsonContent(required: ['disponibilidad'], properties: [new OA\Property(property: 'disponibilidad', ref: '#/components/schemas/Disponibilidad')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona su disponibilidad.'),
            new OA\Response(response: 404, description: 'La cuenta no es titular ni piloto de ningún proveedor.'),
            new OA\Response(response: 422, description: 'Valor de disponibilidad inválido.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function cambiarDisponibilidad(CambiarDisponibilidadRequest $request): JsonResponse
    {
        abort_unless(
            $this->identidad->tienePermiso($request, self::PERMISO) || $this->identidad->tienePermiso($request, self::PERMISO_DISPONIBILIDAD),
            403,
            __('aprobacion.errores.sin_permiso'),
        );

        $registro = $this->disponibilidad->propia($this->usuarioId($request), Disponibilidad::from((string) $request->validated('disponibilidad')));

        abort_if($registro === null, 404, __('proveedores.errores.sin_proveedor'));

        return response()->json(['disponibilidad' => DisponibilidadResource::de($registro)]);
    }

    #[OA\Post(
        path: '/api/proveedores/mi/reenviar',
        operationId: 'reenviarMiProveedor',
        description: 'Tras un rechazo, el titular corrige lo que se le indicó (documentos, datos) y vuelve a la cola de '
            .'revisión: el proveedor pasa de `rechazado` a `pendiente`. Solo aplica a un proveedor rechazado.',
        summary: 'Reenvía mi proveedor rechazado a revisión',
        security: [['tokenDispositivo' => []]],
        tags: ['Proveedores'],
        responses: [
            new OA\Response(response: 200, description: 'Proveedor otra vez `pendiente`.', content: new OA\JsonContent(required: ['proveedor'], properties: [new OA\Property(property: 'proveedor', ref: '#/components/schemas/Proveedor')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o revocado.'),
            new OA\Response(response: 403, description: 'El rol del token no gestiona un proveedor.'),
            new OA\Response(response: 404, description: 'La cuenta no es titular de ningún proveedor.'),
            new OA\Response(response: 422, description: 'El proveedor no está rechazado.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')),
        ],
    )]
    public function reenviar(Request $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO);

        $proveedor = $this->alcance->proveedorDelTitular($this->usuarioId($request));

        abort_if($proveedor === null, 404, __('proveedores.errores.sin_proveedor'));

        try {
            $this->reenviar->ejecutar($this->usuarioId($request), TipoSujeto::Proveedor, $proveedor->id);
        } catch (RegistroInvalido|TransicionDeAprobacionInvalida $excepcion) {
            return RechazoDeDominio::respuesta($excepcion);
        }

        return response()->json(['proveedor' => new ProveedorResource($proveedor->refresh())]);
    }

    /** El piloto ve su proveedor en solo lectura; el alcance sale de su propio registro de piloto. */
    private function verComoPiloto(Request $request): JsonResponse
    {
        $this->exigirPermiso($this->identidad, $request, self::PERMISO_VER);

        $piloto = $this->alcance->pilotoDeLaCuenta($this->usuarioId($request));

        abort_if($piloto === null, 404, __('proveedores.errores.sin_proveedor'));

        $proveedor = $piloto->proveedor;
        $faltantes = array_map(
            static fn ($tipo): string => $tipo->value,
            $this->estadoDocumental->faltantesDe(TipoSujeto::Piloto, $piloto->id),
        );

        return response()->json([
            'proveedor' => [...(new ProveedorResource($proveedor))->resolve($request), 'documento_fiscal' => null],
            'documentos_faltantes' => $faltantes,
            'apto_para_ofertas' => $this->equipos->tieneEquipoApto($proveedor->id),
            'disponibilidad' => DisponibilidadResource::de($piloto),
            'disponibilidad_empresa' => DisponibilidadResource::de($proveedor),
        ]);
    }
}
