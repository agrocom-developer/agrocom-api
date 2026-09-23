<?php

use App\Dominios\Comercial\Contratos\Eventos\ContratoCreado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaContratoCreado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaOrdenTrabajoCreada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaTrabajoCerrado;
use App\Dominios\Notificaciones\Aplicacion\ReglasDeNotificacion;
use App\Dominios\Notificaciones\Aplicacion\ResolverDestinatarios;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\FormatoNotificacion;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Operaciones\Contratos\Eventos\OrdenTrabajoCreada;
use App\Dominios\Operaciones\Contratos\Eventos\TrabajoCerrado;
use App\Dominios\Personal\Contratos\DatosIntegranteEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\DatosUsuarioDePersona;
use App\Dominios\Seguridad\Contratos\LecturaUsuarioDePersona;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Carbon;

/*
 * Tarea 141 (ADR 0025) — el motor de notificaciones, lo que no necesita base
 * de datos: qué dice cada regla y a quién, cómo se convierten roles, personas
 * y equipos en cuentas, y que el catálogo de avisos está completo. Las
 * garantías que sí necesitan la base (idempotencia, aislamiento entre cuentas)
 * están en `NotificacionesEntregaTest`.
 */

$raizProyecto = dirname(__DIR__, 2);

// ── Las reglas: qué se dice, a quién y a qué recurso ─────────────────────────

test('contrato creado avisa al rol encargado de operaciones y lleva al contrato', function () {
    $armada = (new ReglaContratoCreado)->armar(new ContratoCreado(12, 4, 'Colonia Menonita', '1250.5'));

    expect($armada->tipo)->toBe(TipoNotificacion::ContratoCreado)
        ->and($armada->claveEvento)->toBe('contrato_creado:12')
        ->and($armada->recurso)->toBe(RecursoNotificable::Contrato)
        ->and($armada->recursoId)->toBe(12)
        ->and($armada->parametros)->toBe(['cliente' => 'Colonia Menonita', 'hectareas' => '1.250,50'])
        ->and($armada->destinatarios)->toEqual([Destinatario::rol(RolDestinatario::EncargadoOperaciones)]);
});

test('orden de trabajo creada avisa a cada equipo asignado y lleva a la orden de trabajo', function () {
    $armada = (new ReglaOrdenTrabajoCreada)->armar(new OrdenTrabajoCreada(7, 3, 2, [1, 4], '140'));

    expect($armada->tipo)->toBe(TipoNotificacion::OrdenTrabajoCreada)
        ->and($armada->claveEvento)->toBe('orden_trabajo_creada:7')
        ->and($armada->recurso)->toBe(RecursoNotificable::OrdenTrabajo)
        ->and($armada->recursoId)->toBe(7)
        ->and($armada->parametros)->toBe(['orden' => 3, 'aplicacion' => 2, 'hectareas' => '140,00'])
        ->and($armada->destinatarios)->toEqual([Destinatario::equipo(1), Destinatario::equipo(4)]);
});

test('trabajo cerrado avisa a jefe de campo y a encargado de operaciones, y lleva al trabajo', function () {
    $armada = (new ReglaTrabajoCerrado)->armar(new TrabajoCerrado(9, 3, 7, 2, '12.5'));

    expect($armada->tipo)->toBe(TipoNotificacion::TrabajoCerrado)
        ->and($armada->claveEvento)->toBe('trabajo_cerrado:9')
        ->and($armada->recurso)->toBe(RecursoNotificable::Trabajo)
        ->and($armada->recursoId)->toBe(9)
        ->and($armada->parametros)->toBe(['orden' => 3, 'aplicacion' => 2, 'hectareas' => '12,50'])
        ->and($armada->destinatarios)->toEqual([
            Destinatario::rol(RolDestinatario::JefeCampo),
            Destinatario::rol(RolDestinatario::EncargadoOperaciones),
        ]);
});

test('el dueño no recibe el aviso de trabajo cerrado (ADR 0025, punto 10: es el más frecuente)', function () {
    $armada = (new ReglaTrabajoCerrado)->armar(new TrabajoCerrado(9, 3, null, 2, '12'));

    expect($armada->destinatarios)->not->toContainEqual(Destinatario::rol(RolDestinatario::Dueno));
});

test('la identidad del hecho es estable: el mismo hecho da la misma clave y hechos distintos, claves distintas', function () {
    $regla = new ReglaTrabajoCerrado;

    $a = $regla->armar(new TrabajoCerrado(9, 3, 7, 2, '12.5'));
    $otraVez = $regla->armar(new TrabajoCerrado(9, 3, 7, 2, '12.5'));
    $otroTrabajo = $regla->armar(new TrabajoCerrado(10, 3, 7, 2, '12.5'));

    expect($otraVez->claveEvento)->toBe($a->claveEvento)
        ->and($otroTrabajo->claveEvento)->not->toBe($a->claveEvento);

    // No depende del momento: una clave con la hora no sería idempotente.
    expect($a->claveEvento)->toMatch('/^[a-z_]+:\d+$/');
});

