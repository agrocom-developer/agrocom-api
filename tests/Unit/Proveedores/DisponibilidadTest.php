<?php

use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\MiProveedorController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\PilotosController;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\CambiarDisponibilidadRequest;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Disponibilidad del proveedor (ADR 0045, punto 8): un interruptor manual por cuenta (`disponible`,
 * `ocupado`, `en_trabajo`), con fecha y autor. La propia cuenta lo cambia (el titular el del proveedor;
 * el piloto de una empresa el suyo) y el encargado puede marcar a un piloto suyo. Quien no está
 * disponible no recibe avisos de nuevas solicitudes (ni los pilotos de una empresa que se marca
 * ocupada), pero sigue viendo el mapa; y volver a «disponible» no genera avisos atrasados.
 *
 * Mundo: proveedor 1 (tercero, titular 401, pilotos 411 y 412); proveedor 2 (titular 402, piloto 421);
 * proveedor 3 (independiente, titular 403 que es a la vez piloto 431).
 */

uses(TestCase::class);

const DS_TITULAR = 401;
const DS_PILOTO = 411;
const DS_PILOTO_B = 412;
const DS_TITULAR_AJENO = 402;
const DS_PILOTO_AJENO = 421;
const DS_INDEPENDIENTE = 403;
const DS_CLIENTE = 499;

