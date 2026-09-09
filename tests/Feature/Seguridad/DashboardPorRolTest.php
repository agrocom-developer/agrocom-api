<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * Tarea 67 — el dashboard dejó de ser una maqueta: cada sección la sirve el
 * módulo dueño por su contrato de lectura, y QUÉ secciones aparecen lo decide
 * el ROL ACTIVO.
 *
 * Lo que estos tests defienden, en orden de importancia:
 *
 * 1. Que el dashboard REFLEJE la base: si valido una sesión o registro una
 *    pausa, el request siguiente lo muestra. Es la diferencia entre leer y
 *    fingir, y es lo único que impide que vuelva a haber cifras escritas a
 *    mano.
 * 2. Que un rol no vea por la ventana del dashboard lo que no puede ver por
 *    su pantalla propia (el hallazgo de la tarea 62, ahora por sección).
 * 3. Que las secciones "mías" exijan una persona vinculada.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

/** Asigna un rol y devuelve su id. */
function dashboardRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

/**
 * Escenario mínimo con el que el dashboard tiene algo que mostrar.
 *
 * Se apoya en `DemoSeeder` —la familia mínima: cuadrilla, cliente, contrato,
 * campo, lotes con geometría y una orden vigente— en vez de rearmar el
 * esquema a mano: es el mismo punto de partida que usan los tests de la API,
 * y así un cambio de columnas no obliga a tocar este archivo. Encima de eso
 * agrega UNA sesión cerrada, que es lo que el dashboard mira.
 *
 * @return array{sesion: Sesion, piloto: PerPersona, validador: PerPersona, lote: Lote}
 */
function dashboardEscenario(): array
{
    test()->seed(DemoSeeder::class);

    $lote = Lote::query()->where('codigo', 'L-01')->firstOrFail();
    $orden = OrdenAplicacion::query()->firstOrFail();
    // Persona nueva y no una de la demo: `sec_user.persona_id` es UNIQUE y
    // las personas sembradas ya tienen su cuenta, así que reusarlas impide
    // crear el usuario de prueba.
    $piloto = PerPersona::query()->create([
        'nombre' => 'Piloto del tablero',
        'rol' => RolOperativoPersona::Piloto,
        'activo' => true,
        'tarifa_ha' => '12.00',
    ]);

    $validador = PerPersona::query()->where('rol', RolOperativoPersona::EncargadoOperaciones)->orderBy('id')->firstOrFail();

    $trabajo = Trabajo::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'inicio' => now()->subDays(2),
    ]);

    $maquina = app(MaquinaEstadosSesion::class);

    $sesion = $maquina->abrir([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => now()->subDay()->setTime(9, 0)->toDateTimeString(),
    ]);

    $maquina->cerrar($sesion, (string) Str::uuid(), now()->subDay()->setTime(10, 30)->toDateTimeString(), 'completado', '25.00');

    return ['sesion' => $sesion->fresh(), 'piloto' => $piloto, 'validador' => $validador, 'lote' => $lote];
}

/** Entra al panel con un rol activo ya fijado y devuelve las secciones visibles. */
function dashboardVisibles(SecUser $usuario, int $idRol): array
{
    $respuesta = test()->actingAs($usuario, 'interno')
        ->withSession(['sec_rol_activo_id' => $idRol])
        ->get(route('panel.dashboard'));

    $respuesta->assertOk();

    return $respuesta->viewData('visibles');
}

// --- 1. El dashboard lee la base -----------------------------------------

it('valida una sesión y el dashboard lo refleja en el request siguiente', function () {
    $escenario = dashboardEscenario();

    $usuario = SecUser::factory()->create();
    $idDueno = dashboardRol($usuario, 'dueno');

    // Antes de validar: la sesión está en la cola y no hay hectáreas del día.
    $visibles = dashboardVisibles($usuario, $idDueno);

    expect($visibles)->toContain('cola_validacion')
        ->not->toContain('hectareas_por_dia');

    // Validar es lo único que genera devengo (invariante 3) y lo que saca la
    // sesión de la cola.
    app(ValidarSesion::class)->ejecutar($escenario['sesion'], $escenario['validador']->id);

    $visibles = dashboardVisibles($usuario, $idDueno);

    expect($visibles)->not->toContain('cola_validacion')
        ->toContain('hectareas_por_dia');
});

