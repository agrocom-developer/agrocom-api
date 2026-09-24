<?php

use App\Dominios\Comercial\Contratos\Eventos\ContratoCreado;
use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Notificaciones\Aplicacion\AbrirNotificacion;
use App\Dominios\Notificaciones\Aplicacion\EntregarNotificacion;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Aplicacion\ReglasDeNotificacion;
use App\Dominios\Notificaciones\Aplicacion\ResolverDestinatarios;
use App\Dominios\Notificaciones\Contratos\AlertasDeCuenta;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Notificaciones\Infraestructura\DestinoDeNotificacion;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Notificaciones\Infraestructura\EntregarNotificacionDelEvento;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web\NotificacionesController;
use App\Dominios\Operaciones\Contratos\Eventos\OrdenTrabajoCreada;
use App\Dominios\Operaciones\Contratos\Eventos\TrabajoCerrado;
use App\Dominios\Personal\Contratos\DatosIntegranteEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * Tarea 141 (ADR 0025) — las garantías del motor que necesitan base de datos,
 * contra el ESQUEMA REAL (las migraciones de seguridad, de la bitácora y de
 * `ntf_notificaciones`, en SQLite en memoria) y las clases reales (casos de
 * uso, listener, lectura, controlador):
 *
 *   1. IDEMPOTENCIA: el mismo evento no genera avisos duplicados aunque el
 *      listener corra dos veces (invariante 1 aplicada al evento), ni resucita
 *      uno dado de baja, y la unicidad de la base es la red de seguridad.
 *   2. AISLAMIENTO: una cuenta ve, abre y marca solo lo suyo — nunca lo de
 *      otra (el caso del portal del invariante 5, aplicado a las cuentas).
 *   3. Por rol ASIGNADO, no por rol activo (invariante 10): una notificación no
 *      es un permiso.
 *   4. Un fallo al avisar no rompe la operación de negocio; quien dispara el
 *      hecho no se avisa a sí mismo.
 *   5. Soft delete y bitácora como todo modelo de dominio (invariantes 8 y 9).
 *   6. La garantía es de código: ninguna lectura del modelo sale sin
 *      `deUsuario()`, y nada fuera del módulo toca `Notificacion`.
 */

uses(TestCase::class);

/**
 * Crea, con las migraciones reales, las tablas que el motor necesita.
 *
 * SQLite no conoce `CREATE UNIQUE INDEX ... USING btree ... WHERE`: las
 * migraciones de `sec_user` y `sec_user_role` crean la tabla y recién después
 * fallan en ese índice parcial de Postgres. Se tolera SOLO ese error; cualquier
 * otro (una columna nueva sin default, por ejemplo) rompe la prueba, como debe.
 */
function ntfEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'create_sec_role_table',
        'create_sec_user_role_table',
        'create_sec_user_preferencia_table',
        'create_sec_permission_table',
        'create_sec_role_permission_table',
        'create_ntf_notificaciones_table',
    ];

    // Cada archivo se incluye una sola vez por proceso: la migración es una clase anónima.
    static $instancias = [];

    foreach ($migraciones as $nombre) {
        $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];

        if (count($archivos) !== 1) {
            throw new RuntimeException("Se esperaba exactamente una migración {$nombre}.");
        }

        $instancias[$archivos[0]] ??= require $archivos[0];

        try {
            $instancias[$archivos[0]]->up();
        } catch (QueryException $excepcion) {
            if (! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

beforeEach(function () {
    ntfEsquema();

    // El listener atrapa y REPORTA cualquier error para no romper la operación de negocio; sin esto un
    // fallo real del reparto pasaría en silencio y la prueba solo vería «no se creó nada».
    Exceptions::fake();
    $this->esperaErroresReportados = false;
});

afterEach(function () {
    if (! $this->esperaErroresReportados) {
        Exceptions::assertNothingReported();
    }
});

/** @param  array<string, mixed>  $extra */
function ntfRol(string $nombre, array $extra = []): int
{
    $ahora = now();

    return DB::table('sec_role')->insertGetId([
        'name' => $nombre, 'description' => $nombre, 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora, ...$extra,
    ]);
}

/**
 * @param  list<int>  $roles
 * @param  array<string, mixed>  $extra
 */
function ntfCuenta(array $roles = [], array $extra = []): SecUser
{
    static $secuencia = 0;
    $secuencia++;
    $ahora = now();

    $id = DB::table('sec_user')->insertGetId([
        'name' => "Cuenta {$secuencia}",
        'username' => "cuenta.{$secuencia}",
        'password' => 'no-se-usa',
        'type' => TipoUsuario::Interno->value,
        'state' => true,
        'created_at' => $ahora,
        'updated_at' => $ahora,
        ...$extra,
    ]);

    foreach ($roles as $rolId) {
        DB::table('sec_user_role')->insert(['id_user' => $id, 'id_role' => $rolId, 'created_at' => $ahora, 'updated_at' => $ahora]);
    }

    return SecUser::withTrashed()->findOrFail($id);
}

function ntfAviso(int $usuarioId, string $clave, array $extra = []): Notificacion
{
    return Notificacion::create([
        'usuario_id' => $usuarioId,
        'tipo' => TipoNotificacion::ContratoCreado,
        'clave_evento' => $clave,
        'parametros' => ['cliente' => 'Cliente', 'hectareas' => '10,00'],
        'recurso_tipo' => RecursoNotificable::Contrato,
        'recurso_id' => 1,
        ...$extra,
    ]);
}

// ── 1. Idempotencia ─────────────────────────────────────────────────────────

test('el mismo evento repartido dos veces por el listener no genera avisos duplicados', function () {
    $encargado = ntfCuenta([ntfRol('encargado_operaciones')]);
    $evento = new ContratoCreado(12, 4, 'Colonia Menonita', '100');

    event($evento);
    event($evento);

    $avisos = Notificacion::query()->deUsuario($encargado->id)->get();

    expect($avisos)->toHaveCount(1)
        ->and($avisos[0]->clave_evento)->toBe('contrato_creado:12');
});

test('correr el caso de uso dos veces con el mismo evento deja las mismas filas: la segunda no crea nada', function () {
    $rol = ntfRol('jefe_campo');
    $a = ntfCuenta([$rol]);
    $b = ntfCuenta([$rol]);
    $entregar = app(EntregarNotificacion::class);
    $evento = new TrabajoCerrado(9, 3, 7, 2, '12.5');

    expect($entregar->ejecutar($evento))->toBe(2)
        ->and($entregar->ejecutar($evento))->toBe(0)
        ->and($entregar->ejecutar($evento))->toBe(0)
        ->and(Notificacion::query()->count())->toBe(2)
        ->and(Notificacion::query()->deUsuario($a->id)->count())->toBe(1)
        ->and(Notificacion::query()->deUsuario($b->id)->count())->toBe(1);
});

test('hechos distintos sí generan avisos distintos para la misma cuenta', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);

    event(new ContratoCreado(1, 1, 'A', '1'));
    event(new ContratoCreado(2, 1, 'B', '1'));

    expect(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(2);
});

test('un reintento no resucita ni duplica un aviso que la cuenta ya dio de baja', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $evento = new ContratoCreado(12, 4, 'Colonia', '100');

    event($evento);
    Notificacion::query()->deUsuario($cuenta->id)->firstOrFail()->delete();

    event($evento);

    expect(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(0)
        ->and(Notificacion::withTrashed()->deUsuario($cuenta->id)->count())->toBe(1);
});

test('la base rechaza un aviso repetido para la misma cuenta y el mismo hecho, también si el original está dado de baja', function () {
    $cuenta = ntfCuenta();
    $original = ntfAviso($cuenta->id, 'contrato_creado:1');

    expect(fn () => ntfAviso($cuenta->id, 'contrato_creado:1'))->toThrow(UniqueConstraintViolationException::class);

    $original->delete();

    expect(fn () => ntfAviso($cuenta->id, 'contrato_creado:1'))->toThrow(UniqueConstraintViolationException::class);
});

test('la misma clave de hecho para OTRA cuenta sí es un aviso distinto', function () {
    $uno = ntfCuenta();
    $otro = ntfCuenta();

    ntfAviso($uno->id, 'contrato_creado:1');
    ntfAviso($otro->id, 'contrato_creado:1');

    expect(Notificacion::query()->count())->toBe(2);
});

test('si dos procesos se cruzan y el otro gana la carrera, el reparto lo toma como «ya estaba» y no falla', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $otroProceso = false;

    // Justo después de la búsqueda previa («¿ya existe?») y antes del INSERT, «el otro proceso» confirma
    // su fila. Va fuera de la transacción del reparto, como en una carrera real: si la escribiera adentro,
    // el SAVEPOINT la revertiría junto con el intento duplicado.
    DB::listen(function ($consulta) use (&$otroProceso, $cuenta): void {
        if ($otroProceso || ! str_starts_with($consulta->sql, 'select') || ! str_contains($consulta->sql, 'ntf_notificaciones')) {
            return;
        }

        $otroProceso = true;
        DB::table('ntf_notificaciones')->insert([
            'usuario_id' => $cuenta->id,
            'tipo' => 'contrato_creado',
            'clave_evento' => 'contrato_creado:12',
            'recurso_tipo' => 'contrato',
            'recurso_id' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $creadas = app(EntregarNotificacion::class)->ejecutar(new ContratoCreado(12, 4, 'Colonia', '100'));

    expect($otroProceso)->toBeTrue()
        ->and($creadas)->toBe(0)
        ->and(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(1);
});

// ── 2. Aislamiento entre cuentas ────────────────────────────────────────────

test('una cuenta ve solo sus avisos: la lectura de la campana nunca incluye los de otra', function () {
    $ana = ntfCuenta();
    $beto = ntfCuenta();
    ntfAviso($ana->id, 'contrato_creado:1');
    ntfAviso($ana->id, 'contrato_creado:2');
    ntfAviso($beto->id, 'contrato_creado:3');

    $lectura = app(LecturaNotificaciones::class);

    expect($lectura->recientesDe($ana->id, 10))->toHaveCount(2)
        ->and($lectura->recientesDe($beto->id, 10))->toHaveCount(1)
        ->and($lectura->recientesDe(999, 10))->toBe([]);

    $idsDeAna = Notificacion::query()->deUsuario($ana->id)->pluck('id')->all();
    $idsMostradosABeto = array_map(static fn ($a) => $a->id, $lectura->recientesDe($beto->id, 10));

    expect(array_intersect($idsDeAna, $idsMostradosABeto))->toBe([]);
});

test('abrir el aviso de otra cuenta responde como si no existiera y no lo marca leído', function () {
    $ana = ntfCuenta();
    $beto = ntfCuenta();
    $deBeto = ntfAviso($beto->id, 'contrato_creado:1');

    expect(fn () => app(AbrirNotificacion::class)->ejecutar($ana->id, $deBeto->id))->toThrow(ModelNotFoundException::class);
    // Y un id que no existe da exactamente lo mismo: no se distingue «es de otro» de «no existe».
    expect(fn () => app(AbrirNotificacion::class)->ejecutar($ana->id, 987654))->toThrow(ModelNotFoundException::class);

    expect($deBeto->fresh()->leida_en)->toBeNull();
});

test('abrir el aviso propio lo marca leído una sola vez y no pisa la hora de la primera lectura', function () {
    $ana = ntfCuenta();
    $aviso = ntfAviso($ana->id, 'contrato_creado:1');

    $this->travelTo(now()->setTime(10, 0));
    app(AbrirNotificacion::class)->ejecutar($ana->id, $aviso->id);
    $primera = $aviso->fresh()->leida_en;

    $this->travelTo(now()->addHour());
    app(AbrirNotificacion::class)->ejecutar($ana->id, $aviso->id);

    expect($primera)->not->toBeNull()
        ->and($aviso->fresh()->leida_en->equalTo($primera))->toBeTrue();
});

test('abrir un aviso con la vista «como otro usuario» activa no lo marca leído ni falla: el destino sigue alcanzable', function () {
    $ana = ntfCuenta();
    $aviso = ntfAviso($ana->id, 'contrato_creado:1');

    ModoSoloLectura::activar();

    try {
        $abierto = app(AbrirNotificacion::class)->ejecutar($ana->id, $aviso->id);
    } finally {
        ModoSoloLectura::desactivar();
    }

    expect($abierto->id)->toBe($aviso->id)
        ->and($aviso->fresh()->leida_en)->toBeNull();
});

test('marcar todas como leídas afecta solo a la cuenta que lo pide', function () {
    $ana = ntfCuenta();
    $beto = ntfCuenta();
    ntfAviso($ana->id, 'contrato_creado:1');
    ntfAviso($ana->id, 'contrato_creado:2');
    ntfAviso($beto->id, 'contrato_creado:3');

    expect(app(MarcarTodasLeidas::class)->ejecutar($ana->id))->toBe(2)
        ->and(Notificacion::query()->deUsuario($ana->id)->sinLeer()->count())->toBe(0)
        ->and(Notificacion::query()->deUsuario($beto->id)->sinLeer()->count())->toBe(1)
        ->and(app(MarcarTodasLeidas::class)->ejecutar($ana->id))->toBe(0);
});

test('el controlador toma la cuenta de la sesión: el id de aviso de la petición no le da acceso a lo ajeno', function () {
    $ana = ntfCuenta();
    $beto = ntfCuenta();
    $deBeto = ntfAviso($beto->id, 'contrato_creado:1');

    $peticion = Request::create("/panel/notificaciones/{$deBeto->id}/abrir");
    $peticion->setUserResolver(static fn (?string $guard = null) => $guard === 'interno' ? $ana : null);

    $controlador = app(NotificacionesController::class);
    $destino = new DestinoDeNotificacion(new class implements AutorizacionPanelWeb
    {
        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return true;
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
            return '/panel/dashboard';
        }
    });

    expect(fn () => $controlador->abrir($peticion, $deBeto->id, app(AbrirNotificacion::class), $destino))->toThrow(ModelNotFoundException::class);
});

// ── 3. Por rol asignado, no por rol activo ─────────────────────────────────

test('«por rol» alcanza a la cuenta que tiene el rol asignado aunque también tenga otros (piloto y jefe de campo)', function () {
    $piloto = ntfRol('piloto');
    $jefe = ntfRol('jefe_campo');
    $abraham = ntfCuenta([$piloto, $jefe]);
    $soloPiloto = ntfCuenta([$piloto]);

    $ids = app(LecturaUsuariosPorRol::class)->idsConRol('jefe_campo');

    expect($ids)->toBe([$abraham->id])
        ->and(app(LecturaUsuariosPorRol::class)->idsConRol('piloto'))->toBe([$abraham->id, $soloPiloto->id]);

    // Y el aviso le llega: el rol activo de su sesión no interviene en el reparto.
    event(new TrabajoCerrado(9, 3, 7, 2, '12'));

    expect(Notificacion::query()->deUsuario($abraham->id)->count())->toBe(1)
        ->and(Notificacion::query()->deUsuario($soloPiloto->id)->count())->toBe(0);
});

test('quedan fuera del reparto por rol: cuenta bloqueada, de portal, dada de baja, con la asignación revocada o con el rol inactivo', function () {
    $rol = ntfRol('encargado_operaciones');
    $vigente = ntfCuenta([$rol]);
    ntfCuenta([$rol], ['state' => false]);
    ntfCuenta([$rol], ['type' => TipoUsuario::Cliente->value]);
    ntfCuenta([$rol], ['deleted_at' => now()]);

    $revocada = ntfCuenta([$rol]);
    DB::table('sec_user_role')->where('id_user', $revocada->id)->update(['deleted_at' => now()]);

    expect(app(LecturaUsuariosPorRol::class)->idsConRol('encargado_operaciones'))->toBe([$vigente->id]);

    DB::table('sec_role')->where('id', $rol)->update(['state' => false]);

    expect(app(LecturaUsuariosPorRol::class)->idsConRol('encargado_operaciones'))->toBe([])
        ->and(app(LecturaUsuariosPorRol::class)->idsConRol('rol_que_no_existe'))->toBe([]);
});

test('el equipo asignado alcanza a los integrantes con cuenta de una orden de trabajo, y a nadie más', function () {
    $piloto = ntfCuenta([ntfRol('piloto')], ['persona_id' => 40]);
    $auxiliar = ntfCuenta([ntfRol('auxiliar')], ['persona_id' => 41]);
    $ajeno = ntfCuenta([ntfRol('jefe_campo')], ['persona_id' => 42]);
    // La persona 43 integra el equipo pero no tiene cuenta.

    app()->instance(LecturaEquipoTrabajo::class, new class implements LecturaEquipoTrabajo
    {
        public function vigentesAFecha(string $fecha): array
        {
            return [];
        }

        public function porIds(array $ids): array
        {
            return [];
        }

        public function integrantesAFecha(int $equipoTrabajoId, string $fecha): array
        {
            return $equipoTrabajoId === 1
                ? array_map(static fn (int $p): DatosIntegranteEquipo => new DatosIntegranteEquipo($p, $p, "P{$p}", 'piloto', '2026-01-01', null), [40, 41, 43])
                : [];
        }

        public function recursosAFecha(int $equipoTrabajoId, string $fecha): array
        {
            return [];
        }
    });

    event(new OrdenTrabajoCreada(7, 3, 2, [1], '140'));

    expect(Notificacion::query()->deUsuario($piloto->id)->count())->toBe(1)
        ->and(Notificacion::query()->deUsuario($auxiliar->id)->count())->toBe(1)
        ->and(Notificacion::query()->deUsuario($ajeno->id)->count())->toBe(0)
        ->and(Notificacion::query()->count())->toBe(2);
});

// ── 4. Un fallo no rompe el negocio; el autor no se avisa a sí mismo ────────

test('un error al repartir se reporta pero nunca llega a quien anunció el hecho', function () {
    $this->esperaErroresReportados = true;

    $regla = new class implements ReglaNotificacion
    {
        public function evento(): string
        {
            return ContratoCreado::class;
        }

        public function armar(object $evento): NotificacionArmada
        {
            throw new RuntimeException('falla al armar el aviso');
        }
    };

    $listener = new EntregarNotificacionDelEvento(new EntregarNotificacion(
        new ReglasDeNotificacion([$regla]),
        app(ResolverDestinatarios::class),
    ));

    $listener->handle(new ContratoCreado(1, 1, 'A', '1'));

    Exceptions::assertReported(RuntimeException::class);
    expect(Notificacion::query()->count())->toBe(0);
});

test('quien dispara el hecho no se avisa a sí mismo, aunque tenga el rol', function () {
    $rol = ntfRol('encargado_operaciones');
    $autor = ntfCuenta([$rol]);
    $colega = ntfCuenta([$rol]);

    Auth::guard('interno')->setUser($autor);
    Auth::shouldUse('interno');

    event(new ContratoCreado(12, 4, 'Colonia', '100'));

    expect(Notificacion::query()->deUsuario($autor->id)->count())->toBe(0)
        ->and(Notificacion::query()->deUsuario($colega->id)->count())->toBe(1);
});

test('sin nadie autenticado (una consola, un job) no se excluye a nadie', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);

    event(new ContratoCreado(12, 4, 'Colonia', '100'));

    expect(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(1);
});

test('el listener está registrado para cada evento que tiene regla, y para ninguno más', function () {
    foreach ([ContratoCreado::class, OrdenTrabajoCreada::class, TrabajoCerrado::class] as $evento) {
        expect(Event::hasListeners($evento))->toBeTrue("sin listener para {$evento}");
    }

    expect(app(ReglasDeNotificacion::class)->eventos())->toEqualCanonicalizing([
        ContratoCreado::class,
        OrdenTrabajoCreada::class,
        TrabajoCerrado::class,
    ]);
});

test('sin nadie a quien avisar el hecho no crea nada ni falla', function () {
    event(new ContratoCreado(12, 4, 'Colonia', '100'));

    expect(Notificacion::query()->count())->toBe(0);
});

// ── Un destinatario roto no anula el aviso de los demás ────────────────────

test('un equipo con datos rotos se reporta y no anula el aviso de los otros equipos de la misma orden de trabajo', function () {
    $this->esperaErroresReportados = true;

    $delEquipoSano = ntfCuenta([ntfRol('piloto')], ['persona_id' => 41]);

    app()->instance(LecturaEquipoTrabajo::class, new class implements LecturaEquipoTrabajo
    {
        public function vigentesAFecha(string $fecha): array
        {
            return [];
        }

        public function porIds(array $ids): array
        {
            return [];
        }

        public function integrantesAFecha(int $equipoTrabajoId, string $fecha): array
        {
            // Como `LecturaEquipoTrabajoEloquent` con un integrante cuya persona se dio de baja.
            if ($equipoTrabajoId === 1) {
                throw new ErrorException('Attempt to read property "nombre" on null');
            }

            return [new DatosIntegranteEquipo(1, 41, 'P41', 'piloto', '2026-01-01', null)];
        }

        public function recursosAFecha(int $equipoTrabajoId, string $fecha): array
        {
            return [];
        }
    });

    event(new OrdenTrabajoCreada(7, 3, 2, [1, 2], '100'));

    Exceptions::assertReported(ErrorException::class);
    expect(Notificacion::query()->deUsuario($delEquipoSano->id)->count())->toBe(1);
});

// ── El aviso sale al confirmar, no antes ni si se revierte ──────────────────

test('dentro de una transacción el aviso NO sale hasta que se confirma', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $vistoAdentro = null;

    DB::transaction(function () use ($cuenta, &$vistoAdentro): void {
        event(new ContratoCreado(12, 4, 'Colonia', '100'));

        $vistoAdentro = Notificacion::query()->deUsuario($cuenta->id)->count();
    });

    expect($vistoAdentro)->toBe(0)
        ->and(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(1);
});

test('si la transacción se revierte, el hecho no se anuncia: ningún aviso de algo que no ocurrió', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);

    try {
        DB::transaction(function (): void {
            event(new ContratoCreado(12, 4, 'Colonia', '100'));

            throw new RuntimeException('el alta falló después de anunciar');
        });
    } catch (RuntimeException) {
        // esperado
    }

    expect(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(0);
});

test('el aviso de una tanda anidada sale recién cuando confirma la transacción de afuera', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $visto = [];

    DB::transaction(function () use ($cuenta, &$visto): void {
        DB::transaction(function (): void {
            event(new ContratoCreado(12, 4, 'Colonia', '100'));
        });

        $visto[] = Notificacion::query()->deUsuario($cuenta->id)->count();
    });

    expect($visto)->toBe([0])
        ->and(Notificacion::query()->deUsuario($cuenta->id)->count())->toBe(1);
});

// ── La campana no tira el panel ─────────────────────────────────────────────

test('si falla la lectura de avisos, la cáscara del panel los reporta y sigue con la campana vacía', function () {
    $this->esperaErroresReportados = true;

    app()->instance(LecturaNotificaciones::class, new class implements LecturaNotificaciones
    {
        public function recientesDe(int $usuarioId, int $limite): array
        {
            throw new RuntimeException('no existe la tabla ntf_notificaciones');
        }

        public function alertasDeCuenta(int $usuarioId): AlertasDeCuenta
        {
            return new AlertasDeCuenta;
        }
    });

    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $metodo = new ReflectionMethod(CascaraPanel::class, 'notificaciones');

    $campana = $metodo->invoke(app(CascaraPanel::class), $cuenta, 1);

    Exceptions::assertReported(RuntimeException::class);
    expect($campana)->toBe([]);
});

test('la cáscara arma la campana con enlace, id y el texto ya traducido', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);
    $aviso = ntfAviso($cuenta->id, 'contrato_creado:1', ['parametros' => ['cliente' => 'Colonia', 'hectareas' => '10,00']]);

    $campana = (new ReflectionMethod(CascaraPanel::class, 'notificaciones'))->invoke(app(CascaraPanel::class), $cuenta, 1);

    expect($campana)->toHaveCount(1)
        ->and($campana[0])->toMatchArray([
            'id' => $aviso->id,
            'icon' => 'description',
            'title' => 'Contrato nuevo de Colonia (10,00 ha).',
            'unread' => true,
            'href' => route('panel.notificaciones.abrir', $aviso->id),
        ]);
});

