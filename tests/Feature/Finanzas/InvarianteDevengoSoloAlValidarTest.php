<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Gate de la invariante 3 de CLAUDE.md ("el devengo se genera solo al
 * validar una sesión, nunca al cerrarla"), pendiente desde la tarea 14
 * (`docs/gestion/cola_tareas.md`, sección "Condicionadas": "su gate... se
 * escribe en la tarea 16, junto con el dominio de Finanzas que hace que haya
 * algo que guardar"). Antes de esta tarea, `fin_devengos_personal` no
 * existía — un test de aduana contra una tabla inexistente pasaría siempre
 * y simularía una cobertura que no había.
 */

uses(RefreshDatabase::class);

test('cerrar() una sesión, sin pasar por validar(), nunca crea una fila en fin_devengos_personal', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente de gate de devengo']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de gate de devengo']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-GATEDEV', 'hectareas' => '50.00']);
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
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-gatedev-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    // El piloto SÍ tiene tarifa_ha: si `cerrar()` disparara un devengo por
    // error, este test tiene que fallar por la fila creada, no de casualidad
    // por PersonaSinTarifaHa — la tarifa presente descarta esa lectura falsa
    // de un test en verde.
    $piloto = PerPersona::create([
        'nombre' => 'Piloto de gate de devengo',
        'rol' => RolOperativoPersona::Piloto,
        'tarifa_ha' => '150.00',
        'activo' => true,
    ]);

    $sesion = (new MaquinaEstadosSesion)->abrir([
        'uuid_cliente' => 'uuid-sesion-gatedev-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '0',
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    $cerrada = (new MaquinaEstadosSesion)->cerrar(
        $sesion,
        cierreUuidCliente: 'uuid-cierre-gatedev-'.uniqid(),
        fin: '2026-09-01T12:00:00-04:00',
        motivoCierre: 'completado',
        hectareasDeclaradas: '15.00',
    );

    expect($cerrada->estado)->toBe(EstadoSesion::Cerrado)
        ->and(DevengoPersonal::query()->count())->toBe(0);
});
