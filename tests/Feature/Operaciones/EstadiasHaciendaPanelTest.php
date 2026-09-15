<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * `GET /panel/estadias` (HU-51, tarea 74): pantalla de consulta de estadías
 * del equipo en haciendas. Gateada por `operaciones.estadia.ver` (CA
 * obligatorio: sin el permiso, 403). Solo lectura — la estadía nace en
 * `/api/sync`, nunca se crea/edita desde el panel.
 *
 * Patrón idéntico a tests/Feature/Operaciones/TrabajosPanelTest.php: helpers
 * de autenticación, fixtures de datos demo, tests de permisos y filtros.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function baseParaEstadias(): PerBase
{
    return PerBase::create(['nombre' => 'Base estadias '.uniqid()]);
}

function usuarioConRolParaEstadias(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaEstadias(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function estadiaDemo(): EstadiaHacienda
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel estadias', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $base = baseParaEstadias();

    $equipo = EquipoTrabajo::create([
        'codigo' => 'EQ-PANEL',
        'nombre' => 'Equipo panel',
        'base_id' => $base->id,
        'desde' => now()->format('Y-m-d'),
    ]);

    $persona1 = PerPersona::create(['nombre' => 'Persona 1', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $persona2 = PerPersona::create(['nombre' => 'Persona 2', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return EstadiaHacienda::create([
        'uuid_cliente' => 'uuid-estadia-panel',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T09:00:00-04:00',
        'salida' => '2026-09-01T12:00:00-04:00',
        'cierre_uuid_cliente' => 'uuid-cierre-estadia-panel',
    ]);
}

it('un usuario con el permiso ve la estadía en la pantalla', function () {
    estadiaDemo();
    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertSee(__('operaciones.estadias.titulo'));
});

it('sin el permiso operaciones.estadia.ver, la pantalla responde 403', function () {
    estadiaDemo();
    [$piloto, $idRol] = usuarioConRolParaEstadias('piloto.curioso', 'piloto');

    entrarAlPanelParaEstadias($piloto, $idRol);

    $this->get('/panel/estadias')->assertForbidden();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/estadias')->assertRedirect();
});

it('publica el ítem de menú de estadías gateado por operaciones.estadia.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.operacion.items.estadias')->sole();

    $idPermiso = (int) SecPermission::query()->where('code', 'operaciones.estadia.ver')->value('id');

    expect($itemMenu->ruta)->toBe('panel.estadias.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

it('la pantalla muestra una estadía cerrada con sus datos completos', function () {
    $estadia = estadiaDemo();
    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.datos', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertSee('01/09/2026 09:00')
        ->assertSee('01/09/2026 12:00');
});

it('muestra "en curso" cuando la salida es null', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente panel estadias en curso', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $base = baseParaEstadias();

    $equipo = EquipoTrabajo::create([
        'codigo' => 'EQ-ENCURSO',
        'nombre' => 'Equipo en curso',
        'base_id' => $base->id,
        'desde' => now()->format('Y-m-d'),
    ]);

    EstadiaHacienda::create([
        'uuid_cliente' => 'uuid-estadia-encurso',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T09:00:00-04:00',
        'salida' => null,
        'cierre_uuid_cliente' => null,
    ]);

    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.encurso', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertSee(__('operaciones.estadias.en_curso'));
});

it('sin estadías, muestra el aviso de vacío', function () {
    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.vacio', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertSee(__('operaciones.estadias.vacio'));
});

it('el filtro por equipo devuelve solo las estadías de ese equipo', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente filtro equipo', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $base = baseParaEstadias();

    $equipoA = EquipoTrabajo::create(['codigo' => 'EQ-A', 'nombre' => 'Equipo A', 'base_id' => $base->id, 'desde' => now()->format('Y-m-d')]);
    $equipoB = EquipoTrabajo::create(['codigo' => 'EQ-B', 'nombre' => 'Equipo B', 'base_id' => $base->id, 'desde' => now()->format('Y-m-d')]);

    EstadiaHacienda::create([
        'uuid_cliente' => 'uuid-estadia-a',
        'equipo_trabajo_id' => $equipoA->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T09:00:00-04:00',
        'salida' => '2026-09-01T12:00:00-04:00',
        'cierre_uuid_cliente' => 'uuid-cierre-a',
    ]);

    EstadiaHacienda::create([
        'uuid_cliente' => 'uuid-estadia-b',
        'equipo_trabajo_id' => $equipoB->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T09:00:00-04:00',
        'salida' => '2026-09-01T12:00:00-04:00',
        'cierre_uuid_cliente' => 'uuid-cierre-b',
    ]);

    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.filtro.equipo', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get("/panel/estadias?equipo_trabajo_id={$equipoA->id}")
        ->assertOk()
        ->assertViewHas('estadias', fn ($estadias) => $estadias->pluck('id')->all() === [EstadiaHacienda::where('equipo_trabajo_id', $equipoA->id)->first()->id]
            && ! $estadias->pluck('id')->contains(EstadiaHacienda::where('equipo_trabajo_id', $equipoB->id)->first()->id));
});

it('calcula correctamente los días efectivos de una estadía de 3 días (debe ser 3.0, no 1)', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente dias efectivos', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $base = baseParaEstadias();

    $equipo = EquipoTrabajo::create(['codigo' => 'EQ-DIAS', 'nombre' => 'Equipo dias', 'base_id' => $base->id, 'desde' => '2026-09-01']);

    // Exactamente 3 días: 2026-09-01 09:00 a 2026-09-04 09:00
    // Esto garantiza que diffInSeconds sea exactamente 3 * 86400 = 259200 segundos
    $entrada = Carbon::create(2026, 9, 1, 9, 0, 0);
    $salida = Carbon::create(2026, 9, 4, 9, 0, 0);

    EstadiaHacienda::create([
        'uuid_cliente' => 'uuid-estadia-3dias',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => $entrada,
        'salida' => $salida,
        'cierre_uuid_cliente' => 'uuid-cierre-3dias',
    ]);

    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.dias', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertViewHas('diasPorEquipo', fn ($dias) => isset($dias[$equipo->id]) && abs($dias[$equipo->id] - 3.0) < 0.01);
});

it('pagina el listado de estadías', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente paginacion', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $base = baseParaEstadias();

    $equipo = EquipoTrabajo::create(['codigo' => 'EQ-PAG', 'nombre' => 'Equipo paginacion', 'base_id' => $base->id, 'desde' => now()->format('Y-m-d')]);

    for ($i = 0; $i < 20; $i++) {
        EstadiaHacienda::create([
            'uuid_cliente' => "uuid-estadia-pag-$i",
            'equipo_trabajo_id' => $equipo->id,
            'propiedad_id' => $propiedad->id,
            'entrada' => now()->addDays($i)->setTime(9, 0, 0),
            'salida' => now()->addDays($i)->setTime(12, 0, 0),
            'cierre_uuid_cliente' => "uuid-cierre-pag-$i",
        ]);
    }

    [$jefe, $idRol] = usuarioConRolParaEstadias('jefe.estadias.paginacion', 'jefe_campo');

    entrarAlPanelParaEstadias($jefe, $idRol);

    $this->get('/panel/estadias')
        ->assertOk()
        ->assertViewHas('estadias', fn ($estadias) => $estadias->count() === 15 && $estadias->total() === 20);

    $this->get('/panel/estadias?page=2')
        ->assertOk()
        ->assertViewHas('estadias', fn ($estadias) => $estadias->count() === 5);
});