function dsMundo(): void
{
    Esquema::migrar([
        'create_sec_user_table', 'create_plt_bitacoras_table', 'create_sol_zonas_table', 'create_prv_proveedores_table', 'create_prv_pilotos_table',
        'create_prv_documentos_table', 'create_sec_user_preferencia_table', 'agrega_disponibilidad_y_filtros_de_proveedor',
    ]);

    foreach (['prv_documentos', 'prv_pilotos', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    Carbon::setTestNow(Carbon::parse('2026-12-10 09:00:00'));
    $ahora = now();

    DB::table('prv_proveedores')->insert([
        ['id' => 1, 'usuario_titular_id' => DS_TITULAR, 'tipo' => 'tercero', 'razon_social' => 'Empresa Uno', 'documento_fiscal' => '1', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 2, 'usuario_titular_id' => DS_TITULAR_AJENO, 'tipo' => 'tercero', 'razon_social' => 'Empresa Dos', 'documento_fiscal' => '2', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
        ['id' => 3, 'usuario_titular_id' => DS_INDEPENDIENTE, 'tipo' => 'independiente', 'razon_social' => 'Independiente', 'documento_fiscal' => '3', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);

    foreach ([[11, 1, DS_PILOTO], [12, 1, DS_PILOTO_B], [21, 2, DS_PILOTO_AJENO], [31, 3, DS_INDEPENDIENTE]] as [$id, $proveedor, $usuario]) {
        DB::table('prv_pilotos')->insert(['id' => $id, 'proveedor_id' => $proveedor, 'usuario_id' => $usuario, 'licencia_numero' => "RPA-{$id}", 'licencia_vence' => '2027-12-31', 'categoria_habilitacion' => 'RPAS', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    app()->instance(LecturaEquipos::class, new class implements LecturaEquipos
    {
        public function aptosDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }

        public function equiposDeCuenta(int $usuarioId, bool $soloVigentes = true, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }

        public function tieneEquipoApto(int $proveedorId, ?DateTimeImmutable $hoy = null): bool
        {
            return true;
        }

        public function vigentesDe(int $proveedorId, ?DateTimeImmutable $hoy = null): array
        {
            return [];
        }
    });
}

/** @param  list<string>  $permisos */
function dsComo(int $usuarioId, array $permisos): void
{
    app('auth')->forgetGuards();
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
}

/** Un `PUT` ya validado por el request real, como lo recibiría el controlador. */
function dsPeticion(mixed $valor): CambiarDisponibilidadRequest
{
    $peticion = CambiarDisponibilidadRequest::create('/api/proveedores/mi/disponibilidad', 'PUT', ['disponibilidad' => $valor]);
    $peticion->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

    return $peticion;
}

/** @param  list<string>  $permisos */
function dsMiCambio(int $usuarioId, array $permisos, mixed $valor): JsonResponse
{
    dsComo($usuarioId, $permisos);

    return app(MiProveedorController::class)->cambiarDisponibilidad(dsPeticion($valor));
}

function dsPilotoCambio(int $titularId, int $pilotoId, mixed $valor, array $permisos = ['proveedores.cuenta.gestionar']): JsonResponse
{
    dsComo($titularId, $permisos);

    return app(PilotosController::class)->cambiarDisponibilidad(dsPeticion($valor), $pilotoId);
}

/** @return array<string, mixed> */
function dsCuerpo(JsonResponse $respuesta): array
{
    return json_decode((string) $respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);
}

function dsEstatus(callable $accion): int
{
    try {
        $respuesta = $accion();

        return $respuesta instanceof JsonResponse ? $respuesta->getStatusCode() : 200;
    } catch (HttpException $excepcion) {
        return $excepcion->getStatusCode();
    } catch (ValidationException) {
        return 422;
    }
}

function dsFila(string $tabla, int $id): object
{
    return DB::table($tabla)->find($id);
}

/** @return list<int> */
function dsAvisables(int $proveedorId): array
{
    return app(LecturaProveedores::class)->usuariosParaAvisoDeNuevasSolicitudes($proveedorId);
}

test('todo proveedor y piloto nace disponible, sin fecha ni autor del cambio', function () {
    dsMundo();

    expect(dsFila('prv_proveedores', 1)->disponibilidad)->toBe('disponible')
        ->and(dsFila('prv_proveedores', 1)->disponibilidad_cambiada_en)->toBeNull()
        ->and(dsFila('prv_pilotos', 11)->disponibilidad)->toBe('disponible')
        ->and(dsFila('prv_pilotos', 11)->disponibilidad_cambiada_por)->toBeNull();
});

test('el titular cambia la disponibilidad de su proveedor y queda la fecha y el autor', function () {
    dsMundo();
    $respuesta = dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'ocupado');
    $disponibilidad = dsCuerpo($respuesta)['disponibilidad'];

    expect($respuesta->getStatusCode())->toBe(200)
        ->and($disponibilidad['valor'])->toBe('ocupado')
        ->and($disponibilidad['cambiada_por_usuario_id'])->toBe(DS_TITULAR)
        ->and($disponibilidad['cambiada_por_propia'])->toBeTrue()
        ->and($disponibilidad['cambiada_en'])->not->toBeNull()
        ->and(dsFila('prv_proveedores', 1)->disponibilidad)->toBe('ocupado')
        ->and((int) dsFila('prv_proveedores', 1)->disponibilidad_cambiada_por)->toBe(DS_TITULAR)
        // El piloto de la empresa no cambia de fila: su interruptor es suyo.
        ->and(dsFila('prv_pilotos', 11)->disponibilidad)->toBe('disponible')
        // La proveedor ajeno no se toca.
        ->and(dsFila('prv_proveedores', 2)->disponibilidad)->toBe('disponible');

    expect(dsCuerpo(dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'en_trabajo'))['disponibilidad']['valor'])->toBe('en_trabajo');
});

test('el piloto de una empresa cambia solo el suyo, con el permiso de disponibilidad propia', function () {
    dsMundo();
    $respuesta = dsMiCambio(DS_PILOTO, ['proveedores.disponibilidad.gestionar', 'proveedores.cuenta.ver'], 'ocupado');

    expect(dsCuerpo($respuesta)['disponibilidad']['cambiada_por_propia'])->toBeTrue()
        ->and(dsFila('prv_pilotos', 11)->disponibilidad)->toBe('ocupado')
        ->and((int) dsFila('prv_pilotos', 11)->disponibilidad_cambiada_por)->toBe(DS_PILOTO)
        ->and(dsFila('prv_pilotos', 12)->disponibilidad)->toBe('disponible')
        ->and(dsFila('prv_proveedores', 1)->disponibilidad)->toBe('disponible');
});

test('sin permiso es 403, una cuenta sin proveedor es 404 y un valor inválido es 422', function () {
    dsMundo();

    expect(dsEstatus(fn () => dsMiCambio(DS_CLIENTE, ['solicitudes.solicitud.gestionar'], 'ocupado')))->toBe(403)
        ->and(dsEstatus(fn () => dsMiCambio(DS_CLIENTE, ['proveedores.disponibilidad.gestionar'], 'ocupado')))->toBe(404)
        ->and(dsEstatus(fn () => dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'vacaciones')))->toBe(422)
        ->and(dsEstatus(fn () => dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], null)))->toBe(422)
        ->and(dsFila('prv_proveedores', 1)->disponibilidad)->toBe('disponible');
});

test('el encargado marca a un piloto suyo y la respuesta dice que lo cambió otra cuenta', function () {
    dsMundo();
    $respuesta = dsPilotoCambio(DS_TITULAR, 11, 'ocupado');
    $piloto = dsCuerpo($respuesta)['piloto'];

    expect($respuesta->getStatusCode())->toBe(200)
        ->and($piloto['id'])->toBe(11)
        ->and($piloto['disponibilidad']['valor'])->toBe('ocupado')
        ->and($piloto['disponibilidad']['cambiada_por_usuario_id'])->toBe(DS_TITULAR)
        ->and($piloto['disponibilidad']['cambiada_por_propia'])->toBeFalse()
        ->and(dsFila('prv_pilotos', 11)->disponibilidad)->toBe('ocupado')
        ->and(dsFila('prv_pilotos', 12)->disponibilidad)->toBe('disponible');
});

test('un piloto de otro proveedor es 404 y quien no gestiona un proveedor es 403', function () {
    dsMundo();

    expect(dsEstatus(fn () => dsPilotoCambio(DS_TITULAR, 21, 'ocupado')))->toBe(404)
        ->and(dsEstatus(fn () => dsPilotoCambio(DS_TITULAR, 9999, 'ocupado')))->toBe(404)
        ->and(dsEstatus(fn () => dsPilotoCambio(DS_PILOTO, 12, 'ocupado', ['proveedores.disponibilidad.gestionar', 'proveedores.cuenta.ver'])))->toBe(403)
        ->and(dsEstatus(fn () => dsPilotoCambio(DS_CLIENTE, 11, 'ocupado')))->toBe(404)
        ->and(dsEstatus(fn () => dsPilotoCambio(DS_TITULAR, 11, 'quizas')))->toBe(422)
        ->and(dsFila('prv_pilotos', 21)->disponibilidad)->toBe('disponible')
        ->and(dsFila('prv_pilotos', 11)->disponibilidad)->toBe('disponible');
});

test('gana el último cambio entre el encargado y el piloto, y GET /proveedores/mi dice quién lo hizo', function () {
    dsMundo();
    dsPilotoCambio(DS_TITULAR, 11, 'ocupado');

    dsComo(DS_PILOTO, ['proveedores.cuenta.ver']);
    $vista = dsCuerpo(app(MiProveedorController::class)->show(Request::create('/api/proveedores/mi')));

    expect($vista['disponibilidad']['valor'])->toBe('ocupado')
        ->and($vista['disponibilidad']['cambiada_por_propia'])->toBeFalse()
        ->and($vista['disponibilidad_empresa']['valor'])->toBe('disponible');

    dsMiCambio(DS_PILOTO, ['proveedores.disponibilidad.gestionar'], 'disponible');
    dsComo(DS_PILOTO, ['proveedores.cuenta.ver']);
    $despues = dsCuerpo(app(MiProveedorController::class)->show(Request::create('/api/proveedores/mi')));

    expect($despues['disponibilidad']['valor'])->toBe('disponible')
        ->and($despues['disponibilidad']['cambiada_por_propia'])->toBeTrue();

    dsComo(DS_TITULAR, ['proveedores.cuenta.gestionar']);
    $titular = dsCuerpo(app(MiProveedorController::class)->show(Request::create('/api/proveedores/mi')));

    expect($titular['proveedor']['disponibilidad']['valor'])->toBe('disponible')
        ->and($titular['disponibilidad']['cambiada_por_usuario_id'])->toBeNull();
});

test('a las cuentas no disponibles no se les avisa de nuevas solicitudes; a las disponibles sí', function () {
    dsMundo();

    expect(dsAvisables(1))->toBe([DS_TITULAR, DS_PILOTO, DS_PILOTO_B]);

    // Un piloto ocupado silencia solo al piloto.
    dsPilotoCambio(DS_TITULAR, 11, 'ocupado');
    expect(dsAvisables(1))->toBe([DS_TITULAR, DS_PILOTO_B]);

    // «En trabajo» tiene el mismo efecto que «ocupado».
    dsMiCambio(DS_PILOTO_B, ['proveedores.disponibilidad.gestionar'], 'en_trabajo');
    expect(dsAvisables(1))->toBe([DS_TITULAR]);

    // La empresa ocupada no avisa a ningún piloto suyo, aunque alguno esté disponible.
    dsPilotoCambio(DS_TITULAR, 11, 'disponible');
    dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'ocupado');
    expect(dsAvisables(1))->toBe([])
        ->and(dsAvisables(2))->toBe([DS_TITULAR_AJENO, DS_PILOTO_AJENO]);

    // Los asuntos del proveedor (ofertas, mensajes) siguen llegando a todos: el interruptor solo calla las nuevas solicitudes.
    expect(app(LecturaProveedores::class)->usuariosDeAviso(1))->toBe([DS_TITULAR, DS_PILOTO, DS_PILOTO_B]);
});

test('el independiente, que es a la vez titular y piloto, se rige por el interruptor del proveedor', function () {
    dsMundo();

    expect(dsAvisables(3))->toBe([DS_INDEPENDIENTE]);

    // El de su fila de piloto no cuenta: manda el del proveedor.
    DB::table('prv_pilotos')->where('id', 31)->update(['disponibilidad' => 'ocupado']);
    expect(dsAvisables(3))->toBe([DS_INDEPENDIENTE]);

    dsMiCambio(DS_INDEPENDIENTE, ['proveedores.cuenta.gestionar'], 'ocupado');
    expect(dsAvisables(3))->toBe([]);
});

test('volver a «disponible» no genera avisos atrasados: cambiar el interruptor no emite ningún aviso', function () {
    dsMundo();
    Event::fake([AvisoDeNegociacion::class]);

    dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'ocupado');
    dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'disponible');
    dsPilotoCambio(DS_TITULAR, 11, 'ocupado');
    dsPilotoCambio(DS_TITULAR, 11, 'disponible');

    Event::assertNotDispatched(AvisoDeNegociacion::class);
    expect(dsAvisables(1))->toBe([DS_TITULAR, DS_PILOTO, DS_PILOTO_B]);
});

