<?php

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-87 (tarea 102): "odómetro" de `ciclos_acumulados` — el listener real de
 * `RecargaRegistrada` (`IncrementarCiclosBateria`, cableado en
 * `MantenimientoServiceProvider::boot()`), probado aislado del transporte
 * HTTP y del motor de sync, mismo criterio que
 * tests/Feature/Finanzas/GenerarDevengosSesionTest.php con `SesionValidada`.
 */

uses(RefreshDatabase::class);

it('dos recargas de la misma batería incrementan ciclos_acumulados dos veces', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-01', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 5, 'estado' => 'activa']);

    event(new RecargaRegistrada('BAT-01'));
    event(new RecargaRegistrada('BAT-01'));

    expect(Bateria::query()->findOrFail($bateria->id)->ciclos_acumulados)->toBe(7);
});

it('una RecargaRegistrada con un identificador sin batería conocida no hace nada', function () {
    Bateria::query()->create(['identificador' => 'BAT-01', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 5, 'estado' => 'activa']);

    event(new RecargaRegistrada('BAT-INEXISTENTE'));

    expect(Bateria::query()->where('identificador', 'BAT-01')->value('ciclos_acumulados'))->toBe(5);
});
