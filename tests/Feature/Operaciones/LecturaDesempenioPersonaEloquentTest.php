<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Brick\Math\BigDecimal;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Operaciones\Contratos\LecturaDesempenioPersona`
 * (HU-58, tarea 81): "¿qué hizo esta persona esta campaña?" — sesiones con
 * cliente/campaña/lote ya resueltos, rechazos aparte (invariante 2) e
 * incidencias. Fixtures directo por Eloquent, mismo criterio que
 * `ReporteTecnicoTest`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

/** Cliente (o uno ya existente, para dos campañas del mismo cliente) con una campaña abierta y un contrato asignado a ella. */
function contratoConCampania(string $sufijo, string $campaniaCodigo, string $campaniaDesde, string $campaniaHasta, ?Cliente $cliente = null): Contrato
{
    $cliente ??= Cliente::create(['razon_social' => "Cliente desempeño {$sufijo}", 'tipo_persona' => 'juridica']);
    $campania = Campania::create([
        'cliente_id' => $cliente->id,
        'codigo' => $campaniaCodigo,
        'fecha_inicio' => $campaniaDesde,
        'fecha_fin' => $campaniaHasta,
        'estado' => EstadoCampania::Abierta,
    ]);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => $campaniaDesde,
        'estado' => EstadoContrato::Vigente,
    ]);
}

function trabajoDeContrato(Contrato $contrato, string $sufijo): Trabajo
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo desempeño {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-DES-{$sufijo}", 'hectareas' => '50.00']);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-08-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '50.00']);

    return Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-desempeno-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-08-05T08:00:00-04:00',
        'fin' => '2026-08-05T12:00:00-04:00',
    ]);
}

function sesionDe(
    Trabajo $trabajo,
    string $sufijo,
    int $pilotoId,
    ?int $auxiliarId,
    EstadoSesion $estado,
    string $hectareas,
    string $inicio,
    ?string $anuladaEn = null,
): Sesion {
    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-desempeno-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'auxiliar_id' => $auxiliarId,
        'hectareas_declaradas' => $hectareas,
        'estado' => $estado,
        'inicio' => $inicio,
        'fin' => null,
        'motivo_cierre' => $estado === EstadoSesion::Abierto ? null : 'completado',
        'anulada_en' => $anuladaEn,
    ]);
}

it('devuelve listas vacías para una persona sin sesiones en el rango', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto sin vuelos', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $resultado = app(LecturaDesempenioPersona::class)->ejecutar($piloto->id, '2026-01-01', '2026-12-31');

    expect($resultado->sesiones)->toBe([])
        ->and($resultado->rechazos)->toBe([])
        ->and($resultado->incidencias)->toBe([]);
});

it('trae las sesiones de dos clientes distintos, con la campaña resuelta por el contrato de la orden y no por fecha', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto multicliente', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    // Cliente A tiene DOS campañas abiertas cuyo rango contiene la fecha del
    // vuelo — la ambigüedad que ADR 0015 punto 1 prohíbe resolver por fecha.
    $contratoA1 = contratoConCampania('A1', '2025-2026', '2025-07-01', '2026-06-30');
    $clienteA = Cliente::find($contratoA1->cliente_id);
    contratoConCampania('A2', '2025-Verano', '2025-11-01', '2026-03-31', $clienteA);

    $trabajoA = trabajoDeContrato($contratoA1, 'A');
    $sesionA = sesionDe($trabajoA, 'A', $piloto->id, null, EstadoSesion::Validado, '12.34', '2026-01-15T08:00:00-04:00');

    $otroPiloto = PerPersona::create(['nombre' => 'Piloto titular B', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $contratoB = contratoConCampania('B', '2026-B', '2026-01-01', '2026-12-31');
    $trabajoB = trabajoDeContrato($contratoB, 'B');
    $sesionB = sesionDe($trabajoB, 'B', $otroPiloto->id, $piloto->id, EstadoSesion::Cerrado, '5.66', '2026-02-10T08:00:00-04:00');

    $resultado = app(LecturaDesempenioPersona::class)->ejecutar($piloto->id, '2026-01-01', '2026-12-31');

    expect($resultado->sesiones)->toHaveCount(2);

    $filaA = collect($resultado->sesiones)->firstWhere('sesionId', $sesionA->id);
    $filaB = collect($resultado->sesiones)->firstWhere('sesionId', $sesionB->id);

    expect($filaA->rol)->toBe('piloto')
        ->and($filaA->campaniaCodigo)->toBe('2025-2026')
        ->and($filaA->clienteNombre)->toBe('Cliente desempeño A1')
        ->and($filaB->rol)->toBe('auxiliar')
        ->and($filaB->clienteNombre)->toBe('Cliente desempeño B');

    // Dos clientes distintos, cada uno con su propia campaña.
    expect(collect($resultado->sesiones)->pluck('clienteNombre')->unique()->count())->toBe(2);

    $totalHectareas = collect($resultado->sesiones)
        ->reduce(fn (BigDecimal $acumulado, $sesion) => $acumulado->plus(BigDecimal::of($sesion->hectareasDeclaradas)), BigDecimal::zero());

    expect((string) $totalHectareas->toScale(2))->toBe('18.00');
});

it('una sesión rechazada aparece en rechazos con su motivo y no en sesiones ni en el total de hectáreas', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto con rechazo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $jefeCampo = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $contrato = contratoConCampania('RECHAZO', '2025-2026', '2025-07-01', '2026-06-30');
    $trabajo = trabajoDeContrato($contrato, 'RECHAZO');

    $sesionValida = sesionDe($trabajo, 'RECHAZO-VALIDA', $piloto->id, null, EstadoSesion::Validado, '10.00', '2026-03-01T08:00:00-04:00');
    $sesionRechazada = sesionDe($trabajo, 'RECHAZO-MALA', $piloto->id, null, EstadoSesion::Cerrado, '99.99', '2026-03-02T08:00:00-04:00', anuladaEn: '2026-03-03T09:00:00-04:00');

    SesionRechazo::create([
        'anula_a_id' => $sesionRechazada->id,
        'motivo' => 'Hectáreas declaradas no coinciden con la captura de RC',
        'rechazado_por' => $jefeCampo->id,
    ]);

    $resultado = app(LecturaDesempenioPersona::class)->ejecutar($piloto->id, '2026-01-01', '2026-12-31');

    expect($resultado->sesiones)->toHaveCount(1)
        ->and($resultado->sesiones[0]->sesionId)->toBe($sesionValida->id)
        ->and($resultado->rechazos)->toHaveCount(1);

    $rechazo = $resultado->rechazos[0];

    expect($rechazo->sesionId)->toBe($sesionRechazada->id)
        ->and($rechazo->motivo)->toBe('Hectáreas declaradas no coinciden con la captura de RC')
        ->and($rechazo->rechazadoPorPersonaId)->toBe($jefeCampo->id);

    $totalHectareas = collect($resultado->sesiones)
        ->reduce(fn (BigDecimal $acumulado, $sesion) => $acumulado->plus(BigDecimal::of($sesion->hectareasDeclaradas)), BigDecimal::zero());

    // Los 99.99 ha de la sesión rechazada NUNCA entran a este total.
    expect((string) $totalHectareas->toScale(2))->toBe('10.00');
});

it('trae las incidencias de las sesiones de la persona, por tipo', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto con incidencia', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $contrato = contratoConCampania('INC', '2025-2026', '2025-07-01', '2026-06-30');
    $trabajo = trabajoDeContrato($contrato, 'INC');
    $sesion = sesionDe($trabajo, 'INC', $piloto->id, null, EstadoSesion::Cerrado, '8.00', '2026-04-01T08:00:00-04:00');

    $evidencia = Evidencia::create([
        'uuid_cliente' => 'uuid-evidencia-incidencia-desempeno',
        'tipo' => TipoEvidencia::FotoIncidencia,
        'archivo_url' => 'evidencias/incidencia/2026/04/uuid-evidencia-incidencia-desempeno.jpg',
        'hash' => hash('sha256', 'uuid-evidencia-incidencia-desempeno'),
        'fecha' => '2026-04-01T09:00:00-04:00',
    ]);

    Incidencia::create([
        'uuid_cliente' => 'uuid-incidencia-desempeno',
        'sesion_id' => $sesion->id,
        'tipo' => TipoIncidencia::Bateria,
        'descripcion' => 'Batería con caída de voltaje',
        'hora' => '2026-04-01T09:00:00-04:00',
        'evidencia_foto_id' => $evidencia->id,
    ]);

    $resultado = app(LecturaDesempenioPersona::class)->ejecutar($piloto->id, '2026-01-01', '2026-12-31');

    expect($resultado->incidencias)->toHaveCount(1)
        ->and($resultado->incidencias[0]->tipo)->toBe('bateria')
        ->and($resultado->incidencias[0]->sesionId)->toBe($sesion->id);
});

it('no trae sesiones fuera del rango de fechas ni de otra persona', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto acotado', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $otraPersona = PerPersona::create(['nombre' => 'Otro piloto', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $contrato = contratoConCampania('RANGO', '2025-2026', '2025-07-01', '2026-06-30');
    $trabajo = trabajoDeContrato($contrato, 'RANGO');

    sesionDe($trabajo, 'RANGO-ANTES', $piloto->id, null, EstadoSesion::Validado, '3.00', '2025-12-31T08:00:00-04:00');
    sesionDe($trabajo, 'RANGO-OTRO', $otraPersona->id, null, EstadoSesion::Validado, '4.00', '2026-06-01T08:00:00-04:00');
    $sesionEnRango = sesionDe($trabajo, 'RANGO-OK', $piloto->id, null, EstadoSesion::Validado, '5.00', '2026-06-15T08:00:00-04:00');

    $resultado = app(LecturaDesempenioPersona::class)->ejecutar($piloto->id, '2026-01-01', '2026-12-31');

    expect($resultado->sesiones)->toHaveCount(1)
        ->and($resultado->sesiones[0]->sesionId)->toBe($sesionEnRango->id);
});
