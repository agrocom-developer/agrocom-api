<?php

use App\Dominios\Compartido\Aplicacion\ConfiguracionDeCorreo;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use App\Dominios\Compartido\Infraestructura\Notificaciones\CorreoDePrueba;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Soporte\Esquema;

/*
 * Correo del sistema configurado desde el panel (Configuración > Correo): el SMTP de la base manda sobre
 * el `.env`, la contraseña de aplicación vive cifrada, la validación acota los valores, el correo de prueba
 * no filtra el mensaje del servidor y recuperar el acceso avisa cuando todavía no hay correo.
 */

const CLAVE_APLICACION = 'abcd efgh ijkl mnop';

/** @var list<string> */
function permisosCorreo(?array $nuevos = null): array
{
    static $permisos = [];

    if ($nuevos !== null) {
        $permisos = $nuevos;
    }

    return $permisos;
}

beforeEach(function () {
    Esquema::migrar(['create_plt_bitacoras_table', 'create_plt_configuraciones_table', 'create_sec_user_table', 'agrega_tipo_cuenta_a_sec_user', 'create_password_reset_tokens_table', 'create_sec_user_preferencia_table']);
    DB::table('plt_configuraciones')->delete();

    app()->instance(AutorizacionPanelWeb::class, new class implements AutorizacionPanelWeb
    {
        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return in_array($codigoPermiso, permisosCorreo(), true);
        }

        public function cascara(Request $request): array
        {
            return [];
        }

        public function personaId(Request $request): ?int
        {
            return null;
        }

        public function primerDestinoVisible(Request $request): string
        {
            return '/panel';
        }
    });
    permisosCorreo(['seguridad.configuracion.ver', 'seguridad.configuracion.editar']);

    app()->setLocale('es');
    $this->withoutMiddleware();
    $this->actingAs((new SecUser)->forceFill(['id' => 1, 'name' => 'Admin', 'email' => 'admin@correo.test']), 'interno');
});

function guardarCorreo(array $cambios = []): array
{
    return [
        'valores' => [
            'correo.proveedor' => 'gmail',
            'correo.host' => 'smtp.gmail.com',
            'correo.puerto' => '587',
            'correo.cifrado' => 'tls',
            'correo.usuario' => 'dronagro@gmail.com',
            'correo.password' => CLAVE_APLICACION,
            'correo.remitente' => 'dronagro@gmail.com',
            'correo.remitente_nombre' => 'DronAgro',
            ...$cambios,
        ],
    ];
}

it('sin servidor en la base no toca el mailer del .env', function () {
    config(['mail.default' => 'array']);
    $correo = app(ConfiguracionDeCorreo::class);

    $correo->aplicar();

    expect($correo->definidaEnBase())->toBeFalse()
        ->and(config('mail.default'))->toBe('array')
        ->and($correo->estaConfigurado())->toBeTrue();

    config(['mail.default' => 'log']);
    expect($correo->estaConfigurado())->toBeFalse();
});

it('guarda el correo, cifra la contraseña de aplicación y la aplica al mailer', function () {
    $this->post('/panel/configuracion/correo', guardarCorreo())->assertRedirect();

    $crudo = DB::table('plt_configuraciones')->where('clave', 'correo.password')->value('valor');
    expect($crudo)->not->toContain('abcd')->and($crudo)->not->toContain('mnop')
        ->and(Configuracion::query()->where('clave', 'correo.password')->first()->valor)->toBe(CLAVE_APLICACION);

    app(ConfiguracionDeCorreo::class)->aplicar();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.gmail.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.require_tls'))->toBeTrue()
        ->and(config('mail.mailers.smtp.password'))->toBe('abcdefghijklmnop')
        ->and(config('mail.from.name'))->toBe('DronAgro');
});

it('cifrado ssl usa smtps y ninguno desactiva STARTTLS', function () {
    $this->post('/panel/configuracion/correo', guardarCorreo(['correo.puerto' => '465', 'correo.cifrado' => 'ssl']))->assertRedirect();
    app(ConfiguracionDeCorreo::class)->aplicar();
    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps');

    $this->post('/panel/configuracion/correo', guardarCorreo(['correo.proveedor' => 'otro', 'correo.cifrado' => 'ninguno']))->assertRedirect();
    app(ConfiguracionDeCorreo::class)->aplicar();
    expect(config('mail.mailers.smtp.auto_tls'))->toBeFalse()->and(config('mail.mailers.smtp.require_tls'))->toBeFalse();
});