test('el texto del aviso de orden de trabajo aclara que las hectáreas son las de toda la tanda', function () {
    $cuenta = ntfCuenta();
    ntfAviso($cuenta->id, 'orden_trabajo_creada:1', [
        'tipo' => TipoNotificacion::OrdenTrabajoCreada,
        'recurso_tipo' => RecursoNotificable::OrdenTrabajo,
        'parametros' => ['orden' => 3, 'aplicacion' => 2, 'hectareas' => '100,00'],
    ]);

    $titulo = app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 1)[0]->titulo;

    expect($titulo)->toBe('Tu cuadrilla tiene una orden de trabajo nueva: aplicación 2 de la orden #3 (100,00 ha en total).');
});

// ── 5. Soft delete y bitácora ───────────────────────────────────────────────

test('el borrado físico de un aviso está bloqueado (invariante 8)', function () {
    $aviso = ntfAviso(ntfCuenta()->id, 'contrato_creado:1');

    expect(fn () => $aviso->forceDelete())->toThrow(BorradoFisicoNoPermitido::class);
});

test('repartir un aviso y abrirlo dejan huella en la bitácora, sin que ningún caso de uso la llame (invariante 9)', function () {
    $cuenta = ntfCuenta([ntfRol('encargado_operaciones')]);

    event(new ContratoCreado(12, 4, 'Colonia', '100'));
    $aviso = Notificacion::query()->deUsuario($cuenta->id)->firstOrFail();

    expect(Bitacora::query()->where('tabla', 'ntf_notificaciones')->where('registro_id', $aviso->id)->where('accion', 'creado')->count())->toBe(1);

    app(AbrirNotificacion::class)->ejecutar($cuenta->id, $aviso->id);

    expect(Bitacora::query()->where('tabla', 'ntf_notificaciones')->where('registro_id', $aviso->id)->where('accion', 'actualizado')->count())->toBe(1);
});

