<?php

use App\Dominios\Compartido\Dominio\Excepciones\EscrituraEnModoSoloLectura;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\ResolverVistaComo;
use App\Dominios\Seguridad\Aplicacion\TerminarVistaComo;
use App\Dominios\Seguridad\Dominio\Excepciones\EscrituraEnVistaComo;
use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use App\Dominios\Seguridad\Infraestructura\Http\AutorizacionPortalClienteSesion;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\AplicarVistaComo;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/*
 * Tarea 140 — "ver como otro usuario" (crítica: toca el aislamiento del portal,
 * invariante 5 de CLAUDE.md). Reglas que NO necesitan base de datos ni app:
 *
 *   1. La bandera de sesión: solo se acepta lo que el propio sistema escribe.
 *   2. Solo lectura: con una vista abierta, todo método que escribe se rechaza
 *      ANTES de llegar a ningún controlador — no se esconde un botón, se rechaza.
 *   3. Defensa en profundidad: con el modo de solo lectura activo, un modelo de
 *      dominio no se guarda, no se borra y no se restaura.
 *   4. El portal solo conoce UNA fuente de contrato: la cuenta del guard
 *      `cliente`. La vista como cliente sustituye esa cuenta, no agrega un `where`.
 *   5. El permiso `ver_como` se siembra solo al administrador de plataforma, y el
 *      esquema y el enum del motivo no se desincronizan.
 *
 * Lo que necesita base de datos o el enrutador real (entrada y salida en la
 * bitácora, quién puede abrir una vista, revalidación en cada request, el portal
 * viendo SOLO su contrato, ninguna ruta con sesión fuera del middleware) vive en
 * `VistaComoAislamientoTest`, contra el esquema real en SQLite en memoria.
 */

$raizProyecto = dirname(__DIR__, 2);

/** Solicitud web con una sesión en memoria y, opcionalmente, una ruta con nombre. */
function vistaComoSolicitud(string $metodo, array $sesion = [], ?string $nombreRuta = null, array $cuerpo = []): Request
{
    $solicitud = Request::create('/panel/usuarios/1/ver-como', $metodo, $cuerpo);

    $almacen = new Store('prueba', new ArraySessionHandler(120));
    $almacen->start();

    foreach ($sesion as $clave => $valor) {
        $almacen->put($clave, $valor);
    }

    $solicitud->setLaravelSession($almacen);

    if ($nombreRuta !== null) {
        $ruta = (new Route([$metodo], '/x', static fn () => null))->name($nombreRuta);
        $solicitud->setRouteResolver(static fn () => $ruta);
    }

    return $solicitud;
}

function vistaComoMiddleware(): AplicarVistaComo
{
    $elegirRol = new ElegirRolActivo;

    return new AplicarVistaComo(new ResolverVistaComo, new TerminarVistaComo($elegirRol), $elegirRol);
}

/** @return array<string, int|string|null> lo que `IniciarVistaComo` escribe en la sesión */
function vistaComoBandera(TipoUsuario $tipo = TipoUsuario::Interno): array
{
    return (new VistaComoActiva(7, $tipo, 1, 5, 4, $tipo === TipoUsuario::Interno ? 3 : null))->paraSesion();
}

/** Cuenta cuántas veces se llegó al "controlador" detrás del middleware. */
function vistaComoSiguiente(int &$llamadas): Closure
{
    return static function (Request $solicitud) use (&$llamadas): Response {
        $llamadas++;

        return new Response('llegó al controlador');
    };
}

afterEach(function () {
    ModoSoloLectura::desactivar();
    Model::unsetEventDispatcher();
    Model::clearBootedModels();
});

// --- 1. La bandera de sesión ---------------------------------------------------

it('la bandera de sesión se lee tal como se escribió', function (TipoUsuario $tipo) {
    $vista = new VistaComoActiva(7, $tipo, 1, 5, 4, $tipo === TipoUsuario::Interno ? 3 : null);

    $leida = VistaComoActiva::desdeSesion($vista->paraSesion());

    expect($leida)->toEqual($vista)
        ->and($leida?->guard())->toBe($tipo->value);
})->with([
    'cuenta interna' => [TipoUsuario::Interno],
    'cuenta de portal' => [TipoUsuario::Cliente],
]);

