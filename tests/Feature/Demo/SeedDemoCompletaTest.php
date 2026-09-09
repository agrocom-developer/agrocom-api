<?php

use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Brick\Math\BigDecimal;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemostracionSeeder;
use Database\Seeders\Demo\PersonalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * La demo completa —catálogo + familia demo, el mismo par que corre
 * `db:seed`— tiene que dejar una base con la que se pueda DEMOSTRAR el
 * sistema: sin pantallas vacías, sin cuentas huérfanas y con los montos
 * cuadrando desde su origen.
 *
 * Es el test que faltaba cuando `/panel/devengos` devolvía 404 en la base
 * local: el seed «funcionaba» (no tiraba excepciones) pero dejaba usuarios
 * sin persona, que es justamente lo que rompía esa pantalla.
 */
beforeEach(function () {
    Storage::fake('r2');

    $this->seed(CatalogoSeeder::class);
    $this->seed(DemostracionSeeder::class);
});

it('no deja ninguna cuenta interna sin persona vinculada', function () {
    $sinPersona = SecUser::query()
        ->where('type', 'interno')
        ->whereNull('persona_id')
        ->pluck('username')
        ->all();

    expect($sinPersona)->toBe([]);
});

it('retira las cuentas genéricas demo y admin', function () {
    expect(SecUser::query()->whereIn('username', ['demo', 'admin'])->exists())->toBeFalse();
});

it('deja toda cuenta interna con un email @agrocom.example (tarea 66)', function () {
    $sinEmail = SecUser::query()
        ->where('type', 'interno')
        ->where(function ($consulta) {
            $consulta->whereNull('email')->orWhere('email', 'not like', '%@agrocom.example');
        })
        ->pluck('username')
        ->all();

    expect($sinEmail)->toBe([]);
});

it('deja toda cuenta interna con al menos un rol asignado', function () {
    $usuarios = SecUser::query()->where('type', 'interno')->get();

    expect($usuarios)->not->toBeEmpty();

    foreach ($usuarios as $usuario) {
        expect($usuario->idsDeRoles())->not->toBeEmpty("«{$usuario->username}» quedó sin rol");
    }
});

it('deja una cuenta que abre todas las pantallas del panel', function () {
    $dueno = SecRole::query()->where('name', 'dueno')->firstOrFail();

    $usuario = SecUser::query()
        ->where('username', PersonalDemoSeeder::AUTOR)
        ->firstOrFail();

    expect($usuario->idsDeRoles())->toContain($dueno->id);

    $itemsConRuta = SecMenu::query()->whereNotNull('ruta')->count();
    $menu = app(ObtenerMenuPorRolActivo::class)->ejecutar($usuario, $dueno->id);

    $hojasVisibles = collect($menu)
        ->flatMap(fn ($modulo) => $modulo->hijos)
        ->filter(fn ($item) => $item->ruta !== null)
        ->count();

    expect($hojasVisibles)->toBe($itemsConRuta);
});

it('oculta Devengos del menú cuando el usuario no tiene persona vinculada', function () {
    $piloto = SecRole::query()->where('name', 'piloto')->firstOrFail();

    $conPersona = SecUser::query()->where('username', 'josue.haenke')->firstOrFail();

    $rutasVisibles = fn (SecUser $usuario): array => collect(app(ObtenerMenuPorRolActivo::class)->ejecutar($usuario, $piloto->id))
        ->flatMap(fn ($modulo) => $modulo->hijos)
        ->pluck('ruta')
        ->all();

    expect($rutasVisibles($conPersona))->toContain('panel.devengos.index');

    // Misma cuenta, mismo rol activo, mismo permiso — sin persona.
    $conPersona->persona_id = null;

    expect($rutasVisibles($conPersona))->not->toContain('panel.devengos.index');
});

it('siembra las 21 capturas del control remoto como evidencias con archivo real', function () {
    $evidencias = Evidencia::query()->get();

    // 21 capturas de OperacionDemoSeeder + 2 evidencias de firma de acta de
    // PortalDemoSeeder (tarea 65, HU-41: cliente.sanjorge y
    // cliente.esperanza) — este test verifica TODA evidencia sembrada por
    // DemostracionSeeder, no solo el relato de las capturas de RC.
    expect($evidencias)->toHaveCount(23);

    foreach ($evidencias as $evidencia) {
        expect(Storage::disk('r2')->exists($evidencia->archivo_url))
            ->toBeTrue("falta el archivo de la evidencia #{$evidencia->id}")
            ->and($evidencia->hash)->toHaveLength(64)
            ->and($evidencia->archivo_url)->toStartWith("evidencias/{$evidencia->tipo->value}/");
    }

    // Las cuatro secciones de la galería de evidencias tienen contenido.
    foreach (TipoEvidencia::cases() as $tipo) {
        if ($tipo === TipoEvidencia::Comprobante) {
            continue; // el único que no participa del relato de las capturas
        }

        expect(Evidencia::query()->where('tipo', $tipo)->exists())
            ->toBeTrue("ninguna evidencia de tipo {$tipo->value}");
    }
});

