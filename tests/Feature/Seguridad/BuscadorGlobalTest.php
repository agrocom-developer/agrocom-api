<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Compartido\Dominio\TerminosBusqueda;
use App\Dominios\Seguridad\Aplicacion\BuscarEnElPanel;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Buscador global del header (9/9/2026): `/panel/buscar`.
 *
 * Lo que se defiende acá son las tres promesas que se le hicieron al usuario
 * —multi-palabra en cualquier orden, coincidencia a mitad de una oración, y
 * sin importar acentos ni mayúsculas— y la que no se ve pero importa más: que
 * un resultado nunca muestre algo que el ROL ACTIVO no podría abrir por menú
 * (CLAUDE.md invariante 10).
 *
 * Ojo con el motor: la suite corre en SQLite y el panel en PostgreSQL. Las dos
 * primeras promesas se prueban en los dos motores; la de los acentos necesita
 * `unaccent`, que solo existe en Postgres, así que ese test se SALTEA en
 * SQLite en vez de dar un verde que no probó nada.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioBuscador(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarABuscar(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** `Comercial` no tiene factories: sus tests crean así (ver GestionCamposPanelTest). */
function cliente(string $razonSocial): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial]);
}

/** @return list<string> Títulos del bloque `clientes`, o [] si no hubo bloque. */
function clientesEncontrados(SecUser $usuario, int $idRol, string $consulta): array
{
    $bloques = app(BuscarEnElPanel::class)->ejecutar($usuario, $idRol, TerminosBusqueda::desde($consulta));

    foreach ($bloques as $bloque) {
        if ($bloque->clave === 'clientes') {
            return array_map(fn ($resultado) => $resultado->titulo, $bloque->resultados);
        }
    }

    return [];
}

// --- Cómo se parte lo que el usuario escribió ---------------------------

it('parte la consulta en palabras y descarta las de una sola letra', function () {
    expect(TerminosBusqueda::desde('  Estancia   La  Esperanza ')->terminos)
        ->toBe(['estancia', 'la', 'esperanza']);

    // `%a%` no descarta ninguna fila: sumarla solo ensucia el resultado.
    expect(TerminosBusqueda::desde('a esperanza')->terminos)->toBe(['esperanza']);
});

it('trata como vacía una consulta que no dejó ningún término útil', function () {
    expect(TerminosBusqueda::desde(null)->vacia())->toBeTrue()
        ->and(TerminosBusqueda::desde('   ')->vacia())->toBeTrue()
        ->and(TerminosBusqueda::desde('a b c')->vacia())->toBeTrue()
        ->and(TerminosBusqueda::desde('ab')->vacia())->toBeFalse();
});

it('corta la consulta en el tope de términos', function () {
    $consulta = implode(' ', array_map(fn (int $i): string => "palabra{$i}", range(1, 20)));

    expect(TerminosBusqueda::desde($consulta)->terminos)
        ->toHaveCount(TerminosBusqueda::MAXIMO_TERMINOS);
});

// --- Las tres promesas del motor ----------------------------------------

it('encuentra con varias palabras en cualquier orden', function () {
    [$usuario, $idRol] = usuarioBuscador('busca.multipalabra', 'dueno');
    cliente('Estancia La Esperanza S.R.L.');
    cliente('Agropecuaria San Jorge S.R.L.');

    expect(clientesEncontrados($usuario, $idRol, 'estancia esperanza'))
        ->toBe(['Estancia La Esperanza S.R.L.']);

    // El orden no cambia el resultado: son `AND`, no una frase.
    expect(clientesEncontrados($usuario, $idRol, 'esperanza estancia'))
        ->toBe(['Estancia La Esperanza S.R.L.']);

    // Y exige TODAS: una palabra que no está deja la búsqueda sin resultado.
    expect(clientesEncontrados($usuario, $idRol, 'estancia jorge'))->toBe([]);
});

it('encuentra una palabra ubicada en medio de otra', function () {
    [$usuario, $idRol] = usuarioBuscador('busca.infix', 'dueno');
    cliente('Estancia La Esperanza S.R.L.');

    // Es el caso que descarta full-text: `tsvector` indexa palabras enteras y
    // "peranza" no encontraría nada.
    expect(clientesEncontrados($usuario, $idRol, 'peranza'))
        ->toBe(['Estancia La Esperanza S.R.L.']);
});