test('marcar todas como leídas deja una fila de bitácora por aviso, no un UPDATE masivo que se la salte', function () {
    $cuenta = ntfCuenta();
    ntfAviso($cuenta->id, 'contrato_creado:1');
    ntfAviso($cuenta->id, 'contrato_creado:2');

    app(MarcarTodasLeidas::class)->ejecutar($cuenta->id);

    expect(Bitacora::query()->where('tabla', 'ntf_notificaciones')->where('accion', 'actualizado')->count())->toBe(2);
});

test('la autoría queda en la fila: quién disparó el hecho es created_by', function () {
    $autor = ntfCuenta();
    $destinatario = ntfCuenta([ntfRol('encargado_operaciones')]);
    Auth::guard('interno')->setUser($autor);
    Auth::shouldUse('interno');

    event(new ContratoCreado(12, 4, 'Colonia', '100'));

    expect(Notificacion::query()->deUsuario($destinatario->id)->firstOrFail()->created_by)->toBe($autor->id);
});

// ── La lectura de la campana ────────────────────────────────────────────────

test('la lectura prioriza los no leídos: uno viejo sin leer no queda afuera por culpa de leídos más nuevos', function () {
    $cuenta = ntfCuenta();
    $this->travelTo(now()->subDays(3));
    ntfAviso($cuenta->id, 'contrato_creado:1');            // el viejo, sin leer
    $this->travelTo(now()->addDays(3));

    foreach (range(2, 6) as $n) {
        ntfAviso($cuenta->id, "contrato_creado:{$n}", ['leida_en' => now()]);   // cinco leídos nuevos
    }

    $lectura = app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 3);

    expect($lectura)->toHaveCount(3)
        ->and(collect($lectura)->where('leida', false))->toHaveCount(1);
});

