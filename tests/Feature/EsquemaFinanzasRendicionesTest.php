<?php

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/*
 * HU-34 (tarea 48) — invariantes 5/6/8 de CLAUDE.md: soft delete + columnas
 * de auditoría desde la primera migración de `fin_rendiciones` (mismo
 * criterio que `EsquemaNucleoComercialTest`/`EsquemaSeguridadPersonalTest`
 * para TE-03), más la columna `rendicion_id` que el `ALTER TABLE` agrega a
 * `fin_gastos`.
 */

uses(RefreshDatabase::class);

it('crea fin_rendiciones con sus columnas, soft delete y auditoría', function () {
    expect(Schema::hasTable('fin_rendiciones'))->toBeTrue()
        ->and(Schema::hasColumns('fin_rendiciones', [
            'id',
            'base_id',
            'jefe_campo_id',
            'fecha',
            'descripcion',
            'monto',
            'estado',
            'aprobado_por',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]))->toBeTrue();
});

it('agrega rendicion_id a fin_gastos por ALTER TABLE', function () {
    expect(Schema::hasColumn('fin_gastos', 'rendicion_id'))->toBeTrue();
});

it('delete() hace borrado lógico y saca la rendición de los listados por defecto', function () {
    $base = PerBase::query()->create(['nombre' => 'Base de esquema '.uniqid()]);
    $jefeCampo = PerPersona::query()->create(['nombre' => 'Jefe de esquema '.uniqid(), 'rol' => 'jefe_campo', 'activo' => true]);

    $rendicion = Rendicion::query()->create([
        'base_id' => $base->id,
        'jefe_campo_id' => $jefeCampo->id,
        'fecha' => '2026-09-01',
        'estado' => 'abierta',
        'monto' => '0.00',
    ]);

    $rendicion->delete();

    expect($rendicion->deleted_at)->not->toBeNull()
        ->and(Rendicion::query()->whereKey($rendicion->getKey())->exists())->toBeFalse()
        ->and(Rendicion::withTrashed()->whereKey($rendicion->getKey())->exists())->toBeTrue();
});

it('bloquea el borrado físico de una rendición: forceDelete() lanza excepción y el registro sobrevive', function () {
    $base = PerBase::query()->create(['nombre' => 'Base de esquema '.uniqid()]);
    $jefeCampo = PerPersona::query()->create(['nombre' => 'Jefe de esquema '.uniqid(), 'rol' => 'jefe_campo', 'activo' => true]);

    $rendicion = Rendicion::query()->create([
        'base_id' => $base->id,
        'jefe_campo_id' => $jefeCampo->id,
        'fecha' => '2026-09-01',
        'estado' => 'abierta',
        'monto' => '0.00',
    ]);

    expect(fn () => $rendicion->forceDelete())->toThrow(BorradoFisicoNoPermitido::class)
        ->and(Rendicion::withTrashed()->whereKey($rendicion->getKey())->exists())->toBeTrue();
});