it('ignora mayúsculas y minúsculas', function () {
    [$usuario, $idRol] = usuarioBuscador('busca.mayusculas', 'dueno');
    cliente('Estancia La Esperanza S.R.L.');

    expect(clientesEncontrados($usuario, $idRol, 'ESPERANZA'))
        ->toBe(['Estancia La Esperanza S.R.L.']);
});

it('ignora los acentos', function () {
    [$usuario, $idRol] = usuarioBuscador('busca.acentos', 'dueno');
    cliente('Agrícola San Marcos S.R.L.');

    // Sin acento en la consulta, con acento en el dato...
    expect(clientesEncontrados($usuario, $idRol, 'agricola'))
        ->toBe(['Agrícola San Marcos S.R.L.']);

    // ...y al revés.
    expect(clientesEncontrados($usuario, $idRol, 'agrícola'))
        ->toBe(['Agrícola San Marcos S.R.L.']);
})->skip(
    fn (): bool => DB::connection()->getDriverName() !== 'pgsql',
    'unaccent() es de PostgreSQL: en SQLite el buscador cae a LOWER() LIKE y esto no se puede probar.',
);

it('trata los comodines de SQL como texto literal', function () {
    [$usuario, $idRol] = usuarioBuscador('busca.comodines', 'dueno');
    cliente('Estancia La Esperanza S.R.L.');

    // Sin escapar, `%%` devolvería la tabla entera.
    expect(clientesEncontrados($usuario, $idRol, '%%'))->toBe([]);
});

// --- Lo que no se ve: permisos del rol activo ---------------------------

it('no devuelve un bloque que el rol activo no tiene permiso de ver', function () {
    cliente('Estancia La Esperanza S.R.L.');

    // El dueño ve clientes...
    [$dueno, $idRolDueno] = usuarioBuscador('ve.clientes', 'dueno');
    expect(clientesEncontrados($dueno, $idRolDueno, 'esperanza'))->toHaveCount(1);

    // ...el piloto no, y el buscador no es una puerta lateral para verlos.
    [$piloto, $idRolPiloto] = usuarioBuscador('no.ve.clientes', 'piloto');
    expect($piloto->tienePermisoEnRol('comercial.cliente.ver', $idRolPiloto))->toBeFalse()
        ->and(clientesEncontrados($piloto, $idRolPiloto, 'esperanza'))->toBe([]);
});

// --- La pantalla ---------------------------------------------------------

it('la pantalla de resultados muestra un bloque por entidad con coincidencias', function () {
    [$usuario, $idRol] = usuarioBuscador('mira.resultados', 'dueno');
    cliente('Estancia La Esperanza S.R.L.');
    entrarABuscar($usuario, $idRol);

    $this->get(route('panel.buscar', ['q' => 'esperanza']))
        ->assertOk()
        ->assertSee(__('busqueda.bloques.clientes'))
        ->assertSee('Estancia La Esperanza S.R.L.');
});

it('la pantalla sin consulta no busca nada y explica cómo se usa', function () {
    [$usuario, $idRol] = usuarioBuscador('entra.sin.consulta', 'dueno');
    entrarABuscar($usuario, $idRol);

    $this->get(route('panel.buscar'))
        ->assertOk()
        ->assertSee(__('busqueda.inicial_titulo'));
});

it('la pantalla avisa cuando no hubo ninguna coincidencia', function () {
    [$usuario, $idRol] = usuarioBuscador('sin.coincidencias', 'dueno');
    entrarABuscar($usuario, $idRol);

    $this->get(route('panel.buscar', ['q' => 'zzzzzznoexiste']))
        ->assertOk()
        ->assertSee(__('busqueda.vacio_titulo', ['consulta' => 'zzzzzznoexiste']));
});

it('el buscador del header apunta a la pantalla de resultados', function () {
    [$usuario, $idRol] = usuarioBuscador('mira.el.header', 'dueno');
    entrarABuscar($usuario, $idRol);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('action="'.route('panel.buscar').'"', false)
        ->assertSee('name="q"', false);
});