test('la lectura respeta el tope, va del más nuevo al más viejo y devuelve el texto ya traducido y el ícono', function () {
    $cuenta = ntfCuenta();
    $this->travelTo(now()->subHours(2));
    ntfAviso($cuenta->id, 'contrato_creado:1', ['parametros' => ['cliente' => 'Viejo', 'hectareas' => '1,00']]);
    $this->travelTo(now()->addHours(2));
    ntfAviso($cuenta->id, 'contrato_creado:2', ['parametros' => ['cliente' => 'Nuevo', 'hectareas' => '2,00']]);

    $lectura = app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 10);

    expect($lectura[0]->titulo)->toBe('Contrato nuevo de Nuevo (2,00 ha).')
        ->and($lectura[1]->titulo)->toBe('Contrato nuevo de Viejo (1,00 ha).')
        ->and($lectura[0]->icono)->toBe('description')
        ->and(app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 1))->toHaveCount(1)
        ->and(app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 0))->toBe([]);
});

test('el nombre del cliente entra al texto tal cual, sin que sus «:» sean tomados por parámetros', function () {
    $cuenta = ntfCuenta();
    ntfAviso($cuenta->id, 'contrato_creado:1', ['parametros' => ['cliente' => 'Agro :hectareas S.A.', 'hectareas' => '5,00']]);

    $titulo = app(LecturaNotificaciones::class)->recientesDe($cuenta->id, 1)[0]->titulo;

    expect($titulo)->toBe('Contrato nuevo de Agro :hectareas S.A. (5,00 ha).');
});