test('una regla rechaza un evento que no es el suyo', function () {
    (new ReglaContratoCreado)->armar(new TrabajoCerrado(1, 1, null, 1, '1'));
})->throws(InvalidArgumentException::class);

test('el formato de hectáreas redondea al centésimo con decimales exactos, sin float en el cálculo', function (string $entrada, string $esperado) {
    expect(FormatoNotificacion::hectareas($entrada))->toBe($esperado);
})->with([
    'entero' => ['140', '140,00'],
    'un decimal' => ['12.5', '12,50'],
    'redondea hacia arriba' => ['0.005', '0,01'],
    'miles' => ['1250.5', '1.250,50'],
    'ya en dos decimales' => ['80.00', '80,00'],
    'millones' => ['1234567.891', '1.234.567,89'],
    'cero' => ['0', '0,00'],
    'redondeo que cambia el grupo de miles' => ['999.995', '1.000,00'],
    'negativo' => ['-1250.5', '-1.250,50'],
    // Un float pierde precisión desde ~15 dígitos: con BigDecimal no hay pérdida.
    'más dígitos de los que un float representa' => ['12345678901234567.895', '12.345.678.901.234.567,90'],
]);

// ── El registro de reglas ────────────────────────────────────────────────────

test('el registro indexa cada regla por su evento y devuelve nulo para un evento sin regla', function () {
    $registro = new ReglasDeNotificacion([new ReglaContratoCreado, new ReglaTrabajoCerrado]);

    expect($registro->para(new ContratoCreado(1, 1, 'X', '1')))->toBeInstanceOf(ReglaContratoCreado::class)
        ->and($registro->para(new TrabajoCerrado(1, 1, null, 1, '1')))->toBeInstanceOf(ReglaTrabajoCerrado::class)
        ->and($registro->para(new OrdenTrabajoCreada(1, 1, 1, [1], '1')))->toBeNull()
        ->and($registro->eventos())->toBe([ContratoCreado::class, TrabajoCerrado::class]);
});

test('dos reglas para el mismo evento se rechazan al armar el registro', function () {
    new ReglasDeNotificacion([new ReglaContratoCreado, new ReglaContratoCreado]);
})->throws(LogicException::class);

// ── Convertir roles, personas y equipos en cuentas ───────────────────────────

/**
 * Fakes de los tres contratos de los que depende la resolución. Sin base:
 * lo que se prueba es la lógica de composición, no las consultas.
 *
 * @param  array<string, list<int>>  $cuentasPorRol
 * @param  array<int, array{id: int, activo: bool}>  $cuentaPorPersona
 * @param  array<int, list<int>>  $personasPorEquipo
 */
function resolvedorConFakes(array $cuentasPorRol, array $cuentaPorPersona, array $personasPorEquipo): ResolverDestinatarios
{
    $roles = new class($cuentasPorRol) implements LecturaUsuariosPorRol
    {
        /** @param array<string, list<int>> $porRol */
        public function __construct(private array $porRol) {}

        public function idsConRol(string $claveRol): array
        {
            return $this->porRol[$claveRol] ?? [];
        }
    };

    $personas = new class($cuentaPorPersona) implements LecturaUsuarioDePersona
    {
        /** @param array<int, array{id: int, activo: bool}> $porPersona */
        public function __construct(private array $porPersona) {}

        public function dePersona(int $personaId): ?DatosUsuarioDePersona
        {
            $cuenta = $this->porPersona[$personaId] ?? null;

            return $cuenta === null ? null : new DatosUsuarioDePersona($cuenta['id'], "u{$cuenta['id']}", $cuenta['activo'], 1);
        }
    };

    $equipos = new class($personasPorEquipo) implements LecturaEquipoTrabajo
    {
        public ?string $fechaConsultada = null;

        /** @param array<int, list<int>> $porEquipo */
        public function __construct(private array $porEquipo) {}

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
            $this->fechaConsultada = $fecha;

            return array_map(
                static fn (int $personaId): DatosIntegranteEquipo => new DatosIntegranteEquipo($personaId, $personaId, "P{$personaId}", 'piloto', '2026-01-01', null),
                $this->porEquipo[$equipoTrabajoId] ?? [],
            );
        }

        public function recursosAFecha(int $equipoTrabajoId, string $fecha): array
        {
            return [];
        }
    };

    return new ResolverDestinatarios($roles, $personas, $equipos);
}

test('un destinatario por rol resuelve a todas las cuentas que tienen ese rol asignado', function () {
    $resolver = resolvedorConFakes(['jefe_campo' => [4, 9], 'encargado_operaciones' => [3]], [], []);

    expect($resolver->ejecutar([Destinatario::rol(RolDestinatario::JefeCampo)]))->toBe([4, 9])
        ->and($resolver->ejecutar([Destinatario::rol(RolDestinatario::Dueno)]))->toBe([]);
});

test('una cuenta que califica por dos destinatarios recibe un solo aviso y el orden es estable', function () {
    $resolver = resolvedorConFakes(['jefe_campo' => [9, 4], 'encargado_operaciones' => [4, 3]], [], []);

    expect($resolver->ejecutar([
        Destinatario::rol(RolDestinatario::JefeCampo),
        Destinatario::rol(RolDestinatario::EncargadoOperaciones),
    ]))->toBe([3, 4, 9]);
});

