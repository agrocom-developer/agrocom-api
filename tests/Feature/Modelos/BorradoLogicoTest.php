<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoVentana;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * ADR 0007 / invariante 8: soft delete por defecto y DELETE físico bloqueado
 * en todo modelo de dominio (criterio de aceptación de TE-03: "ningún modelo
 * permite DELETE físico").
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

dataset('modelos de dominio del núcleo comercial', [
    'Cliente' => [Cliente::class],
    'ClienteContacto' => [ClienteContacto::class],
    'Contrato' => [Contrato::class],
    'ContratoVentana' => [ContratoVentana::class],
    'Campo' => [Campo::class],
    'Lote' => [Lote::class],
    'OrdenAplicacion' => [OrdenAplicacion::class],
]);

it('delete() hace borrado lógico y saca el registro de los listados por defecto', function (string $clase) {
    $modelo = $clase::query()->firstOrFail();

    $modelo->delete();

    expect($modelo->deleted_at)->not->toBeNull()
        ->and($clase::query()->whereKey($modelo->getKey())->exists())->toBeFalse()
        ->and($clase::withTrashed()->whereKey($modelo->getKey())->exists())->toBeTrue();
})->with('modelos de dominio del núcleo comercial');

it('bloquea el borrado físico: forceDelete() lanza excepción y el registro sobrevive', function (string $clase) {
    $modelo = $clase::query()->firstOrFail();

    expect(fn () => $modelo->forceDelete())->toThrow(BorradoFisicoNoPermitido::class)
        ->and($clase::withTrashed()->whereKey($modelo->getKey())->exists())->toBeTrue();
})->with('modelos de dominio del núcleo comercial');

it('bloquea el borrado físico también vía forceDestroy() y forceDeleteQuietly()', function () {
    $orden = OrdenAplicacion::query()->firstOrFail();

    expect(fn () => OrdenAplicacion::forceDestroy($orden->getKey()))
        ->toThrow(BorradoFisicoNoPermitido::class)
        ->and(fn () => $orden->forceDeleteQuietly())
        ->toThrow(BorradoFisicoNoPermitido::class)
        ->and(OrdenAplicacion::withTrashed()->whereKey($orden->getKey())->exists())->toBeTrue();
});

it('registra created_by y updated_by desde el usuario autenticado', function () {
    $autora = SecUser::factory()->create();
    $this->actingAs($autora);

    $cliente = Cliente::query()->create([
        'razon_social' => 'Cliente Auditado S.R.L.',
        'nit' => '999888777',
        'tipo_persona' => 'juridica',
    ]);

    expect($cliente->created_by)->toBe($autora->id)
        ->and($cliente->updated_by)->toBe($autora->id);

    $editor = SecUser::factory()->create();
    $this->actingAs($editor);

    $cliente->update(['razon_social' => 'Cliente Auditado y Editado S.R.L.']);

    expect($cliente->created_by)->toBe($autora->id)
        ->and($cliente->updated_by)->toBe($editor->id);
});