it('cierra cada sesión del relato de capturas con su propia captura de RC y la valida con alguien que no es su piloto', function () {
    // Las diez sesiones de `OperacionDemoSeeder`: el relato de las capturas
    // reales del control remoto, con fechas fijas de agosto. `DashboardDemoSeeder`
    // agrega actividad reciente que NO participa de ese relato (no tiene
    // captura propia), y por eso el filtro es por captura y no "todas".
    $sesiones = Sesion::query()->whereNotNull('captura_rc_id')->get();

    expect($sesiones)->toHaveCount(10);

    foreach ($sesiones as $sesion) {
        expect($sesion->estado)->toBe(EstadoSesion::Validado)
            ->and($sesion->capturaRc?->tipo)->toBe(TipoEvidencia::CapturaRc)
            // Invariante 4, a nivel de PERSONA y no de rol.
            ->and($sesion->validado_por)->not->toBe($sesion->piloto_id);
    }
});

it('deja sesiones esperando validación, que es lo que llena la cola del jefe de campo', function () {
    // `DashboardDemoSeeder` (tarea 67): sin ninguna sesión en `cerrado` la cola
    // de validación y el badge del menú salen en cero, y el dashboard del jefe
    // de campo se queda sin su sección principal.
    $pendientes = Sesion::query()->where('estado', EstadoSesion::Cerrado)->whereNull('anulada_en')->get();

    expect($pendientes)->not->toBeEmpty();

    foreach ($pendientes as $sesion) {
        expect($sesion->validado_por)->toBeNull()
            ->and($sesion->fecha_validacion)->toBeNull();
    }
});

it('siembra actividad de los últimos días para que el dashboard no abra vacío', function () {
    // El dashboard mira ventanas móviles (hectáreas por día de los últimos 14,
    // pausas del mes). Contra el relato de agosto solo, esas secciones se
    // retiran por falta de datos desde septiembre en adelante.
    $recientes = Sesion::query()->where('inicio', '>=', now()->subDays(14)->startOfDay())->count();

    expect($recientes)->toBeGreaterThan(0);
});

it('genera los devengos desde las sesiones validadas y cuadran exacto', function () {
    $devengos = DevengoPersonal::query()->get();

    expect($devengos)->not->toBeEmpty();

    foreach ($devengos as $devengo) {
        $esperado = BigDecimal::of($devengo->hectareas)->multipliedBy($devengo->tarifa_ha)->toScale(2);

        expect(BigDecimal::of($devengo->monto)->isEqualTo($esperado))
            ->toBeTrue("el devengo #{$devengo->id} no cuadra desde hectáreas × tarifa");
    }

    // Un devengo por cada persona que voló o asistió cada sesión VALIDADA:
    // el devengo se genera al validar, nunca al cerrar (invariante 3), así que
    // las sesiones que quedan en la cola no aportan ninguno.
    $esperados = Sesion::query()->where('estado', EstadoSesion::Validado)->get()
        ->sum(fn (Sesion $sesion): int => $sesion->auxiliar_id === null ? 1 : 2);

    expect($devengos)->toHaveCount($esperados);
});

it('deja datos en cada pantalla del panel que hoy abría vacía', function (string $tabla) {
    expect(DB::table($tabla)->count())->toBeGreaterThan(0);
})->with([
    'ope_drones',
    'ope_trabajos',
    'ope_actas',
    'ope_alertas',
    'ope_pausas',
    'ope_reportes_tecnicos',
    'man_baterias',
    'man_vehiculos',
    'man_generadores',
    'man_planes_mantenimiento',
    'man_ordenes_mantenimiento',
    'per_equipos_trabajo',
    'per_equipo_integrantes',
    'per_equipo_recursos',
    'inv_repuestos',
    'inv_stock',
    'inv_movimientos',
    'fin_gastos',
    'fin_rendiciones',
    'fin_combustibles',
    'fin_anticipos',
    'fin_planillas',
    'fin_planilla_detalles',
    'fin_devengos_personal',
    'com_facturas',
]);

it('es idempotente: resembrar no duplica nada', function () {
    $antes = [
        'usuarios' => SecUser::query()->count(),
        'evidencias' => Evidencia::query()->count(),
        'sesiones' => Sesion::query()->count(),
        'devengos' => DevengoPersonal::query()->count(),
        'equipos' => EquipoTrabajo::query()->count(),
        'integrantes' => EquipoIntegrante::query()->count(),
        'recursos' => EquipoRecurso::query()->count(),
    ];

    $this->seed(CatalogoSeeder::class);
    $this->seed(DemostracionSeeder::class);

    expect([
        'usuarios' => SecUser::query()->count(),
        'evidencias' => Evidencia::query()->count(),
        'sesiones' => Sesion::query()->count(),
        'devengos' => DevengoPersonal::query()->count(),
        'equipos' => EquipoTrabajo::query()->count(),
        'integrantes' => EquipoIntegrante::query()->count(),
        'recursos' => EquipoRecurso::query()->count(),
    ])->toBe($antes);
});

it('siembra dos equipos de trabajo con piloto, auxiliar y equipamiento', function () {
    $equipos = EquipoTrabajo::query()->with(['integrantes', 'recursos'])->get();

    expect($equipos)->toHaveCount(2);

    foreach ($equipos as $equipo) {
        $roles = $equipo->integrantes->pluck('rol_equipo')->map(fn ($rol) => $rol->value)->all();
        $tipos = $equipo->recursos->pluck('recurso_tipo')->map(fn ($tipo) => $tipo->value)->all();

        expect($roles)->toContain('piloto')->toContain('auxiliar')
            ->and($tipos)->toContain('dron')->toContain('vehiculo')->toContain('generador');
    }
});