// ── A dónde lleva el click, según el rol activo ─────────────────────────────

/** @return AutorizacionPanelWeb un doble que concede solo los permisos dados */
function ntfAutorizacion(array $permisos): AutorizacionPanelWeb
{
    return new class($permisos) implements AutorizacionPanelWeb
    {
        /** @param list<string> $permisos */
        public function __construct(private array $permisos) {}

        public function tienePermiso(Request $request, string $codigoPermiso): bool
        {
            return in_array($codigoPermiso, $this->permisos, true);
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
            return 'http://localhost/panel/dashboard';
        }
    };
}

test('el click lleva al recurso si el rol activo puede verlo y, si no, a su primera pantalla — nunca a un 403', function (RecursoNotificable $recurso, string $permiso, string $rutaEsperada) {
    $aviso = new Notificacion(['recurso_tipo' => $recurso, 'recurso_id' => 42]);
    $peticion = Request::create('/panel/notificaciones/1/abrir');

    $conPermiso = (new DestinoDeNotificacion(ntfAutorizacion([$permiso])))->url($peticion, $aviso);
    $sinPermiso = (new DestinoDeNotificacion(ntfAutorizacion([])))->url($peticion, $aviso);

    expect($conPermiso)->toBe(route($rutaEsperada, 42))
        ->and($sinPermiso)->toBe('http://localhost/panel/dashboard');
})->with([
    'contrato' => [RecursoNotificable::Contrato, 'comercial.contrato.editar', 'panel.contratos.edit'],
    'orden de trabajo' => [RecursoNotificable::OrdenTrabajo, 'operaciones.trabajo.ver', 'panel.trabajos.show'],
    'trabajo' => [RecursoNotificable::Trabajo, 'operaciones.trabajo.ver', 'panel.trabajos.detalle'],
]);