test('un destinatario por persona resuelve a su cuenta; sin cuenta o bloqueada no hay a quién avisar', function () {
    $resolver = resolvedorConFakes([], [10 => ['id' => 5, 'activo' => true], 11 => ['id' => 6, 'activo' => false]], []);

    expect($resolver->ejecutar([Destinatario::persona(10)]))->toBe([5])
        ->and($resolver->ejecutar([Destinatario::persona(11)]))->toBe([])
        ->and($resolver->ejecutar([Destinatario::persona(12)]))->toBe([]);
});

test('un destinatario por equipo resuelve a las cuentas de sus integrantes vigentes hoy, salteando a quien no tiene cuenta', function () {
    $resolver = resolvedorConFakes(
        [],
        [4 => ['id' => 5, 'activo' => true], 6 => ['id' => 7, 'activo' => true], 8 => ['id' => 9, 'activo' => false]],
        [1 => [4, 6, 7, 8]],
    );

    expect($resolver->ejecutar([Destinatario::equipo(1)], '2026-09-23'))->toBe([5, 7])
        ->and($resolver->ejecutar([Destinatario::equipo(99)], '2026-09-23'))->toBe([]);
});

test('la vigencia del equipo se evalúa el día del hecho: sin fecha explícita, hoy', function () {
    Carbon::setTestNow('2026-09-23 10:00:00');

    try {
        $resolver = resolvedorConFakes([], [4 => ['id' => 5, 'activo' => true]], [1 => [4]]);
        $resolver->ejecutar([Destinatario::equipo(1)]);

        $equipos = (new ReflectionProperty($resolver, 'equipos'))->getValue($resolver);

        expect($equipos->fechaConsultada)->toBe('2026-09-23');
    } finally {
        Carbon::setTestNow();
    }
});

// ── El catálogo está completo ────────────────────────────────────────────────

test('cada tipo de aviso tiene su texto en lang/es/notificaciones.php y usa solo parámetros que las reglas entregan', function () use ($raizProyecto) {
    /** @var array{titulo: array<string, string>} $catalogo */
    $catalogo = require $raizProyecto.'/lang/es/notificaciones.php';

    $parametrosQueEntregaCadaRegla = [
        'contrato_creado' => ['cliente', 'hectareas'],
        'orden_trabajo_creada' => ['orden', 'aplicacion', 'hectareas'],
        'trabajo_cerrado' => ['orden', 'aplicacion', 'hectareas'],
    ];

    foreach (TipoNotificacion::cases() as $tipo) {
        expect($catalogo['titulo'])->toHaveKey($tipo->value);

        preg_match_all('/:([a-z]+)/', $catalogo['titulo'][$tipo->value], $usados);

        expect($parametrosQueEntregaCadaRegla)->toHaveKey($tipo->value)
            ->and(array_diff($usados[1], $parametrosQueEntregaCadaRegla[$tipo->value]))->toBe([], "«{$tipo->value}» usa un parámetro que su regla no entrega");
    }

    expect(array_keys($catalogo['titulo']))->toEqualCanonicalizing(array_map(static fn (TipoNotificacion $t): string => $t->value, TipoNotificacion::cases()));
});

test('cada rol al que una regla puede dirigirse existe en el catálogo de roles sembrado', function () use ($raizProyecto) {
    $seeder = (string) file_get_contents($raizProyecto.'/database/seeders/Catalogo/SeguridadSeeder.php');

    foreach (RolDestinatario::cases() as $rol) {
        expect($seeder)->toContain("'{$rol->value}' =>");
    }
});

test('cada recurso de un aviso tiene su valor permitido por el CHECK de la migración', function () use ($raizProyecto) {
    $migracion = (string) file_get_contents(glob($raizProyecto.'/database/migrations/*_create_ntf_notificaciones_table.php')[0]);

    foreach (TipoNotificacion::cases() as $tipo) {
        expect($migracion)->toContain("'{$tipo->value}'");
    }

    foreach (RecursoNotificable::cases() as $recurso) {
        expect($migracion)->toContain("'{$recurso->value}'");
    }
});

// ── Los eventos son DTO de primitivos que se anuncian al confirmar ───────────

arch('los eventos de dominio de la primera cadena son DTO readonly que se anuncian al confirmar la transacción')
    ->expect([ContratoCreado::class, OrdenTrabajoCreada::class, TrabajoCerrado::class])
    ->toBeReadonly()
    ->toImplement(ShouldDispatchAfterCommit::class)
    ->not->toUse('Illuminate\Database');

test('cada regla atiende un evento y todas las reglas del proveedor tienen un evento distinto', function () {
    $reglas = [new ReglaContratoCreado, new ReglaOrdenTrabajoCreada, new ReglaTrabajoCerrado];
    $eventos = array_map(static fn (ReglaNotificacion $r): string => $r->evento(), $reglas);

    expect(array_unique($eventos))->toHaveCount(3);
});
