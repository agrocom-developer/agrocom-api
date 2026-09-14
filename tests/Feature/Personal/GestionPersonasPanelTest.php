<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Aplicacion\ActualizarPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
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

/*
 * HU-26 (tarea 37): administración de personas operativas, con su rol
 * operativo, base opcional y tarifa por hectárea. Permisos evaluados contra
 * el ROL ACTIVO de la sesión, nunca la unión de los roles del usuario
 * (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Personal/GestionBasesPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaPersonas(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaPersonas(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadPersona(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Ana Piloto',
        'rol' => RolOperativoPersona::Piloto->value,
        'base_id' => '',
        'tarifa_ha' => '150.00',
        'activo' => '1',
    ], $overrides);
}

it('da de alta una persona con nombre, rol, base y tarifa', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.personas.store'), payloadPersona(['base_id' => (string) $base->id]))
        ->assertRedirect(route('panel.personas.index'));

    $persona = PerPersona::query()->where('nombre', 'Ana Piloto')->sole();

    expect($persona->rol)->toBe(RolOperativoPersona::Piloto)
        ->and($persona->base_id)->toBe($base->id)
        ->and($persona->tarifa_ha)->toBe('150.00')
        ->and($persona->activo)->toBeTrue();
});

it('da de alta una persona sin base asignada', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $this->post(route('panel.personas.store'), payloadPersona())
        ->assertRedirect(route('panel.personas.index'));

    $persona = PerPersona::query()->where('nombre', 'Ana Piloto')->sole();
    expect($persona->base_id)->toBeNull();
});

it('rechaza un rol fuera del enum sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $this->post(route('panel.personas.store'), payloadPersona(['rol' => 'astronauta']))
        ->assertSessionHasErrors('rol');

    expect(PerPersona::query()->where('nombre', 'Ana Piloto')->exists())->toBeFalse();
});

it('rechaza una base_id inexistente sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $this->post(route('panel.personas.store'), payloadPersona(['base_id' => '999999']))
        ->assertSessionHasErrors('base_id');

    expect(PerPersona::query()->where('nombre', 'Ana Piloto')->exists())->toBeFalse();
});

it('rechaza una base_id de una base ya dada de baja sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Borrada']);
    $base->delete();

    $this->post(route('panel.personas.store'), payloadPersona(['base_id' => (string) $base->id]))
        ->assertSessionHasErrors('base_id');

    expect(PerPersona::query()->where('nombre', 'Ana Piloto')->exists())->toBeFalse();
});

it('registra en bitácora el alta, la edición y la baja de una persona', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $this->post(route('panel.personas.store'), payloadPersona());

    $persona = PerPersona::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'per_personas')
        ->where('registro_id', $persona->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['nombre'])->toBe('Ana Piloto');

    $this->put(
        route('panel.personas.update', $persona),
        payloadPersona(['nombre' => 'Ana Piloto B']),
    )->assertRedirect(route('panel.personas.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'per_personas')
        ->where('registro_id', $persona->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['nombre'])->toBe('Ana Piloto B');

    $this->delete(route('panel.personas.destroy', $persona))
        ->assertRedirect(route('panel.personas.index'));

    Bitacora::query()
        ->where('tabla', 'per_personas')
        ->where('registro_id', $persona->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una persona por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaPersonas('encargado', 'encargado_operaciones');
    entrarAlPanelParaPersonas($encargado, $idRol);

    $this->post(route('panel.personas.store'), payloadPersona());
    $persona = PerPersona::query()->sole();

    $this->delete(route('panel.personas.destroy', $persona))
        ->assertRedirect(route('panel.personas.index'));

    $borrada = PerPersona::withTrashed()->findOrFail($persona->id);
    expect($borrada->trashed())->toBeTrue();

    $this->get(route('panel.personas.index'))
        ->assertOk()
        ->assertDontSee('Ana Piloto');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.personas.destroy', $persona))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaPersonas('piloto.curioso', 'piloto');
    entrarAlPanelParaPersonas($piloto, $idRol);

    $persona = PerPersona::query()->create(['nombre' => 'Persona Existente', 'rol' => RolOperativoPersona::Auxiliar, 'activo' => true]);

    $this->get(route('panel.personas.index'))->assertForbidden();
    $this->get(route('panel.personas.create'))->assertForbidden();
    $this->post(route('panel.personas.store'), payloadPersona())->assertForbidden();
    $this->get(route('panel.personas.edit', $persona))->assertForbidden();
    $this->put(route('panel.personas.update', $persona), payloadPersona())->assertForbidden();
    $this->delete(route('panel.personas.destroy', $persona))->assertForbidden();

    expect(PerPersona::query()->count())->toBe(1)
        ->and($persona->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaPersonas('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaPersonas($multirol, $idPiloto);
    $this->post(route('panel.personas.store'), payloadPersona())->assertForbidden();
    expect(PerPersona::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaPersonas($multirol, $idEncargado);
    $this->post(route('panel.personas.store'), payloadPersona())->assertRedirect();
    expect(PerPersona::query()->count())->toBe(1);
});

it('publica el ítem de menú de personal gateado por personal.persona.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.personal')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'personal.persona.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.personas.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

/*
 * Congelamiento de devengo: GenerarDevengosSesion copia `tarifa_ha` al
 * DevengoPersonal en el momento de validarse la sesión, nunca la relee de
 * per_personas después (app/Dominios/Finanzas/Aplicacion/GenerarDevengosSesion.php:67-89).
 * ActualizarPersona no necesita ninguna guarda especial contra devengos
 * históricos — este test CONFIRMA ese comportamiento ya existente, no
 * prueba una guarda nueva.
 */

function trabajoAbiertoParaCongelamientoDeTarifa(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de congelamiento', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de congelamiento']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-CONGELA', 'hectareas' => '50.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '50.00']);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-congela-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

it('cambiar la tarifa_ha de una persona no altera un devengo ya generado', function () {
    $piloto = PerPersona::create([
        'nombre' => 'Piloto con devengo',
        'rol' => RolOperativoPersona::Piloto,
        'tarifa_ha' => '150.00',
        'activo' => true,
    ]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $trabajo = trabajoAbiertoParaCongelamientoDeTarifa();

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-congela-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-congela-'.uniqid(),
    ]);

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    $devengo = DevengoPersonal::query()->where('sesion_id', $sesion->id)->where('persona_id', $piloto->id)->sole();
    expect($devengo->tarifa_ha)->toBe('150.00')
        ->and($devengo->monto)->toBe('1800.00');

    // El encargado edita la tarifa de la persona DESPUÉS de que el devengo
    // ya se generó — vía el mismo caso de uso que usa el panel.
    (new ActualizarPersona)->ejecutar($piloto, $piloto->nombre, $piloto->rol, $piloto->base_id, '999.00', $piloto->activo);

    expect($piloto->fresh()?->tarifa_ha)->toBe('999.00');

    $devengoTrasEdicion = DevengoPersonal::query()->findOrFail($devengo->id);
    expect($devengoTrasEdicion->tarifa_ha)->toBe('150.00')
        ->and($devengoTrasEdicion->monto)->toBe('1800.00');
});