test('cada recurso que un aviso puede nombrar tiene destino: el match del destino cubre todos los casos', function () {
    $peticion = Request::create('/x');

    foreach (RecursoNotificable::cases() as $recurso) {
        $url = (new DestinoDeNotificacion(ntfAutorizacion([])))->url($peticion, new Notificacion(['recurso_tipo' => $recurso, 'recurso_id' => 1]));

        expect($url)->toBeString();
    }
});

// ── Rutas ───────────────────────────────────────────────────────────────────

test('las rutas de la campana exigen sesión interna y rol activo, y el id del aviso es numérico y cabe en un entero', function () {
    $abrir = Route::getRoutes()->getByName('panel.notificaciones.abrir');
    $marcar = Route::getRoutes()->getByName('panel.notificaciones.marcar-todas');

    expect($abrir)->not->toBeNull()
        ->and($marcar)->not->toBeNull()
        ->and($abrir->methods())->toContain('GET')
        ->and($marcar->methods())->toContain('POST')
        ->and($marcar->methods())->not->toContain('GET')
        ->and($abrir->gatherMiddleware())->toContain('auth:interno')->toContain('rol.activo')
        ->and($marcar->gatherMiddleware())->toContain('auth:interno')->toContain('rol.activo')
        ->and($abrir->wheres)->toHaveKey('notificacion', '[0-9]{1,18}');
});

// ── 6. La garantía es de código, no de un test de endpoint ──────────────────

/** @return list<string> rutas relativas de los archivos PHP de app/ */
function ntfArchivosDeApp(): array
{
    $raiz = dirname(__DIR__, 2);
    $archivos = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/app', FilesystemIterator::SKIP_DOTS)) as $archivo) {
        if ($archivo->isFile() && $archivo->getExtension() === 'php') {
            $archivos[] = substr($archivo->getPathname(), strlen($raiz) + 1);
        }
    }

    sort($archivos);

    return $archivos;
}

