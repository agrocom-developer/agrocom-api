<?php

use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\MiProveedorController;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * `siguiente_paso` de `GET /api/proveedores/mi` guía el registro cortado antes del dron: documentos, dron, fotos del dron y
 * revisión. Lo piden el titular independiente y el tercero; Agrocom y el piloto de una empresa no.
 */

uses(TestCase::class);

const SD_TITULAR = 701;
const SD_PILOTO = 702;

/** Un proveedor pendiente con todos sus documentos aprobados (para llegar a los pasos del dron). */
function sdMundo(string $tipo = 'independiente', bool $documentos = true): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table', 'create_sec_user_table', 'create_prv_proveedores_table', 'create_prv_pilotos_table', 'create_prv_documentos_table',
        'create_flo_drones_table', 'create_flo_equipos_table', 'create_neg_tablas_de_negociacion',
    ]);

    foreach (['flo_dron_fotos', 'flo_equipos', 'flo_drones', 'prv_documentos', 'prv_pilotos', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $ahora = now();
    DB::table('prv_proveedores')->insert(['id' => 1, 'usuario_titular_id' => SD_TITULAR, 'tipo' => $tipo, 'razon_social' => 'Proveedor', 'documento_fiscal' => '1111', 'telefono' => null, 'estado' => 'pendiente', 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('prv_pilotos')->insert(['id' => 11, 'proveedor_id' => 1, 'usuario_id' => SD_PILOTO, 'licencia_numero' => 'RPA-1', 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => 'pendiente', 'created_at' => $ahora, 'updated_at' => $ahora]);

    if ($documentos) {
        foreach (['identidad', 'seguro_responsabilidad'] as $tipoDocumento) {
            sdDocumento('proveedor', 1, $tipoDocumento);
        }

        foreach (['identidad', 'licencia_piloto', 'antecedentes'] as $tipoDocumento) {
            sdDocumento('piloto', 11, $tipoDocumento);
        }
    }
}

function sdDocumento(string $sujeto, int $id, string $tipo): void
{
    DB::table('prv_documentos')->insert(['sujeto_tipo' => $sujeto, 'sujeto_id' => $id, 'tipo' => $tipo, 'archivo_ref' => 'x.pdf', 'mime' => 'application/pdf', 'bytes' => 10, 'estado' => 'aprobado', 'subido_por' => SD_TITULAR, 'created_at' => now(), 'updated_at' => now()]);
}

function sdDron(): void
{
    DB::table('flo_drones')->insert(['id' => 1, 'dueno_proveedor_id' => 1, 'operador_proveedor_id' => 1, 'marca' => 'DJI', 'modelo' => 'T40', 'numero_serie' => 'AG-01', 'capacidad_tanque_l' => '40.00', 'ancho_pasada_m' => '11.00', 'estado' => 'pendiente', 'origen' => 'externo', 'created_at' => now(), 'updated_at' => now()]);
}

function sdFoto(string $tipo, string $estado = 'en_revision'): void
{
    DB::table('flo_dron_fotos')->insert(['dron_id' => 1, 'tipo' => $tipo, 'archivo' => "{$tipo}.jpg", 'estado' => $estado, 'created_at' => now(), 'updated_at' => now()]);
}

/** @param  list<string>  $permisos */
function sdPaso(int $usuarioId, array $permisos): string
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

    $respuesta = app(MiProveedorController::class)->show(Request::create('/api/proveedores/mi'));

    return (string) json_decode((string) $respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR)['siguiente_paso'];
}

test('los documentos que faltan van antes que el dron', function () {
    sdMundo('independiente', documentos: false);

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('subir_documentos');
});

test('con los documentos al día y sin dron, el independiente debe registrar el dron', function () {
    sdMundo();

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('registrar_dron');
});

test('un dron sin las fotos obligatorias pide subirlas, también con solo algunas o con las rechazadas', function () {
    sdMundo();
    sdDron();

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('subir_fotos_dron');

    sdFoto('frontal');
    sdFoto('lateral');
    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('subir_fotos_dron');

    sdFoto('placa_serie', 'rechazada');
    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('subir_fotos_dron');
});

test('con las fotos obligatorias subidas, en revisión o aprobadas, espera la revisión', function () {
    sdMundo();
    sdDron();
    sdFoto('frontal');
    sdFoto('lateral', 'aprobada');
    sdFoto('placa_serie');

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('en_revision');
});

test('el tercero que opera drones recorre los mismos pasos', function () {
    sdMundo('tercero');

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('registrar_dron');
});

test('Agrocom, proveedor propio, no recibe pasos de dron', function () {
    sdMundo('propio');

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('en_revision');
});

test('el piloto de una empresa nunca recibe pasos de dron', function () {
    sdMundo('tercero');

    expect(sdPaso(SD_PILOTO, ['proveedores.cuenta.ver']))->toBe('en_revision');
});

test('un dron dado de baja no cuenta como dron registrado', function () {
    sdMundo();
    sdDron();
    DB::table('flo_drones')->update(['estado' => 'baja']);

    expect(sdPaso(SD_TITULAR, ['proveedores.cuenta.gestionar']))->toBe('registrar_dron');
});
