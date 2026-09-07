<?php

use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
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

    expect($evidencias)->toHaveCount(21);

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

it('cierra cada sesión con su propia captura de RC y la valida con alguien que no es su piloto', function () {
    $sesiones = Sesion::query()->get();

    expect($sesiones)->toHaveCount(10);

    foreach ($sesiones as $sesion) {
        expect($sesion->estado)->toBe(EstadoSesion::Validado)
            ->and($sesion->captura_rc_id)->not->toBeNull("la sesión #{$sesion->id} se cerró sin captura de RC")
            ->and($sesion->capturaRc?->tipo)->toBe(TipoEvidencia::CapturaRc)
            // Invariante 4, a nivel de PERSONA y no de rol.
            ->and($sesion->validado_por)->not->toBe($sesion->piloto_id);
    }
});

it('genera los devengos desde las sesiones validadas y cuadran exacto', function () {
    $devengos = DevengoPersonal::query()->get();

    expect($devengos)->not->toBeEmpty();

    foreach ($devengos as $devengo) {
        $esperado = BigDecimal::of($devengo->hectareas)->multipliedBy($devengo->tarifa_ha)->toScale(2);

        expect(BigDecimal::of($devengo->monto)->isEqualTo($esperado))
            ->toBeTrue("el devengo #{$devengo->id} no cuadra desde hectáreas × tarifa");
    }

    // Un devengo por cada persona que voló o asistió cada sesión validada.
    $esperados = Sesion::query()->get()
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
    'man_planes_mantenimiento',
    'man_ordenes_mantenimiento',
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
    ];

    $this->seed(CatalogoSeeder::class);
    $this->seed(DemostracionSeeder::class);

    expect([
        'usuarios' => SecUser::query()->count(),
        'evidencias' => Evidencia::query()->count(),
        'sesiones' => Sesion::query()->count(),
        'devengos' => DevengoPersonal::query()->count(),
    ])->toBe($antes);
});