test('todo acceso al modelo Notificacion sale acotado por cuenta con deUsuario()', function () {
    $raiz = dirname(__DIR__, 2);
    $sinAcotar = [];

    foreach (ntfArchivosDeApp() as $ruta) {
        $codigo = (string) file_get_contents($raiz.'/'.$ruta);

        if ($ruta === 'app/Dominios/Notificaciones/Infraestructura/Eloquent/Notificacion.php') {
            continue;
        }

        // Cada consulta que arranca en `Notificacion::` (query, withTrashed, where, find…) tiene
        // que llamar a `deUsuario(` antes de terminar la sentencia. `create` no lee: solo escribe.
        preg_match_all('/(?<![A-Za-z_])Notificacion::(?!create\b|creating\b|class\b)\w+\([^;]*;/s', $codigo, $consultas);

        foreach ($consultas[0] as $consulta) {
            if (! str_contains($consulta, 'deUsuario(')) {
                $sinAcotar[] = $ruta.': '.preg_replace('/\s+/', ' ', mb_substr($consulta, 0, 90));
            }
        }
    }

    expect($sinAcotar)->toBe([], "Una consulta a Notificacion sin ->deUsuario(): el aviso de otra cuenta quedaría al alcance.\n".implode("\n", $sinAcotar));
});

test('ningún archivo fuera del módulo Notificaciones usa el modelo Notificacion', function () {
    $raiz = dirname(__DIR__, 2);
    $intrusos = [];

    foreach (ntfArchivosDeApp() as $ruta) {
        if (str_starts_with($ruta, 'app/Dominios/Notificaciones/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($raiz.'/'.$ruta), 'Infraestructura\Eloquent\Notificacion')) {
            $intrusos[] = $ruta;
        }
    }

    expect($intrusos)->toBe([]);
});

test('el módulo Notificaciones no escribe en tablas ajenas: solo ntf_notificaciones', function () {
    $raiz = dirname(__DIR__, 2);
    $ajenas = [];

    foreach (ntfArchivosDeApp() as $ruta) {
        if (! str_starts_with($ruta, 'app/Dominios/Notificaciones/')) {
            continue;
        }

        $codigo = (string) file_get_contents($raiz.'/'.$ruta);

        if (preg_match('/DB::table\(|->from\(|\'(?!ntf_)(?:sec|com|ope|per|fin|inv|man|mez|cpn|dis|syn|plt)_[a-z_]+\'/', $codigo) === 1) {
            $ajenas[] = $ruta;
        }
    }

    expect($ajenas)->toBe([]);
});

test('cada evento de la primera cadena lo anuncia un solo archivo, el del módulo dueño de esa transición', function () {
    $raiz = dirname(__DIR__, 2);
    $emisores = [
        'ContratoCreado' => 'app/Dominios/Comercial/Aplicacion/CrearContrato.php',
        'OrdenTrabajoCreada' => 'app/Dominios/Operaciones/Aplicacion/CrearOrdenTrabajo.php',
        'TrabajoCerrado' => 'app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php',
    ];

    foreach ($emisores as $evento => $esperado) {
        $encontrados = array_values(array_filter(
            ntfArchivosDeApp(),
            static fn (string $ruta): bool => str_contains((string) file_get_contents($raiz.'/'.$ruta), "event(new {$evento}("),
        ));

        expect($encontrados)->toBe([$esperado], "«{$evento}» debe anunciarlo solo {$esperado}");
    }
});

test('el trabajo cerrado se anuncia DESPUÉS de persistir la transición y el contrato y la orden de trabajo DESPUÉS de confirmar el alta', function () {
    $raiz = dirname(__DIR__, 2);

    // Posición de cada marca; `false` (la marca desapareció) tiene que hacer fallar la prueba, no pasar.
    $despues = function (string $archivo, string $evento, string $marca) use ($raiz): void {
        $codigo = (string) file_get_contents($raiz.'/'.$archivo);
        $anuncio = strpos($codigo, $evento);
        $previa = strpos($codigo, $marca);

        expect($anuncio)->not->toBeFalse("{$archivo} ya no anuncia {$evento}")
            ->and($previa)->not->toBeFalse("{$archivo} ya no contiene «{$marca}»")
            ->and($anuncio)->toBeGreaterThan($previa, "{$archivo}: {$evento} tiene que ir después de «{$marca}»");
    };

    $despues('app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php', 'event(new TrabajoCerrado(', '$trabajo->save();');
    $despues('app/Dominios/Comercial/Aplicacion/CrearContrato.php', 'event(new ContratoCreado(', 'DB::transaction(');
    $despues('app/Dominios/Operaciones/Aplicacion/CrearOrdenTrabajo.php', 'event(new OrdenTrabajoCreada(', 'DB::transaction(');
});