it('registra una pausa con causa y el dashboard la suma en el request siguiente', function () {
    $escenario = dashboardEscenario();

    $usuario = SecUser::factory()->create();
    $idDueno = dashboardRol($usuario, 'dueno');

    expect(dashboardVisibles($usuario, $idDueno))->not->toContain('pausas');

    $pausa = new Pausa([
        'sesion_id' => $escenario['sesion']->id,
        'causa' => CausaPausa::Clima,
        'inicio' => now()->subDay()->setTime(9, 20)->toDateTimeString(),
        'fin' => now()->subDay()->setTime(9, 35)->toDateTimeString(),
        'duracion_minutos' => 15,
    ]);
    $pausa->created_by = $usuario->id;
    $pausa->updated_by = $usuario->id;
    $pausa->save();

    $respuesta = test()->actingAs($usuario, 'interno')
        ->withSession(['sec_rol_activo_id' => $idDueno])
        ->get(route('panel.dashboard'));

    $pausas = $respuesta->viewData('secciones')['pausas'];

    expect($pausas['total_minutos'])->toBe(15)
        ->and($pausas['por_causa'][CausaPausa::Clima->value])->toBe(15);
});

it('el mapa dibuja los lotes reales con geometría, no una maqueta', function () {
    $escenario = dashboardEscenario();

    $usuario = SecUser::factory()->create();
    $idDueno = dashboardRol($usuario, 'dueno');

    $respuesta = test()->actingAs($usuario, 'interno')
        ->withSession(['sec_rol_activo_id' => $idDueno])
        ->get(route('panel.dashboard'));

    $mapa = $respuesta->viewData('secciones')['mapa'];
    $features = collect($mapa['lotes']['features']);

    // Un polígono por lote con geometría cargada — los tres de la demo, ni uno
    // inventado ni uno de menos.
    expect($features)->toHaveCount(Lote::query()->whereNotNull('geometria')->count());

    $delLote = $features->first(
        fn (array $feature) => str_contains($feature['properties']['nombre'], $escenario['lote']->codigo)
    );

    expect($delLote)->not->toBeNull()
        ->and($delLote['geometry'])->toBe($escenario['lote']->geometria)
        // El lote donde se voló queda pendiente de validar (warning), no
        // "sin sesiones": el color sale del estado real de sus sesiones.
        ->and($delLote['properties']['tono'])->toBe('warning')
        // El centro sale del promedio de los vértices REALES de todos los
        // lotes, no de una coordenada fija: se recalcula acá desde la misma
        // geometría para que el test no dependa de números copiados.
        ->and($mapa['centro'])->toEqual(centroDeLotes(Lote::query()->whereNotNull('geometria')->get()->all()));
});

it('no arma el mapa cuando ningún lote tiene geometría cargada', function () {
    dashboardEscenario();
    Lote::query()->update(['geometria' => null]);

    $usuario = SecUser::factory()->create();
    $idDueno = dashboardRol($usuario, 'dueno');

    // Sin perímetros no hay mapa: la sección se retira en vez de dibujar un
    // lienzo vacío o inventar un polígono.
    expect(dashboardVisibles($usuario, $idDueno))->not->toContain('mapa');
});

// --- 2. Cada sección detrás del permiso de su propia pantalla -------------

it('un jefe de campo recibe 200 sin las secciones comerciales ni de inventario', function () {
    dashboardEscenario();

    $usuario = SecUser::factory()->create();
    $idJefe = dashboardRol($usuario, 'jefe_campo');

    $visibles = dashboardVisibles($usuario, $idJefe);

    // Tiene `operaciones.trabajo.ver` y `operaciones.sesion.validar`…
    expect($visibles)->toContain('cola_validacion')
        ->toContain('mapa')
        // …pero no `comercial.contrato.ver` ni `inventario.movimiento.ver`.
        ->not->toContain('avance_clientes')
        ->not->toContain('stock');
});

