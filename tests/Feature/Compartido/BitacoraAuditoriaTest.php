<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * ADR 0007 / invariante 9 de CLAUDE.md — prueba de punta a punta de
 * `RegistraBitacora` + `BitacoraObserver`: una mutación real sobre un modelo
 * que lleva el trait deja su fila en `plt_bitacoras` con actor, acción y el
 * antes/después de lo que cambió. Qué modelos están OBLIGADOS a llevar el
 * trait lo vigila `tests/Unit/BitacoraAuditoriaTest.php`; este archivo
 * vigila que, una vez enlazado, el mecanismo registre lo que promete.
 */

uses(RefreshDatabase::class);

function bitacoraDe(string $tabla, int $registroId, AccionBitacora $accion): ?Bitacora
{
    return Bitacora::query()
        ->where('tabla', $tabla)
        ->where('registro_id', $registroId)
        ->where('accion', $accion)
        ->latest('id')
        ->first();
}

it('registra actor y acción al crear un rol, con el después completo y sin antes', function () {
    $actor = SecUser::factory()->create();
    $this->actingAs($actor, 'interno');

    $rol = SecRole::query()->create([
        'name' => 'auditor',
        'description' => 'Rol de prueba',
        'state' => true,
    ]);

    $fila = bitacoraDe('sec_role', $rol->id, AccionBitacora::Creado);

    expect($fila)->not->toBeNull()
        ->and($fila->user_id)->toBe($actor->id)
        ->and($fila->antes)->toBeNull()
        ->and($fila->despues['name'])->toBe('auditor')
        ->and($fila->despues['state'])->toBeTrue();
});

it('registra solo las columnas que cambiaron al actualizar, no la fila entera', function () {
    $actor = SecUser::factory()->create();
    $this->actingAs($actor, 'interno');

    $rol = SecRole::query()->create([
        'name' => 'auditor',
        'description' => 'Rol de prueba',
        'state' => true,
    ]);

    $rol->description = 'Rol de prueba actualizado';
    $rol->save();

    $fila = bitacoraDe('sec_role', $rol->id, AccionBitacora::Actualizado);

    expect($fila)->not->toBeNull()
        ->and($fila->user_id)->toBe($actor->id)
        ->and($fila->antes)->toBe(['description' => 'Rol de prueba'])
        ->and($fila->despues)->toBe(['description' => 'Rol de prueba actualizado']);
});

it('registra el borrado lógico con el antes/después de deleted_at', function () {
    $actor = SecUser::factory()->create();
    $this->actingAs($actor, 'interno');

    $rol = SecRole::query()->create([
        'name' => 'auditor',
        'description' => 'Rol de prueba',
        'state' => true,
    ]);

    $rol->delete();

    $fila = bitacoraDe('sec_role', $rol->id, AccionBitacora::Eliminado);

    expect($fila)->not->toBeNull()
        ->and($fila->user_id)->toBe($actor->id)
        ->and($fila->antes)->toBe(['deleted_at' => null])
        ->and($fila->despues['deleted_at'])->not->toBeNull();
});

it('registra que la contraseña cambió, actor y acción, pero nunca su contenido', function () {
    $actor = SecUser::factory()->create();
    $this->actingAs($actor, 'interno');

    $usuario = SecUser::factory()->create();

    $usuario->password = 'una-contrasena-nueva';
    $usuario->save();

    $fila = bitacoraDe('sec_user', $usuario->id, AccionBitacora::Actualizado);

    // La fila existe (quién y cuándo quedan registrados) pero, al ser
    // `password` la única columna que cambió, antes/después quedan en NULL:
    // no hay nada seguro que mostrar del cambio, y la bitácora nunca inventa
    // un valor solo para no dejar el campo vacío.
    expect($fila)->not->toBeNull()
        ->and($fila->user_id)->toBe($actor->id)
        ->and($fila->antes)->toBeNull()
        ->and($fila->despues)->toBeNull();
});

it('sin usuario autenticado el actor queda en null, para seeders y comandos', function () {
    $rol = SecRole::query()->create([
        'name' => 'auditor_sin_actor',
        'description' => 'Rol de prueba',
        'state' => true,
    ]);

    $fila = bitacoraDe('sec_role', $rol->id, AccionBitacora::Creado);

    expect($fila)->not->toBeNull()
        ->and($fila->user_id)->toBeNull();
});