it('una bandera mal formada equivale a «no hay vista», nunca a una vista a medias', function (mixed $valor) {
    expect(VistaComoActiva::desdeSesion($valor))->toBeNull();
})->with([
    'nada' => [null],
    'una cadena' => ['interno'],
    'un arreglo vacío' => [[]],
    'sin el administrador' => [array_diff_key(vistaComoBandera(), ['admin_id' => 1])],
    'ids como texto' => [[...vistaComoBandera(), 'usuario_id' => '4']],
    'tipo desconocido' => [[...vistaComoBandera(), 'tipo' => 'root']],
    'una cuenta interna sin rol' => [[...vistaComoBandera(), 'rol_id' => null]],
    'una cuenta de portal con rol' => [[...vistaComoBandera(TipoUsuario::Cliente), 'rol_id' => 3]],
]);

// --- 2. Solo lectura: se rechaza, no solo se oculta ---------------------------------

it('con una vista abierta rechaza todo método que escribe, sin llegar al controlador', function (string $metodo) {
    $llamadas = 0;
    $solicitud = vistaComoSolicitud($metodo, [VistaComoActiva::CLAVE_SESION => vistaComoBandera()]);

    expect(fn () => vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas)))
        ->toThrow(EscrituraEnVistaComo::class);

    expect($llamadas)->toBe(0);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('lo rechaza igual con la vista de portal', function (string $metodo) {
    $llamadas = 0;
    $solicitud = vistaComoSolicitud($metodo, [VistaComoActiva::CLAVE_SESION => vistaComoBandera(TipoUsuario::Cliente)]);

    expect(fn () => vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas)))
        ->toThrow(EscrituraEnVistaComo::class);

    expect($llamadas)->toBe(0);
})->with(['POST', 'PUT', 'DELETE']);

it('rechaza una conexión POST aunque el framework la resuelva como GET', function () {
    // Symfony ya no admite disfrazar un POST de GET (deprecado desde 7.4): se
    // simula el caso con una solicitud cuyo método real es POST y cuyo método
    // resuelto es GET, para que el rechazo no dependa de esa deprecación.
    $disfrazada = new class extends Request
    {
        public function getRealMethod(): string
        {
            return 'POST';
        }
    };

    $llamadas = 0;
    $solicitud = $disfrazada::create('/panel/usuarios/1/ver-como', 'GET');

    $almacen = new Store('prueba', new ArraySessionHandler(120));
    $almacen->start();
    $almacen->put(VistaComoActiva::CLAVE_SESION, vistaComoBandera());
    $solicitud->setLaravelSession($almacen);

    expect($solicitud->getMethod())->toBe('GET')
        ->and($solicitud->getRealMethod())->toBe('POST');

    expect(fn () => vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas)))
        ->toThrow(EscrituraEnVistaComo::class);

    expect($llamadas)->toBe(0);
});

it('rechaza también un PUT o un DELETE que llega como POST con _method', function (string $simulado) {
    Request::enableHttpMethodParameterOverride();

    $llamadas = 0;
    $solicitud = vistaComoSolicitud('POST', [VistaComoActiva::CLAVE_SESION => vistaComoBandera()], null, ['_method' => $simulado]);

    expect(fn () => vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas)))
        ->toThrow(EscrituraEnVistaComo::class);

    expect($llamadas)->toBe(0);
})->with(['PUT', 'PATCH', 'DELETE']);

it('la ruta de salida es la única escritura que deja pasar, y no sustituye a nadie', function () {
    $llamadas = 0;
    $solicitud = vistaComoSolicitud('POST', [VistaComoActiva::CLAVE_SESION => vistaComoBandera()], AplicarVistaComo::RUTA_SALIDA);

    $respuesta = vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas));

    // Sin base ni guards: llegó al controlador sin tocar nada (no revalidó, no sustituyó).
    expect($llamadas)->toBe(1)
        ->and($respuesta->getContent())->toBe('llegó al controlador');
});

