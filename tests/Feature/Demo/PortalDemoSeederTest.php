<?php

use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\Demo\PortalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * Tarea 65 (HU-41), etapa 2: `PortalDemoSeeder` — dos clientes demo
 * (`cliente.sanjorge`, sobre el contrato de `NucleoComercialSeeder`;
 * `cliente.esperanza`, cliente nuevo propio de esta tarea), cada uno con al
 * menos una sesión validada, un acta firmada y su reporte técnico, por el
 * flujo real.
 *
 * Deliberadamente NO se prueba vía `DemoSeeder` (la familia mínima que usan
 * otros ~19 tests, «una orden vigente», «un lote») ni vía
 * `DemostracionSeeder` completa (lenta, con dependencias ajenas a esta
 * tarea): se siembra `DemoSeeder` + `PortalDemoSeeder` directo, que es
 * exactamente la cadena de la que depende — ver el docblock de
 * `PortalDemoSeeder` para el porqué no vive dentro de `DemoSeeder`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
});

function sembrarPortalDemo(): void
{
    test()->seed(DemoSeeder::class);
    test()->seed(PortalDemoSeeder::class);
}

function entrarAlPortalDemo(string $username): SecUser
{
    $usuario = SecUser::query()->where('username', $username)->sole();
    test()->actingAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente');

    return $usuario;
}

it('crea las dos cuentas de portal sin persona ni roles, cada una con su propio contrato', function () {
    sembrarPortalDemo();

    $sanJorge = SecUser::query()->where('username', 'cliente.sanjorge')->sole();
    $esperanza = SecUser::query()->where('username', 'cliente.esperanza')->sole();

    expect($sanJorge->type)->toBe(TipoUsuario::Cliente)
        ->and($sanJorge->persona_id)->toBeNull()
        ->and($sanJorge->idsDeRoles())->toBe([])
        ->and($esperanza->type)->toBe(TipoUsuario::Cliente)
        ->and($esperanza->persona_id)->toBeNull()
        ->and($esperanza->idsDeRoles())->toBe([])
        ->and($esperanza->contrato_id)->not->toBe($sanJorge->contrato_id);
});

it('cada cliente demo tiene al menos una sesión validada, un acta firmada y su reporte técnico, por el flujo real', function () {
    sembrarPortalDemo();

    foreach (['demo-portal-trabajo-sanjorge', 'demo-portal-trabajo-esperanza'] as $uuidTrabajo) {
        $trabajo = Trabajo::query()->where('uuid_cliente', $uuidTrabajo)->sole();
        $sesion = Sesion::query()->where('trabajo_id', $trabajo->id)->sole();
        $acta = Acta::query()->where('trabajo_id', $trabajo->id)->sole();
        $reporte = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->sole();

        expect($sesion->estado)->toBe(EstadoSesion::Validado)
            // Invariante 4: el validador nunca es el piloto de su propia sesión.
            ->and($sesion->validado_por)->not->toBe($sesion->piloto_id)
            ->and($acta->estado)->toBe(EstadoActa::Firmada)
            ->and($acta->fecha_firma)->not->toBeNull()
            ->and($reporte->trabajo_id)->toBe($trabajo->id);
    }
});

it('es idempotente: correr PortalDemoSeeder otra vez no duplica cuentas, trabajos, actas ni reportes', function () {
    sembrarPortalDemo();

    $conteos = fn () => [
        SecUser::query()->where('type', TipoUsuario::Cliente)->count(),
        Trabajo::query()->count(),
        Sesion::query()->count(),
        Acta::query()->count(),
        ReporteTecnico::query()->count(),
    ];

    $antes = $conteos();

    test()->seed(PortalDemoSeeder::class);

    expect($conteos())->toBe($antes);
});

it('cliente.sanjorge y cliente.esperanza ven datos distintos, y el PDF de un acta ajena da 404', function () {
    sembrarPortalDemo();

    $actaSanJorge = Acta::query()->where('uuid_cliente', 'demo-portal-acta-sanjorge')->sole();
    $actaEsperanza = Acta::query()->where('uuid_cliente', 'demo-portal-acta-esperanza')->sole();

    entrarAlPortalDemo('cliente.sanjorge');
    $this->get(route('portal.actas.index'))->assertOk();
    $this->get(route('portal.actas.pdf', $actaSanJorge->id))->assertOk();
    $this->get(route('portal.actas.pdf', $actaEsperanza->id))->assertNotFound();

    entrarAlPortalDemo('cliente.esperanza');
    $this->get(route('portal.actas.pdf', $actaEsperanza->id))->assertOk();
    $this->get(route('portal.actas.pdf', $actaSanJorge->id))->assertNotFound();
});
