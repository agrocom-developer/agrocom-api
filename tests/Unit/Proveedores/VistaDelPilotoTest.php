<?php

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\DronesController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\MiProveedorController;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El piloto de un proveedor (por ejemplo, el piloto propio de Agrocom) ve en solo lectura el
 * proveedor al que pertenece y los drones de sus equipos; no gestiona nada. Antes recibía 403
 * porque solo el titular tenía permiso. Un rol sin ninguno de los dos permisos sigue en 403 y
 * nadie ve el proveedor ni los drones de otro.
 */

uses(TestCase::class);

const PV_TITULAR = 301;
const PV_PILOTO = 302;
const PV_OTRO_PILOTO = 303;
const PV_CLIENTE = 304;

function pvMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_pilotos_table',
        'create_prv_auxiliares_table', 'create_prv_documentos_table', 'create_flo_drones_table', 'create_flo_equipos_table',
    ]);

    foreach (['flo_equipos', 'flo_drones', 'prv_documentos', 'prv_pilotos', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();
    DB::table('prv_proveedores')->insert([
        ['id' => 1, 'usuario_titular_id' => PV_TITULAR, 'tipo' => 'propio', 'razon_social' => 'Agrocom SRL', 'documento_fiscal' => '1018273029', 'telefono' => '+591 33512000', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 2, 'usuario_titular_id' => 999, 'tipo' => 'tercero', 'razon_social' => 'Otro Proveedor', 'documento_fiscal' => '2222', 'telefono' => null, 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
    DB::table('prv_pilotos')->insert([
        ['id' => 11, 'proveedor_id' => 1, 'usuario_id' => PV_PILOTO, 'licencia_numero' => 'RPA-1', 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 12, 'proveedor_id' => 2, 'usuario_id' => PV_OTRO_PILOTO, 'licencia_numero' => 'RPA-2', 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
    foreach ([[1, 1, 'AG-01', 'Agras T40'], [2, 1, 'AG-02', 'Agras T25'], [3, 2, 'XX-01', 'Dron ajeno']] as [$id, $proveedor, $serie, $modelo]) {
        DB::table('flo_drones')->insert(['id' => $id, 'dueno_proveedor_id' => $proveedor, 'operador_proveedor_id' => $proveedor, 'marca' => 'DJI', 'modelo' => $modelo, 'numero_serie' => $serie, 'capacidad_tanque_l' => '40.00', 'ancho_pasada_m' => '11.00', 'estado' => 'aprobado', 'origen' => 'flota_agrocom', 'created_at' => $ahora, 'updated_at' => $ahora]);
    }
    // El piloto 11 opera solo el dron 2; el dron 1 de Agrocom está en un equipo de otro piloto.
    DB::table('flo_equipos')->insert([
        ['id' => 21, 'proveedor_id' => 1, 'piloto_id' => 11, 'dron_id' => 2, 'vigente_desde' => '2026-01-01', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 22, 'proveedor_id' => 2, 'piloto_id' => 12, 'dron_id' => 3, 'vigente_desde' => '2026-01-01', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);

    app()->instance(LecturaEquipos::class, new class implements LecturaEquipos
    {
        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }

        public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
        {
            return match ($usuarioId) {
                PV_PILOTO => [21],
                PV_OTRO_PILOTO => [22],
                default => [],
            };
        }

        public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
        {
            return $proveedorId === 1;
        }

        public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }
    });
}

/** @param  list<string>  $permisos */
function pvComo(int $usuarioId, array $permisos): Request
{
    Auth::guard('sanctum')->setUser(new GenericUser(['id' => $usuarioId]));

    app()->instance(IdentidadOperarioToken::class, new class($permisos) implements IdentidadOperarioToken
    {
        /** @param  list<string>  $permisos */
        public function __construct(private readonly array $permisos) {}

        public function personaId(Request $request): ?int
        {
            return null;
        }

        public function tienePermiso(Request $request, string $codigo): bool
        {
            return in_array($codigo, $this->permisos, true);
        }
    });

    return Request::create('/api/proveedores/mi');
}

function pvMi(int $usuarioId, array $permisos): JsonResponse
{
    $peticion = pvComo($usuarioId, $permisos);

    return app(MiProveedorController::class)->show($peticion);
}

function pvDrones(int $usuarioId, array $permisos): JsonResponse
{
    $peticion = pvComo($usuarioId, $permisos);

    return app(DronesController::class)->index($peticion);
}

function pvEstatus(callable $accion): int
{
    try {
        $accion();
    } catch (HttpException $excepcion) {
        return $excepcion->getStatusCode();
    }

    return 200;
}

test('el piloto propio de Agrocom ve su proveedor en solo lectura, sin el documento fiscal', function () {
    pvMundo();
    $respuesta = pvMi(PV_PILOTO, ['proveedores.cuenta.ver']);
    $datos = json_decode((string) $respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($respuesta->getStatusCode())->toBe(200)
        ->and($datos['proveedor']['razon_social'])->toBe('Agrocom SRL')
        ->and($datos['proveedor']['tipo'])->toBe('propio')
        ->and($datos['proveedor']['documento_fiscal'])->toBeNull()
        ->and($datos['apto_para_ofertas'])->toBeTrue()
        ->and($datos['documentos_faltantes'])->toBeArray()
        ->and(json_encode($datos))->not->toContain('1018273029');
});

test('el titular sigue viendo su proveedor completo con el permiso de gestión', function () {
    pvMundo();
    $datos = json_decode((string) pvMi(PV_TITULAR, ['proveedores.cuenta.gestionar'])->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($datos['proveedor']['documento_fiscal'])->toBe('1018273029');
});

test('una cuenta sin permiso de proveedor recibe 403 y un piloto sin registro recibe 404', function () {
    pvMundo();

    expect(pvEstatus(fn () => pvMi(PV_CLIENTE, ['solicitudes.solicitud.gestionar'])))->toBe(403)
        ->and(pvEstatus(fn () => pvDrones(PV_CLIENTE, ['solicitudes.solicitud.gestionar'])))->toBe(403)
        ->and(pvEstatus(fn () => pvMi(PV_CLIENTE, ['proveedores.cuenta.ver'])))->toBe(404);
});

test('el piloto ve solo los drones de sus equipos y nunca los de otro proveedor', function () {
    pvMundo();

    $propios = json_decode((string) pvDrones(PV_PILOTO, ['proveedores.cuenta.ver'])->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
    $delOtro = json_decode((string) pvDrones(PV_OTRO_PILOTO, ['proveedores.cuenta.ver'])->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'];
    $titular = json_decode((string) pvDrones(PV_TITULAR, ['proveedores.cuenta.gestionar'])->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'];

    expect(array_column($propios, 'modelo'))->toBe(['Agras T25'])
        ->and(array_column($delOtro, 'modelo'))->toBe(['Dron ajeno'])
        ->and(array_column($titular, 'modelo'))->toBe(['Agras T40', 'Agras T25']);
});

test('el permiso de ver lo tiene el piloto y no el cliente', function () {
    $de = fn (string $lista): array => config('permisos_app.'.$lista);

    expect($de('piloto'))->toContain('proveedores.cuenta.ver')
        ->and($de('proveedor'))->not->toContain('proveedores.cuenta.ver')
        ->and($de('cliente'))->not->toContain('proveedores.cuenta.ver')
        ->and($de('piloto'))->not->toContain('proveedores.cuenta.gestionar');
});
