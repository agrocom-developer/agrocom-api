<?php

use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaAvisoDeNegociacion;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaProveedorRegistrado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaTrabajoCerrado;
use App\Dominios\Notificaciones\Aplicacion\ReglasDeNotificacion;
use App\Dominios\Notificaciones\Aplicacion\ResolverDestinatarios;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\FormatoNotificacion;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Operaciones\Contratos\Eventos\TrabajoCerrado;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/*
 * Tarea 141 (ADR 0025) — el motor de notificaciones, lo que no necesita base
 * de datos: qué dice cada regla y a quién, cómo se convierten roles, personas
 * y equipos en cuentas, y que el catálogo de avisos está completo. Las
 * garantías que sí necesitan la base (idempotencia, aislamiento entre cuentas)
 * están en `NotificacionesEntregaTest`.
 */

$raizProyecto = dirname(__DIR__, 2);

// ── Las reglas: qué se dice, a quién y a qué recurso ─────────────────────────

test('trabajo cerrado avisa al administrador y lleva al trabajo', function () {
    $armada = (new ReglaTrabajoCerrado)->armar(new TrabajoCerrado(9, 3, '12.5'));

    expect($armada->tipo)->toBe(TipoNotificacion::TrabajoCerrado)
        ->and($armada->claveEvento)->toBe('trabajo_cerrado:9')
        ->and($armada->recurso)->toBe(RecursoNotificable::Trabajo)
        ->and($armada->recursoId)->toBe(9)
        ->and($armada->parametros)->toBe(['solicitud' => 3, 'hectareas' => '12,50'])
        ->and($armada->destinatarios)->toEqual([Destinatario::rol(RolDestinatario::Administrador)]);
});

test('la identidad del hecho es estable: el mismo hecho da la misma clave y hechos distintos, claves distintas', function () {
    $regla = new ReglaTrabajoCerrado;

    $a = $regla->armar(new TrabajoCerrado(9, 3, '12.5'));
    $otraVez = $regla->armar(new TrabajoCerrado(9, 3, '12.5'));
    $otroTrabajo = $regla->armar(new TrabajoCerrado(10, 3, '12.5'));

    expect($otraVez->claveEvento)->toBe($a->claveEvento)
        ->and($otroTrabajo->claveEvento)->not->toBe($a->claveEvento);

    // No depende del momento: una clave con la hora no sería idempotente.
    expect($a->claveEvento)->toMatch('/^[a-z_]+:\d+$/');
});

test('una regla rechaza un evento que no es el suyo', function () {
    (new ReglaTrabajoCerrado)->armar(new stdClass);
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
    $registro = new ReglasDeNotificacion([new ReglaTrabajoCerrado]);

    expect($registro->para(new TrabajoCerrado(1, 1, '1')))->toBeInstanceOf(ReglaTrabajoCerrado::class)
        ->and($registro->para(new stdClass))->toBeNull()
        ->and($registro->eventos())->toBe([TrabajoCerrado::class]);
});

test('dos reglas para el mismo evento se rechazan al armar el registro', function () {
    new ReglasDeNotificacion([new ReglaTrabajoCerrado, new ReglaTrabajoCerrado]);
})->throws(LogicException::class);

// ── Convertir roles, personas y equipos en cuentas ───────────────────────────

/**
 * Un resolvedor con la lectura de roles de mentira: el reparto no consulta ninguna base.
 *
 * @param  array<string, list<int>>  $cuentasPorRol
 */
function resolvedorConFakes(array $cuentasPorRol): ResolverDestinatarios
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

    return new ResolverDestinatarios($roles);
}

test('un destinatario por rol resuelve a todas las cuentas que tienen ese rol asignado', function () {
    $resolver = resolvedorConFakes(['administrador' => [4, 9], 'operador' => [3]]);

    expect($resolver->ejecutar([Destinatario::rol(RolDestinatario::Administrador)]))->toBe([4, 9]);
});

test('una cuenta que califica por dos destinatarios recibe un solo aviso y el orden es estable', function () {
    $resolver = resolvedorConFakes(['administrador' => [9, 4]]);

    expect($resolver->ejecutar([
        Destinatario::rol(RolDestinatario::Administrador),
        Destinatario::usuario(3),
        Destinatario::usuario(4),
    ]))->toBe([3, 4, 9]);
});

test('un destinatario por cuenta es esa cuenta, y un tipo que ya no existe no avisa a nadie', function () {
    $resolver = resolvedorConFakes([]);

    expect($resolver->ejecutar([Destinatario::usuario(5)]))->toBe([5])
        ->and($resolver->ejecutar([]))->toBe([]);
});

// ── El catálogo está completo ────────────────────────────────────────────────