it('un piloto ve solo lo suyo: sus sesiones, sus equipos y su liquidación', function () {
    $escenario = dashboardEscenario();

    $usuario = SecUser::factory()->create(['persona_id' => $escenario['piloto']->id]);
    $idPiloto = dashboardRol($usuario, 'piloto');

    $visibles = dashboardVisibles($usuario, $idPiloto);

    expect($visibles)->toContain('mis_sesiones')
        ->not->toContain('mapa')
        ->not->toContain('cola_validacion')
        ->not->toContain('distribucion_sesiones')
        ->not->toContain('avance_clientes')
        ->not->toContain('stock')
        ->not->toContain('alertas');
});

it('un dueño operando como piloto ve el tablero del piloto, no el suyo', function () {
    $escenario = dashboardEscenario();

    // Invariante 10: los permisos efectivos son los del ROL ACTIVO, nunca la
    // unión de los roles del usuario.
    $usuario = SecUser::factory()->create(['persona_id' => $escenario['piloto']->id]);
    dashboardRol($usuario, 'dueno');
    $idPiloto = dashboardRol($usuario, 'piloto');

    expect(dashboardVisibles($usuario, $idPiloto))->not->toContain('mapa')
        ->not->toContain('avance_clientes');
});

// --- 3. Las secciones "mías" exigen persona -------------------------------

it('una cuenta sin persona vinculada no recibe las secciones propias', function () {
    dashboardEscenario();

    $usuario = SecUser::factory()->create(['persona_id' => null]);
    $idDueno = dashboardRol($usuario, 'dueno');

    $visibles = dashboardVisibles($usuario, $idDueno);

    // No son datos de la empresa: son el recibo de una persona. Sin persona
    // no hay de quién.
    expect($visibles)->not->toContain('mis_sesiones')
        ->not->toContain('mis_equipos')
        ->not->toContain('mi_liquidacion');
});

// --- 4. Dinero y hectáreas, nunca float ----------------------------------

it('las hectáreas y el dinero del dashboard viajan como string decimal', function () {
    $escenario = dashboardEscenario();
    app(ValidarSesion::class)->ejecutar($escenario['sesion'], $escenario['validador']->id);

    $usuario = SecUser::factory()->create(['persona_id' => $escenario['piloto']->id]);
    $idDueno = dashboardRol($usuario, 'dueno');

    $respuesta = test()->actingAs($usuario, 'interno')
        ->withSession(['sec_rol_activo_id' => $idDueno])
        ->get(route('panel.dashboard'));

    $secciones = $respuesta->viewData('secciones');

    // Invariante 6: la hectárea es dinero y tiene que poder recalcularse
    // exacto desde el origen. Un float en el camino la vuelve irreproducible.
    foreach ($secciones['hectareas_por_dia']['valores'] as $valor) {
        expect($valor)->toBeString();
    }

    expect($secciones['resumen_por_lote'][0]['hectareasAplicadas'])->toBe('25.00')
        ->and($secciones['mi_liquidacion']['total'])->toBeString()
        ->and($secciones['mi_liquidacion']['anticipos']['saldo'])->toBeString();
});

/**
 * Promedio de los vértices de todos los lotes — la misma cuenta que
 * `LecturaPanelComercial::centroOperativo()`, escrita aparte para que el test
 * compare contra el dato y no contra un número copiado del seeder.
 *
 * @param  list<Lote>  $lotes
 * @return array{lat: float, lng: float}
 */
function centroDeLotes(array $lotes): array
{
    $vertices = [];

    foreach ($lotes as $lote) {
        foreach ($lote->geometria['coordinates'][0] as $vertice) {
            $vertices[] = $vertice;
        }
    }

    return [
        'lat' => array_sum(array_column($vertices, 1)) / count($vertices),
        'lng' => array_sum(array_column($vertices, 0)) / count($vertices),
    ];
}
