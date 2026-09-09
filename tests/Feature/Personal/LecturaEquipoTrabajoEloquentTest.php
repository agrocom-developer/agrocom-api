<?php

use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Personal\Contratos\LecturaEquipoTrabajo` (tarea 72,
 * HU-49, ADR 0015 punto 3): frontera hacia las tareas 73 (gasto/combustible)
 * y 74 (estadía). Test "unitario" en el sentido de probar la clase directo
 * (sin HTTP) — vive en Feature porque tests/Unit de este repo es PHPUnit
 * puro, sin Eloquent (ver tests/Pest.php). Mismo criterio que
 * `LecturaContratoEloquentTest`.
 *
 * `vigentesAFecha()` reemplaza a "equipos vigentes de una campaña" del
 * criterio de aceptación original: `per_equipos_trabajo` no lleva
 * `campania_id` (corrección del 8/9/2026) — ver docblock de la interfaz.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

it('vigentesAFecha devuelve solo los equipos cuya vigencia propia contiene la fecha', function () {
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $vigente = EquipoTrabajo::query()->create(['codigo' => 'EQ-VIGENTE', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $finalizado = EquipoTrabajo::query()->create(['codigo' => 'EQ-FIN', 'base_id' => $base->id, 'estado' => 'inactivo', 'desde' => '2026-01-01', 'hasta' => '2026-02-28']);
    $futuro = EquipoTrabajo::query()->create(['codigo' => 'EQ-FUTURO', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-06-01']);

    $resultado = app(LecturaEquipoTrabajo::class)->vigentesAFecha('2026-03-15');

    $codigos = array_map(fn ($datos) => $datos->codigo, $resultado);

    expect($codigos)->toContain('EQ-VIGENTE')
        ->and($codigos)->not->toContain('EQ-FIN')
        ->and($codigos)->not->toContain('EQ-FUTURO');
});

it('integrantesAFecha resuelve el nombre de la persona vigente esa fecha', function () {
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $persona = PerPersona::query()->create(['nombre' => 'Piloto Vigente', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'persona_id' => $persona->id,
        'rol_equipo' => 'piloto',
        'desde' => '2026-01-01',
        'hasta' => null,
    ]);

    $resultado = app(LecturaEquipoTrabajo::class)->integrantesAFecha($equipo->id, '2026-03-15');

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]->nombrePersona)->toBe('Piloto Vigente')
        ->and($resultado[0]->rolEquipo)->toBe('piloto');

    expect(app(LecturaEquipoTrabajo::class)->integrantesAFecha($equipo->id, '2025-12-31'))->toBe([]);
});

it('recursosAFecha devuelve solo los recursos cuya vigencia contiene la fecha', function () {
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    EquipoRecurso::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'dron',
        'recurso_id' => 7,
        'desde' => '2026-01-01',
        'hasta' => '2026-02-28',
    ]);

    EquipoRecurso::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => 3,
        'desde' => '2026-01-01',
        'hasta' => null,
    ]);

    $resultado = app(LecturaEquipoTrabajo::class)->recursosAFecha($equipo->id, '2026-03-15');

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]->recursoTipo)->toBe('vehiculo')
        ->and($resultado[0]->recursoId)->toBe(3);
});