it('dejar la contraseña vacía conserva la guardada y borrar la elimina', function () {
    $this->post('/panel/configuracion/correo', guardarCorreo())->assertRedirect();
    $this->post('/panel/configuracion/correo', guardarCorreo(['correo.password' => '']))->assertRedirect();

    expect(Configuracion::query()->where('clave', 'correo.password')->first()->valor)->toBe(CLAVE_APLICACION);

    $this->post('/panel/configuracion/correo', [...guardarCorreo(['correo.password' => '']), 'borrar' => ['correo.password' => '1']])->assertRedirect();

    expect(Configuracion::query()->where('clave', 'correo.password')->first()->valor)->toBeNull();
});

it('valida proveedor, puerto, servidor y remitente', function (string $clave, string $valor) {
    $this->post('/panel/configuracion/correo', guardarCorreo([$clave => $valor]))->assertRedirect();

    // `assertSessionHasErrors` lee los puntos como niveles; la clave del catálogo es plana.
    expect(session('errors')->keys())->toContain("valores.{$clave}");

    expect(DB::table('plt_configuraciones')->count())->toBe(0);
})->with([
    'proveedor ajeno' => ['correo.proveedor', 'yahoo'],
    'puerto fuera de rango' => ['correo.puerto', '70000'],
    'puerto no numérico' => ['correo.puerto', 'abc'],
    'cifrado ajeno' => ['correo.cifrado', 'sslv3'],
    'servidor con espacios' => ['correo.host', 'smtp gmail.com'],
    'remitente inválido' => ['correo.remitente', 'no-es-correo'],
    'nombre con salto de línea' => ['correo.remitente_nombre', "Dron\nAgro"],
]);

it('el correo de prueba va al correo de quien pulsa y nunca muestra la contraseña', function () {
    Notification::fake();
    config(['mail.default' => 'array']);

    $this->post('/panel/configuracion/correo/prueba')
        ->assertRedirect(route('panel.configuracion.index', ['grupo' => 'correo']))
        ->assertSessionHas('correo_prueba.ok', true);

    Notification::assertSentOnDemand(CorreoDePrueba::class, fn ($n, $canales, $destino) => $destino->routes['mail'] === 'admin@correo.test');
});

it('un servidor que no responde da un error legible sin el mensaje del servidor ni la clave', function () {
    $this->post('/panel/configuracion/correo', guardarCorreo(['correo.host' => '127.0.0.1', 'correo.puerto' => '1', 'correo.cifrado' => 'ninguno']))->assertRedirect();

    $respuesta = $this->post('/panel/configuracion/correo/prueba');
    $mensaje = session('correo_prueba.mensaje');

    $respuesta->assertSessionHas('correo_prueba.ok', false);
    expect($mensaje)->toBe(__('configuracion.correo_prueba.error.conexion'))
        ->and($mensaje)->not->toContain('mnop')->and($mensaje)->not->toContain('127.0.0.1');
});

it('sin permiso de editar no guarda ni envía pruebas', function () {
    permisosCorreo(['seguridad.configuracion.ver']);

    $this->post('/panel/configuracion/correo', guardarCorreo())->assertForbidden();
    $this->post('/panel/configuracion/correo/prueba')->assertForbidden();
});

it('recuperar el acceso avisa si el correo no está configurado, igual para cualquier cuenta', function () {
    config(['mail.default' => 'log']);

    $this->post('/recuperar', ['email' => 'quien@sea.test'])
        ->assertRedirect(route('login.form'));

    expect(session('errors')->first('email'))->toBe(__('seguridad.recuperar.correo_no_configurado'));
});

it('recuperar el acceso responde el mismo mensaje exista o no la cuenta', function () {
    config(['mail.default' => 'array']);

    $this->post('/recuperar', ['email' => 'nadie@sea.test'])
        ->assertRedirect(route('login.form'))
        ->assertSessionHas('estado', __('seguridad.recuperar.estado_generico'));
});
