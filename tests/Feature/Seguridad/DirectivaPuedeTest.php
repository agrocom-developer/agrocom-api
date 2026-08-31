<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

/*
 * HU-02, CA "botones ocultos sin permiso" — directiva `@puede`, el mecanismo
 * con el que una vista oculta un control de acción (el menú ya llega
 * filtrado por `ObtenerMenuPorRolActivo`, pero los botones DENTRO de una
 * pantalla no pasan por ahí).
 *
 * Lo que fija esta suite es el contrato de la directiva, no una pantalla
 * concreta: se evalúa contra el ROL ACTIVO de la sesión y nunca contra la
 * unión de roles (CLAUDE.md invariante 10), y es fail-closed cuando falta
 * contexto — sin autenticar o sin rol activo, el botón NO se pinta.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

/** Un botón de acción cualquiera, envuelto en la directiva bajo prueba. */
const PLANTILLA_BOTON = "@puede('seguridad.usuario.crear')<button>Nuevo usuario</button>@endpuede";

function puedeAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('pinta el botón cuando el rol activo tiene el permiso', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = puedeAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno');
    session(['sec_rol_activo_id' => $idDueno]);

    expect(Blade::render(PLANTILLA_BOTON))->toContain('Nuevo usuario');
});

it('oculta el botón cuando el ROL ACTIVO no tiene el permiso, aunque el usuario lo tenga en otro rol asignado', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = puedeAsignarRol($usuario, 'piloto'); // sin permisos de seguridad
    puedeAsignarRol($usuario, 'dueno');              // sí tiene seguridad.usuario.crear

    $this->actingAs($usuario, 'interno');
    session(['sec_rol_activo_id' => $idPiloto]);

    // La unión diría que sí (por dueño); la directiva mira solo el rol activo.
    expect($usuario->tienePermiso('seguridad.usuario.crear'))->toBeTrue()
        ->and(Blade::render(PLANTILLA_BOTON))->not->toContain('Nuevo usuario');
});

it('oculta el botón sin usuario autenticado', function () {
    expect(Blade::render(PLANTILLA_BOTON))->not->toContain('Nuevo usuario');
});

it('oculta el botón si hay usuario pero la sesión todavía no tiene rol activo resuelto', function () {
    $usuario = SecUser::factory()->create();
    puedeAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno');

    expect(session('sec_rol_activo_id'))->toBeNull()
        ->and(Blade::render(PLANTILLA_BOTON))->not->toContain('Nuevo usuario');
});

it('oculta el botón si el rol activo de sesión quedó desactivado a nivel de catálogo', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = puedeAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno');
    session(['sec_rol_activo_id' => $idDueno]);

    SecRole::query()->whereKey($idDueno)->update(['state' => false]);

    expect(Blade::render(PLANTILLA_BOTON))->not->toContain('Nuevo usuario');
});

it('el @else de la directiva permite ofrecer la alternativa de solo lectura', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = puedeAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');
    session(['sec_rol_activo_id' => $idPiloto]);

    $html = Blade::render(
        "@puede('seguridad.usuario.crear')<button>Nuevo</button>@else<span>Solo lectura</span>@endpuede"
    );

    expect($html)->toContain('Solo lectura')->not->toContain('<button>');
});

it('@elsepuede encadena un segundo permiso, cada uno evaluado contra el rol activo', function () {
    $usuario = SecUser::factory()->create();
    $idEncargado = puedeAsignarRol($usuario, 'encargado_operaciones'); // todo salvo asignar_rol_dueno

    $this->actingAs($usuario, 'interno');
    session(['sec_rol_activo_id' => $idEncargado]);

    $html = Blade::render(
        "@puede('seguridad.usuario.asignar_rol_dueno')<button>Hacer dueño</button>".
        "@elsepuede('seguridad.usuario.editar')<button>Editar</button>@endpuede"
    );

    expect($html)->toContain('Editar')->not->toContain('Hacer dueño');
});
