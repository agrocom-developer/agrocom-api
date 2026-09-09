<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecDatosFiscales;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 78 (HU-55) — pestaña "Facturación" de `/panel/organizacion`: a
 * diferencia del resto de esa pantalla (mockup sin persistencia, ver
 * `OrganizacionController`), esta pestaña ES real. Cubre: persistencia en
 * `sec_datos_fiscales`, fila única (nunca una segunda al re-guardar), el
 * permiso `seguridad.organizacion.editar` separado de `.ver`, y que la
 * bitácora audita estos campos completos (no son secretos, a diferencia de
 * `Configuracion`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function facturacionAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

function facturacionDatosValidos(): array
{
    return [
        'razon_social_fiscal' => 'Agrocom SRL',
        'nit' => '1023456789',
        'domicilio_fiscal' => 'Av. Circunvalación 123, Santa Cruz de la Sierra',
        'actividad_economica' => 'Fumigación aérea con drones',
        'leyenda_pie' => 'Documento válido como comprobante fiscal.',
    ];
}

it('la pestaña de facturación llega con los datos fiscales guardados y el permiso de edición', function () {
    SecDatosFiscales::query()->create(facturacionDatosValidos());

    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.organizacion.index', ['tab' => 'facturacion']))
        ->assertOk()
        ->assertViewIs('seguridad::pages.organizacion.index')
        ->assertViewHas('tabActiva', 'facturacion')
        ->assertViewHas('puedeEditarFacturacion', true)
        ->assertViewHas('datosFiscales', fn (SecDatosFiscales $fiscales) => $fiscales->nit === '1023456789');
});

it('sin datos fiscales guardados la pestaña llega con datosFiscales en null, sin romper', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.organizacion.index'))
        ->assertOk()
        ->assertViewHas('datosFiscales', null);
});

it('guarda los datos fiscales por primera vez y redirige a la pestaña de facturación', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->post(route('panel.organizacion.facturacion.actualizar'), facturacionDatosValidos());

    $respuesta->assertRedirect(route('panel.organizacion.index', ['tab' => 'facturacion']));
    $respuesta->assertSessionHas('estado');

    expect(SecDatosFiscales::query()->count())->toBe(1)
        ->and(SecDatosFiscales::query()->first()->razon_social_fiscal)->toBe('Agrocom SRL');
});

it('re-guardar los datos fiscales actualiza la única fila, nunca crea una segunda', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.organizacion.facturacion.actualizar'), facturacionDatosValidos());

    $datos = facturacionDatosValidos();
    $datos['nit'] = '9998887776';
    $this->post(route('panel.organizacion.facturacion.actualizar'), $datos);

    expect(SecDatosFiscales::query()->count())->toBe(1)
        ->and(SecDatosFiscales::query()->first()->nit)->toBe('9998887776');
});

it('encargado_operaciones también puede guardar los datos fiscales', function () {
    $usuario = SecUser::factory()->create();
    $idRol = facturacionAsignarRol($usuario, 'encargado_operaciones');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idRol]);

    $this->post(route('panel.organizacion.facturacion.actualizar'), facturacionDatosValidos())
        ->assertRedirect(route('panel.organizacion.index', ['tab' => 'facturacion']));

    expect(SecDatosFiscales::query()->count())->toBe(1);
});

it('un rol con seguridad.organizacion.ver pero sin .editar recibe 403 al intentar guardar', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = facturacionAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->post(route('panel.organizacion.facturacion.actualizar'), facturacionDatosValidos())
        ->assertStatus(403);

    expect(SecDatosFiscales::query()->count())->toBe(0);
});

it('sin seguridad.organizacion.ver, la pestaña de facturación también responde 403', function () {
    $usuario = SecUser::factory()->create();
    $idAuxiliar = facturacionAsignarRol($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idAuxiliar]);

    $this->get(route('panel.organizacion.index', ['tab' => 'facturacion']))->assertStatus(403);
});

it('rechaza el guardado si falta un campo requerido', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $datos = facturacionDatosValidos();
    unset($datos['nit']);

    $this->post(route('panel.organizacion.facturacion.actualizar'), $datos)
        ->assertSessionHasErrors('nit');

    expect(SecDatosFiscales::query()->count())->toBe(0);
});

it('la bitácora audita los datos fiscales completos: no son un secreto', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = facturacionAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->post(route('panel.organizacion.facturacion.actualizar'), facturacionDatosValidos());

    $fiscales = SecDatosFiscales::query()->firstOrFail();

    $fila = Bitacora::query()
        ->where('tabla', 'sec_datos_fiscales')
        ->where('registro_id', $fiscales->id)
        ->where('accion', AccionBitacora::Creado)
        ->latest('id')
        ->first();

    expect($fila)->not->toBeNull()
        ->and($fila->despues['nit'])->toBe('1023456789')
        ->and($fila->despues['razon_social_fiscal'])->toBe('Agrocom SRL');
});
