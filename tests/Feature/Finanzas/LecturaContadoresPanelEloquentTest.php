<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Contratos\LecturaContadoresPanel;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * Contrato de lectura `Finanzas\Contratos\LecturaContadoresPanel` (TE-14,
 * tarea 60): el dato real que alimenta el badge de devengos del menú —
 * delega en `ListarDevengosPersona`, no reimplementa la suma con
 * `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function generarDevengoParaContadoresPanel(PerPersona $piloto, PerPersona $jefe, string $hectareas, string $sufijo): void
{
    $cliente = Cliente::create(['razon_social' => "Cliente contadores fin {$sufijo}"]);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => "Campo contadores fin {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-CTF-{$sufijo}", 'hectareas' => '50.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-ctf-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-01-01T10:00:00-04:00',
    ]);
    $sesion = Sesion::create([
        'uuid_cliente' => "uuid-sesion-ctf-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-01-01T10:05:00-04:00',
        'fin' => '2026-01-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => "uuid-cierre-ctf-{$sufijo}",
    ]);

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

it('devengadoDelMes suma el total exacto de la persona en el mes calendario en curso', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto ctf', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '12.35', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe ctf', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    generarDevengoParaContadoresPanel($piloto, $jefe, '3.33', 'uno');
    generarDevengoParaContadoresPanel($piloto, $jefe, '1.00', 'dos');

    expect(app(LecturaContadoresPanel::class)->devengadoDelMes($piloto->id))->toBe('53.48');
});

it('no mezcla el devengado de otra persona ni de otro mes', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto ctf propio', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);
    $otraPersona = PerPersona::create(['nombre' => 'Piloto ctf ajeno', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe ctf mezcla', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    generarDevengoParaContadoresPanel($otraPersona, $jefe, '10.00', 'ajeno');

    Carbon::setTestNow('2026-01-15 10:00:00');
    generarDevengoParaContadoresPanel($piloto, $jefe, '10.00', 'mes-viejo');
    Carbon::setTestNow('2026-09-15 10:00:00');

    expect(app(LecturaContadoresPanel::class)->devengadoDelMes($piloto->id))->toBe('0.00');
});