test('el cambio queda en la bitácora de auditoría', function () {
    dsMundo();
    dsMiCambio(DS_TITULAR, ['proveedores.cuenta.gestionar'], 'ocupado');

    $registro = DB::table('plt_bitacoras')->where('tabla', 'prv_proveedores')->where('registro_id', 1)->latest('id')->first();

    expect($registro)->not->toBeNull()
        ->and(json_decode((string) $registro->despues, true)['disponibilidad'])->toBe('ocupado')
        ->and(json_decode((string) $registro->antes, true)['disponibilidad'])->toBe('disponible');
});

test('el permiso de cambiar la propia disponibilidad lo tienen el proveedor y el piloto, no el cliente', function () {
    $catalogo = new ReflectionClass(SeguridadSeeder::class);
    $de = fn (string $constante): array => $catalogo->getReflectionConstant($constante)->getValue();

    expect($de('PERMISOS_PILOTO'))->toContain('proveedores.disponibilidad.gestionar')
        ->and($de('PERMISOS_PROVEEDOR'))->toContain('proveedores.disponibilidad.gestionar')
        ->and($de('PERMISOS_CLIENTE'))->not->toContain('proveedores.disponibilidad.gestionar')
        ->and($catalogo->getReflectionConstant('PERMISOS')->getValue())->toHaveKey('proveedores.disponibilidad.gestionar');
});
