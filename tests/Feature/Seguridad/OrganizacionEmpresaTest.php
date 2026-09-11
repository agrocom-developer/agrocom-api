<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosEmpresa;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * "Datos de empresa"/"Datos de contacto" de `/panel/organizacion` (11/9/2026):
 * a diferencia del resto de esa pestaña (plan de suscripción, multi-sucursal —
 * mockup sin persistencia, pivot SaaS multi-tenant sin ADR), estas dos
 * secciones SÍ persisten. Mismo criterio que "Facturación" (tarea 78): fila
 * única en `sec_datos_empresa`, permiso `seguridad.organizacion.editar`
 * compartido, bitácora completa (no es un secreto).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function empresaAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

function empresaDatosValidos(): array
{
    return [
        'nombre' => 'Agrocom SRL',
        'rubro' => 'Fumigación aérea con drones',
        'email' => 'contacto@agrocom.com.ar',
        'telefono' => '+591 700 00000',
        'direccion' => 'Av. Circunvalación 123, Santa Cruz de la Sierra',
    ];
}

it('la pestaña de organización llega con los datos de empresa guardados', function () {
    SecDatosEmpresa::query()->create(empresaDatosValidos());

    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.organizacion.index'))
        ->assertOk()
        ->assertViewIs('seguridad::pages.organizacion.index')
        ->assertViewHas('puedeEditarOrganizacion', true)
        ->assertViewHas('datosEmpresa', fn (SecDatosEmpresa $empresa) => $empresa->nombre === 'Agrocom SRL');
});

it('sin datos de empresa guardados la pestaña llega con datosEmpresa en null, sin romper', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.organizacion.index'))
        ->assertOk()
        ->assertViewHas('datosEmpresa', null);
});

it('guarda los datos de empresa por primera vez y redirige a la pestaña de organización', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->post(route('panel.organizacion.empresa.actualizar'), empresaDatosValidos());

    $respuesta->assertRedirect(route('panel.organizacion.index'));
    $respuesta->assertSessionHas('estado');

    expect(SecDatosEmpresa::query()->count())->toBe(1)
        ->and(SecDatosEmpresa::query()->first()->nombre)->toBe('Agrocom SRL');
});

it('re-guardar los datos de empresa actualiza la única fila, nunca crea una segunda', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.organizacion.empresa.actualizar'), empresaDatosValidos());

    $datos = empresaDatosValidos();
    $datos['rubro'] = 'Fumigación terrestre y aérea';
    $this->post(route('panel.organizacion.empresa.actualizar'), $datos);

    expect(SecDatosEmpresa::query()->count())->toBe(1)
        ->and(SecDatosEmpresa::query()->first()->rubro)->toBe('Fumigación terrestre y aérea');
});

it('contacto (email, teléfono, dirección) es opcional', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.organizacion.empresa.actualizar'), [
        'nombre' => 'Agrocom SRL',
        'rubro' => 'Fumigación aérea con drones',
    ])->assertRedirect(route('panel.organizacion.index'));

    expect(SecDatosEmpresa::query()->count())->toBe(1)
        ->and(SecDatosEmpresa::query()->first()->email)->toBeNull();
});

it('encargado_operaciones también puede guardar los datos de empresa', function () {
    $usuario = SecUser::factory()->create();
    $idRol = empresaAsignarRol($usuario, 'encargado_operaciones');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idRol]);

    $this->post(route('panel.organizacion.empresa.actualizar'), empresaDatosValidos())
        ->assertRedirect(route('panel.organizacion.index'));

    expect(SecDatosEmpresa::query()->count())->toBe(1);
});

it('un rol con seguridad.organizacion.ver pero sin .editar recibe 403 al intentar guardar', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = empresaAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->post(route('panel.organizacion.empresa.actualizar'), empresaDatosValidos())
        ->assertStatus(403);

    expect(SecDatosEmpresa::query()->count())->toBe(0);
});

it('rechaza el guardado si falta un campo requerido', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $datos = empresaDatosValidos();
    unset($datos['nombre']);

    $this->post(route('panel.organizacion.empresa.actualizar'), $datos)
        ->assertSessionHasErrors('nombre');

    expect(SecDatosEmpresa::query()->count())->toBe(0);
});

it('la bitácora audita los datos de empresa completos: no son un secreto', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = empresaAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.organizacion.empresa.actualizar'), empresaDatosValidos());

    $empresa = SecDatosEmpresa::query()->firstOrFail();

    $fila = Bitacora::query()
        ->where('tabla', 'sec_datos_empresa')
        ->where('registro_id', $empresa->id)
        ->where('accion', AccionBitacora::Creado)
        ->latest('id')
        ->first();

    expect($fila)->not->toBeNull()
        ->and($fila->despues['nombre'])->toBe('Agrocom SRL')
        ->and($fila->despues['rubro'])->toBe('Fumigación aérea con drones');
});