it('sin vista abierta el middleware no interviene, escriba o lea', function (string $metodo) {
    $llamadas = 0;

    vistaComoMiddleware()->handle(vistaComoSolicitud($metodo), vistaComoSiguiente($llamadas));

    expect($llamadas)->toBe(1);
})->with(['GET', 'POST', 'PUT', 'DELETE']);

it('una bandera ilegible no bloquea la lectura: se descarta y el request sigue', function () {
    $llamadas = 0;
    $solicitud = vistaComoSolicitud('GET', [VistaComoActiva::CLAVE_SESION => ['basura' => true], 'sec_rol_activo_id' => 3]);

    // El rol activo se olvida por la fachada de sesión: se le da la de este request.
    $contenedor = new Container;
    $contenedor->instance('session', $solicitud->session());
    // Sin esto, una fachada `Session` ya resuelta por un test que levantó la app seguiría apuntando a otra sesión.
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($contenedor);

    vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas));

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);

    // La clave del rol activo puede traer el de la cuenta observada: se resuelve de nuevo.
    expect($llamadas)->toBe(1)
        ->and($solicitud->session()->has(VistaComoActiva::CLAVE_SESION))->toBeFalse()
        ->and($solicitud->session()->has('sec_rol_activo_id'))->toBeFalse();
});

it('pero una bandera ilegible tampoco deja escribir a ese request', function () {
    $llamadas = 0;
    $solicitud = vistaComoSolicitud('POST', [VistaComoActiva::CLAVE_SESION => ['basura' => true]]);

    expect(fn () => vistaComoMiddleware()->handle($solicitud, vistaComoSiguiente($llamadas)))
        ->toThrow(EscrituraEnVistaComo::class);

    expect($llamadas)->toBe(0);
});

// --- 3. Defensa en profundidad: los modelos no escriben en modo de solo lectura ----------

it('con el modo activo un modelo de dominio no se guarda, no se borra y no se restaura', function (string $operacion) {
    $capsula = new Capsule;
    $capsula->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsula->setAsGlobal();
    $capsula->bootEloquent();
    Model::setEventDispatcher(new Dispatcher(new Container));

    $modelo = new class extends ModeloDominio
    {
        protected $table = 'ninguna';
    };
    $modelo->exists = true;
    $modelo->id = 1;

    ModoSoloLectura::activar();

    expect(fn () => match ($operacion) {
        'guardar' => $modelo->save(),
        'borrar' => $modelo->delete(),
        'restaurar' => $modelo->restore(),
    })->toThrow(EscrituraEnModoSoloLectura::class);
})->with(['guardar', 'borrar', 'restaurar']);

it('el modo de solo lectura se enciende y se apaga, y apagado no estorba', function () {
    $modelo = new class extends ModeloDominio
    {
        protected $table = 'ninguna';
    };

    expect(ModoSoloLectura::activo())->toBeFalse();
    ModoSoloLectura::verificar($modelo);

    ModoSoloLectura::activar();
    expect(ModoSoloLectura::activo())->toBeTrue()
        ->and(fn () => ModoSoloLectura::verificar($modelo))->toThrow(EscrituraEnModoSoloLectura::class);

    ModoSoloLectura::desactivar();
    expect(ModoSoloLectura::activo())->toBeFalse();
    ModoSoloLectura::verificar($modelo);
});

// --- 4. El portal tiene UNA fuente de contrato ------------------------------------------

it('el portal resuelve el contrato de la cuenta del guard cliente y de ninguna otra parte', function () {
    $cuentaDeCliente = (new SecUsuarioCliente)->forceFill(['id' => 10, 'contrato_id' => 3]);
    $solicitud = Request::create('/portal/avance');
    $solicitud->setUserResolver(static fn (?string $guard = null) => $guard === 'cliente' ? $cuentaDeCliente : null);

    // Es lo que hace `AplicarVistaComo`: poner la cuenta observada en ESE guard.
    expect((new AutorizacionPortalClienteSesion)->contratoId($solicitud))->toBe(3);
});