test('cada tipo de aviso tiene su texto en lang/es/notificaciones.php y usa solo parámetros que las reglas entregan', function () use ($raizProyecto) {
    /** @var array{titulo: array<string, string>} $catalogo */
    $catalogo = require $raizProyecto.'/lang/es/notificaciones.php';

    $parametrosQueEntregaCadaRegla = [
        'trabajo_cerrado' => ['solicitud', 'hectareas'],
        // Marketplace (ADR 0036): un solo evento de aprobación reparte seis tipos.
        'sujeto_aprobado' => ['sujeto', 'nombre', 'motivo'],
        'sujeto_rechazado' => ['sujeto', 'nombre', 'motivo'],
        'sujeto_suspendido' => ['sujeto', 'nombre', 'motivo'],
        'sujeto_rehabilitado' => ['sujeto', 'nombre', 'motivo'],
        'sujeto_dado_de_baja' => ['sujeto', 'nombre', 'motivo'],
        'sujeto_reenviado' => ['sujeto', 'nombre', 'motivo'],
        'documento_por_vencer' => ['documento', 'sujeto', 'fecha', 'dias'],
        'documento_vencido' => ['documento', 'sujeto', 'fecha'],
        'proveedor_registrado' => ['tipo', 'nombre'],
        // Avisos del modelo anterior (se conservan para leer los ya entregados).
        'oferta_creada' => ['solicitud', 'hectareas', 'hora'],
        'oferta_vencida' => ['solicitud'],
        'oferta_retirada' => ['solicitud', 'motivo'],
        'solicitud_asignada' => ['solicitud', 'proveedor'],
        'solicitud_reasignada' => ['solicitud'],
        'solicitud_sin_proveedor' => ['solicitud'],
        'proveedor_sancionado' => ['proveedor', 'sancion', 'causa'],
        // Calificaciones, disputas y cierre del servicio (ADR 0031, 0033 y 0039).
        'solicitud_completada' => ['solicitud'],
        'calificacion_registrada' => ['solicitud', 'estrellas'],
        'calificacion_reportada' => ['solicitud', 'proveedor'],
        'disputa_abierta' => ['solicitud', 'motivo'],
        'disputa_resuelta' => ['solicitud', 'resultado', 'monto'],
        'disputa_apelada' => ['solicitud'],
        'cliente_con_aviso' => ['promedio'],
        'publicacion_limitada' => ['cliente', 'promedio'],
        'equipo_en_camino' => ['solicitud'],
        'solicitud_ejecutada' => ['solicitud'],
        // Negociación (ADR 0030, 0038 y 0041).
        'nueva_solicitud_abierta' => ['solicitud', 'hectareas', 'zona', 'precio'],
        'oferta_recibida' => ['proveedor', 'precio', 'solicitud'],
        'precio_cambiado' => ['solicitud', 'precio'],
        'solicitud_adjudicada' => ['solicitud', 'proveedor', 'precio'],
        'solicitud_reabierta' => ['solicitud'],
        'solicitud_expirada' => ['solicitud'],
        'ejecucion_iniciada' => ['solicitud'],
        'avance_registrado' => ['solicitud', 'porcentaje'],
        'solicitud_finalizada' => ['solicitud', 'monto'],
        'mensaje_nuevo' => ['solicitud'],
        'precio_final_confirmado' => ['solicitud', 'monto'],
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
    // El `create` consolidado más las migraciones que ampliaron los catálogos cerrados.
    $migracion = '';
    foreach (glob($raizProyecto.'/database/migrations/*ntf_notificaciones*.php') ?: [] as $archivo) {
        $migracion .= (string) file_get_contents($archivo);
    }

    foreach (TipoNotificacion::cases() as $tipo) {
        expect($migracion)->toContain("'{$tipo->value}'");
    }

    foreach (RecursoNotificable::cases() as $recurso) {
        expect($migracion)->toContain("'{$recurso->value}'");
    }
});

// ── Los eventos son DTO de primitivos que se anuncian al confirmar ───────────

arch('los eventos de dominio de la primera cadena son DTO readonly que se anuncian al confirmar la transacción')
    ->expect([TrabajoCerrado::class])
    ->toBeReadonly()
    ->toImplement(ShouldDispatchAfterCommit::class)
    ->not->toUse('Illuminate\Database');

test('cada regla atiende un evento y todas las reglas del proveedor tienen un evento distinto', function () {
    $reglas = [new ReglaTrabajoCerrado, new ReglaProveedorRegistrado];
    $eventos = array_map(static fn (ReglaNotificacion $r): string => $r->evento(), $reglas);

    expect(array_unique($eventos))->toHaveCount(2);
});

test('el aviso de negociación se arma con el tipo, la clave y los destinatarios que trae el evento', function () {
    $evento = new AvisoDeNegociacion('mensaje_nuevo', 'mensaje_nuevo:41:7', ['solicitud' => 41], 41, [7, 9]);
    $armada = (new ReglaAvisoDeNegociacion)->armar($evento);

    expect($armada->tipo)->toBe(TipoNotificacion::MensajeNuevo)
        ->and($armada->parametros)->toBe(['solicitud' => 41])
        ->and($armada->recursoId)->toBe(41)
        ->and($armada->claveEvento)->toBe('mensaje_nuevo:41:7')
        ->and($armada->destinatarios)->toHaveCount(2);

    expect(fn () => (new ReglaAvisoDeNegociacion)->armar(new stdClass))->toThrow(InvalidArgumentException::class);
});
