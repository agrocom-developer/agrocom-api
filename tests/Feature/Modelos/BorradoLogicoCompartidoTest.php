<?php

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * ADR 0007 / invariante 8 — mismo criterio que BorradoLogicoTest.php y
 * BorradoLogicoSeguridadPersonalTest.php, para `Configuracion` (tarea 78,
 * HU-55). `Compartido/` queda afuera de la regla arquitectónica genérica de
 * `ArquitecturaModulosTest` (aloja a `ModeloDominio` mismo, y a `Bitacora`,
 * que a propósito no lo extiende) — por eso sus modelos de negocio necesitan
 * este test explícito en vez de heredar la red de seguridad automática que sí
 * cubre al resto de los módulos.
 */

uses(RefreshDatabase::class);

function crearConfiguracionDePrueba(): Configuracion
{
    return Configuracion::query()->create([
        'clave' => 'mapas.proveedor',
        'valor' => 'google',
        'grupo' => 'mapas',
        'es_secreto' => false,
    ]);
}

it('delete() hace borrado lógico y saca la configuración de los listados por defecto', function () {
    $configuracion = crearConfiguracionDePrueba();

    $configuracion->delete();

    expect($configuracion->deleted_at)->not->toBeNull()
        ->and(Configuracion::query()->whereKey($configuracion->getKey())->exists())->toBeFalse()
        ->and(Configuracion::withTrashed()->whereKey($configuracion->getKey())->exists())->toBeTrue();
});

it('bloquea el borrado físico: forceDelete() lanza excepción y el registro sobrevive', function () {
    $configuracion = crearConfiguracionDePrueba();

    expect(fn () => $configuracion->forceDelete())->toThrow(BorradoFisicoNoPermitido::class)
        ->and(Configuracion::withTrashed()->whereKey($configuracion->getKey())->exists())->toBeTrue();
});