it('un administrador con su propia sesión no tiene contrato: sin cuenta de cliente en el guard, nada', function () {
    $administrador = (new SecUsuarioInterno)->forceFill(['id' => 1, 'persona_id' => null, 'contrato_id' => 3]);
    $solicitud = Request::create('/portal/avance');
    $solicitud->setUserResolver(static fn (?string $guard = null) => $guard === 'interno' ? $administrador : null);

    // Ni siquiera un `contrato_id` colado en la cuenta interna se lee: el guard es `cliente`.
    expect((new AutorizacionPortalClienteSesion)->contratoId($solicitud))->toBeNull();
});

it('el módulo Portal no conoce la vista como: no hay un where aparte para «el administrador mirando»', function () use ($raizProyecto) {
    $conocen = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raizProyecto.'/app/Dominios/Portal', FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if (! $archivo->isFile() || ! str_ends_with($archivo->getPathname(), '.php')) {
            continue;
        }

        $contenido = (string) file_get_contents($archivo->getPathname());

        if (preg_match('/VistaComo|vista_como|viendo_como|user\(\s*[\'"]interno[\'"]\s*\)|guard\(\s*[\'"]interno[\'"]\s*\)/i', $contenido) === 1) {
            $conocen[] = substr($archivo->getPathname(), strlen($raizProyecto) + 1);
        }
    }

    expect($conocen)->toBe([]);
});

it('todo controlador del portal saca su contrato de AutorizacionPortalCliente', function () use ($raizProyecto) {
    $sinContrato = [];

    foreach (glob($raizProyecto.'/app/Dominios/Portal/Infraestructura/Http/Controllers/Web/*.php') ?: [] as $archivo) {
        $contenido = (string) file_get_contents($archivo);

        if (! str_contains($contenido, 'AutorizacionPortalCliente') || ! str_contains($contenido, '->contratoId($request)')) {
            $sinContrato[] = basename($archivo);
        }
    }

    expect($sinContrato)->toBe([]);
});

// --- 5. Siembra y esquema ----------------------------------------------------------

it('el permiso ver_como es solo del administrador de plataforma', function () use ($raizProyecto) {
    $semilla = (string) file_get_contents($raizProyecto.'/database/seeders/Catalogo/SeguridadSeeder.php');
    $codigo = 'seguridad.usuario.ver_como';

    // Existe en el catálogo…
    expect($semilla)->toContain("'{$codigo}' =>");

    // …ninguna lista de rol lo nombra…
    foreach (['PERMISOS_ENCARGADO_OPERACIONES', 'PERMISOS_JEFE_CAMPO', 'PERMISOS_PILOTO', 'PERMISOS_AUXILIAR'] as $lista) {
        preg_match('/private const '.$lista.' = \[(.*?)\];/s', $semilla, $coincidencia);

        expect($coincidencia)->not->toBeEmpty()
            ->and($coincidencia[1] ?? '')->not->toContain($codigo);
    }

    // …está en la lista de plataforma —la misma que `AsignarPermisosRol` hace cumplir para que
    // no se delegue— y el seeder la toma de ahí; el dueño la deja afuera.
    expect(PermisosReservados::SOLO_ADMIN_PLATAFORMA)->toContain($codigo)
        ->and($semilla)->toContain('private const PERMISOS_SOLO_ADMIN_PLATAFORMA = PermisosReservados::SOLO_ADMIN_PLATAFORMA;')
        ->and($semilla)->toMatch('/\$roles\[\'dueno\'\],\s*\$permisos->except\(self::PERMISOS_SOLO_ADMIN_PLATAFORMA\)/');
});

it('el enum de motivos y el de tipos no se desincronizan del CHECK del esquema', function () use ($raizProyecto) {
    $migracion = (string) file_get_contents($raizProyecto.'/database/migrations/2026_09_23_000101_create_sec_vistas_como_table.php');

    $enLista = static function (string $columna) use ($migracion): array {
        preg_match("/CHECK \\({$columna} IN \\(([^)]*)\\)\\)/", $migracion, $m);

        return array_map(static fn (string $v): string => trim($v, " '"), explode(',', $m[1] ?? ''));
    };

    expect($enLista('motivo_fin'))->toEqual(array_column(MotivoFinVistaComo::cases(), 'value'))
        ->and($enLista('tipo'))->toEqual(array_column(TipoUsuario::cases(), 'value'));
});
