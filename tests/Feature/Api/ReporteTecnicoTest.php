<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\ArmarContenidoReporteTecnico;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * HU-18 (tarea 25): reporte técnico por lote, generado automáticamente al
 * firmar el acta de conformidad (HU-17, tarea 24) — nunca por su propio
 * endpoint de lectura (`GET /api/reportes/lote/{id}`), que solo sirve lo ya
 * generado.
 *
 * Fixtures directo por Eloquent (mismo criterio que `ActaConformidadTest`),
 * no vía `/api/sync`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

/** Usuario con rol `jefe_campo` (`operaciones.acta.*` + `operaciones.reporte.ver`, sembrados por `SeguridadSeeder`). */
function usuarioConPermisoReporte(): SecUser
{
    $usuario = SecUser::factory()->create();
    $idRol = (int) SecRole::query()->where('name', 'jefe_campo')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $usuario;
}

/** Usuario autenticado sin ningún rol asignado. */
function usuarioSinPermisoReporte(): SecUser
{
    return SecUser::factory()->create();
}

function ordenParaReporte(string $sufijo, string $hectareasLote): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente reporte {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo reporte {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-REP-{$sufijo}", 'hectareas' => $hectareasLote]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareasLote,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '200.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
}

function trabajoParaReporte(string $sufijo, EstadoTrabajo $estado, string $hectareasLote, string $hectareasDeclaradas): Trabajo
{
    $orden = ordenParaReporte($sufijo, $hectareasLote);

    return Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-reporte-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareasDeclaradas,
        'estado' => $estado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => $estado === EstadoTrabajo::Cerrado ? '2026-09-01T12:00:00-04:00' : null,
    ]);
}

function sesionParaReporte(
    Trabajo $trabajo,
    string $sufijo,
    EstadoSesion $estado,
    string $hectareasDeclaradas,
    string $motivoCierre,
    string $inicio,
    string $fin,
    ?int $dronId = null,
    int $secuencia = 1,
): Sesion {
    $piloto = PerPersona::create(['nombre' => "Piloto reporte {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-reporte-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => $secuencia,
        'piloto_id' => $piloto->id,
        'dron_id' => $dronId,
        'hectareas_declaradas' => $hectareasDeclaradas,
        'estado' => $estado,
        'inicio' => $inicio,
        'fin' => $fin,
        'motivo_cierre' => $motivoCierre,
    ]);
}

/** Trabajo `cerrado` con una única sesión `validado` que cubre el lote entero — listo para conformarse. */
function trabajoListoParaReporte(string $sufijo, string $hectareas = '18.50'): Trabajo
{
    $trabajo = trabajoParaReporte($sufijo, EstadoTrabajo::Cerrado, $hectareas, $hectareas);
    sesionParaReporte($trabajo, $sufijo, EstadoSesion::Validado, $hectareas, 'completado', '2026-09-01T08:05:00-04:00', '2026-09-01T11:00:00-04:00');

    return $trabajo;
}

/** @return string uuid_cliente de la evidencia de firma creada */
function evidenciaFirmaReporte(string $sufijo): string
{
    $uuidCliente = "uuid-firma-reporte-{$sufijo}";

    Evidencia::create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::FirmaActa,
        'archivo_url' => "evidencias/firma_acta/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** Genera y firma el acta de `$trabajo` con `$usuario` — dispara la generación automática del reporte. */
function conformarTrabajoParaReporte(Trabajo $trabajo, SecUser $usuario, string $sufijo): Acta
{
    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-reporte-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    $evidenciaUuid = evidenciaFirmaReporte($sufijo);

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaUuid,
            'firmante' => 'Ing. Marcela Vargas',
            'fecha_firma' => '2026-09-02T16:30:00-04:00',
        ])->assertOk();

    return $acta->fresh();
}

/*
 * ── Caso 1: firmar el acta genera automáticamente la fila y el PDF ──
 */

it('firmar el acta de un trabajo genera automáticamente el reporte técnico, en la misma operación', function () {
    $trabajo = trabajoListoParaReporte('auto');
    $usuario = usuarioConPermisoReporte();

    conformarTrabajoParaReporte($trabajo, $usuario, 'auto');

    expect(ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->count())->toBe(1);

    $reporte = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    expect($reporte->pdf_path)->not->toBeNull();
    Storage::disk('r2')->assertExists($reporte->pdf_path);
});

/*
 * ── Caso 2: reintento de firma sobre acta ya firmada → no regenera ni duplica ──
 */

it('reintentar la firma sobre un acta ya firmada no regenera el reporte ni duplica la fila', function () {
    $trabajo = trabajoListoParaReporte('idem');
    $usuario = usuarioConPermisoReporte();

    $acta = conformarTrabajoParaReporte($trabajo, $usuario, 'idem');
    $reporte = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    Storage::disk('r2')->put($reporte->pdf_path, 'contenido-original-no-tocar');

    // Mismo reintento idempotente que `ActaConformidadTest`: la MISMA evidencia sobre el acta ya firmada.
    $evidenciaUuid = Evidencia::query()->where('tipo', TipoEvidencia::FirmaActa)->value('uuid_cliente');

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaUuid,
            'firmante' => 'Ing. Marcela Vargas',
            'fecha_firma' => '2026-09-02T16:30:00-04:00',
        ])->assertOk();

    expect(ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->count())->toBe(1)
        ->and(ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->firstOrFail()->id)->toBe($reporte->id);
    expect(Storage::disk('r2')->get($reporte->pdf_path))->toBe('contenido-original-no-tocar');
});

/*
 * ── Caso 3: GET sobre un trabajo sin acta firmada → 404 ──
 */

it('descargar el reporte de un trabajo sin acta firmada responde 404', function () {
    $trabajo = trabajoListoParaReporte('sin-firmar');
    $usuario = usuarioConPermisoReporte();

    // Genera el acta pero no la firma: todavía no hay reporte.
    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-reporte-sin-firmar'])
        ->assertOk();

    $this->actingAs($usuario, 'sanctum')
        ->getJson("/api/reportes/lote/{$trabajo->id}")
        ->assertNotFound();

    expect(ReporteTecnico::query()->count())->toBe(0);
});

/*
 * ── Autorización: sin el permiso operaciones.reporte.ver → 403 ──
 */

it('descargar el reporte sin el permiso operaciones.reporte.ver responde 403', function () {
    $trabajo = trabajoListoParaReporte('sinpermiso');
    conformarTrabajoParaReporte($trabajo, usuarioConPermisoReporte(), 'sinpermiso');

    $this->actingAs(usuarioSinPermisoReporte(), 'sanctum')
        ->getJson("/api/reportes/lote/{$trabajo->id}")
        ->assertForbidden();
});

/*
 * ── Caso 4: GET sobre un trabajo conformado → devuelve el reporte, horas correctas ──
 */

it('descargar el reporte de un trabajo conformado devuelve el PDF con las horas de inicio/fin correctas', function () {
    $trabajo = trabajoParaReporte('horas', EstadoTrabajo::Cerrado, '18.50', '18.50');
    $sesion = sesionParaReporte($trabajo, 'horas', EstadoSesion::Validado, '18.50', 'completado', '2026-09-01T08:00:00-04:00', '2026-09-01T11:30:00-04:00');
    $usuario = usuarioConPermisoReporte();

    conformarTrabajoParaReporte($trabajo, $usuario, 'horas');

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->getJson("/api/reportes/lote/{$trabajo->id}")
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toStartWith('application/pdf');

    // Contra el MIN/MAX real de las sesiones (criterio del prompt), no contra
    // un reloj absoluto externo: `Sesion::inicio`/`fin` (`ope_sesiones`,
    // `datetime` sin tz) ya arrastran el mismo bug de timezone que la
    // revisión crítica de la tarea 24 marcó fuera de alcance para
    // `MaquinaEstadosSesion::cerrar()` — no es de esta tarea corregirlo (el
    // prompt prohíbe tocar `Sesion`/`Trabajo`), así que este test verifica
    // la fidelidad de la propagación, no la corrección absoluta del dato de
    // origen. Ver runs/25.md.
    $sesion->refresh();
    $reporte = ReporteTecnico::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    expect($reporte->hora_inicio?->eq($sesion->inicio))->toBeTrue()
        ->and($reporte->hora_fin?->eq($sesion->fin))->toBeTrue();
});

/*
 * ── GET sobre un trabajo inexistente → 404 (route-model-binding) ──
 */

it('descargar el reporte de un trabajo inexistente responde 404', function () {
    $this->actingAs(usuarioConPermisoReporte(), 'sanctum')
        ->getJson('/api/reportes/lote/999999')
        ->assertNotFound();
});

/*
 * ── Caso 5: cobertura Parcial → superficie no aplicada y motivo correcto ──
 */

it('un trabajo con cobertura parcial arma la superficie no aplicada y el motivo correcto', function () {
    // Lote de 20 ha, trabajo cerrado con solo 12 ha declaradas: quedan 8 ha sin aplicar.
    $trabajo = trabajoParaReporte('parcial', EstadoTrabajo::Cerrado, '20.00', '12.00');
    sesionParaReporte($trabajo, 'parcial', EstadoSesion::Validado, '12.00', 'falla_equipo', '2026-09-01T08:00:00-04:00', '2026-09-01T10:00:00-04:00');
    $usuario = usuarioConPermisoReporte();

    conformarTrabajoParaReporte($trabajo, $usuario, 'parcial');

    $datos = app(ArmarContenidoReporteTecnico::class)->ejecutar($trabajo->fresh());

    expect($datos['resumen']['cobertura'])->toBe('parcial')
        ->and($datos['superficie_no_aplicada'])->toBe(['hectareas' => '8.00', 'motivo' => 'falla_equipo']);
});

/*
 * ── Caso 6: relevo de piloto (2+ sesiones vigentes) → detalle por sesión ──
 */

it('un trabajo con relevo de piloto detalla cada sesión por separado', function () {
    $dronSaliente = Dron::create(['identificador' => 'DRON-REPORTE-SALIENTE']);
    $dronEntrante = Dron::create(['identificador' => 'DRON-REPORTE-ENTRANTE']);

    $trabajo = trabajoParaReporte('relevo', EstadoTrabajo::Cerrado, '18.00', '18.00');
    sesionParaReporte($trabajo, 'relevo-1', EstadoSesion::Validado, '10.00', 'relevo_piloto', '2026-09-01T08:00:00-04:00', '2026-09-01T09:30:00-04:00', $dronSaliente->id, 1);
    sesionParaReporte($trabajo, 'relevo-2', EstadoSesion::Validado, '8.00', 'completado', '2026-09-01T09:45:00-04:00', '2026-09-01T11:00:00-04:00', $dronEntrante->id, 2);
    $usuario = usuarioConPermisoReporte();

    conformarTrabajoParaReporte($trabajo, $usuario, 'relevo');

    $datos = app(ArmarContenidoReporteTecnico::class)->ejecutar($trabajo->fresh());

    expect($datos['sesiones_detalle'])->toHaveCount(2);
    expect($datos['sesiones_detalle'][0])->toMatchArray(['dron_id' => $dronSaliente->id, 'hectareas_declaradas' => '10.00', 'motivo_cierre' => 'relevo_piloto']);
    expect($datos['sesiones_detalle'][1])->toMatchArray(['dron_id' => $dronEntrante->id, 'hectareas_declaradas' => '8.00', 'motivo_cierre' => 'completado']);
});

/*
 * ── Caso 7: el contenido NO incluye ningún campo de mezcla, dosis, receta o producto ──
 */

it('el contenido del reporte no incluye ningún campo de mezcla, dosis, receta o producto', function () {
    $trabajo = trabajoListoParaReporte('sin-mezcla');
    conformarTrabajoParaReporte($trabajo, usuarioConPermisoReporte(), 'sin-mezcla');

    $datos = app(ArmarContenidoReporteTecnico::class)->ejecutar($trabajo->fresh());

    // La única mención permitida es la nota de exclusión explícita (CR-01)
    // — se saca antes de buscar las palabras prohibidas en el resto del array.
    $datosSinNota = $datos;
    unset($datosSinNota['nota_mezcla']);
    $json = mb_strtolower(json_encode($datosSinNota, JSON_THROW_ON_ERROR));

    expect($json)->not->toContain('mezcla')
        ->not->toContain('dosis')
        ->not->toContain('receta')
        ->not->toContain('producto')
        ->not->toContain('formula');

    // La nota SÍ debe estar, y en texto explícito (no un placeholder vacío).
    expect($datos['nota_mezcla'])->toContain('CR-01')->toContain('fuera de alcance');
});

/*
 * ── Caso 8: idempotencia — sin constraint específico de Postgres en esta migración ──
 *
 * A diferencia de `ope_recargas`/`ope_condiciones` (tarea 23), esta tabla no
 * declara ningún índice/CHECK vía `DB::statement` (pgsql-only): `trabajo_id`
 * usa `$table->foreignId(...)->unique()`, un `UNIQUE` de esquema estándar que
 * Laravel traduce igual en SQLite y en Postgres. La idempotencia real la
 * ejercita el Caso 2 de arriba contra la conexión que use la sesión (forzada
 * a SQLite por `tests/bootstrap.php`, igual que el resto de la suite — ver
 * runs/25.md): no hay una segunda ruta de fallo específica de Postgres que
 * verificar por separado, así que no hace falta duplicar el caso 2 contra esa
 * conexión.
 */
it('la unicidad de trabajo_id no depende de un constraint específico de Postgres', function () {
    $trabajo = trabajoListoParaReporte('constraint');
    conformarTrabajoParaReporte($trabajo, usuarioConPermisoReporte(), 'constraint');

    expect(fn () => ReporteTecnico::create(['trabajo_id' => $trabajo->id, 'generado_en' => now()]))
        ->toThrow(QueryException::class);
});

/*
 * ── Caso 9: incidencias de sesión (tarea 28 — cierra el hueco que dejó HU-18) ──
 */

it('el contenido del reporte incluye las incidencias de las sesiones vigentes, con tipo y evidencia', function () {
    $trabajo = trabajoParaReporte('incidencia', EstadoTrabajo::Cerrado, '18.50', '18.50');
    $sesion = sesionParaReporte($trabajo, 'incidencia', EstadoSesion::Validado, '18.50', 'completado', '2026-09-01T08:00:00-04:00', '2026-09-01T11:00:00-04:00');

    $evidenciaUuid = 'uuid-evidencia-incidencia-reporte';
    $evidencia = Evidencia::create([
        'uuid_cliente' => $evidenciaUuid,
        'tipo' => TipoEvidencia::FotoIncidencia,
        'archivo_url' => "evidencias/foto_incidencia/2026/09/{$evidenciaUuid}.jpg",
        'hash' => hash('sha256', $evidenciaUuid),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    Incidencia::create([
        'uuid_cliente' => 'uuid-incidencia-reporte',
        'sesion_id' => $sesion->id,
        'tipo' => TipoIncidencia::Clima,
        'hora' => '2026-09-01T09:30:00-04:00',
        'evidencia_foto_id' => $evidencia->id,
    ]);

    $datos = app(ArmarContenidoReporteTecnico::class)->ejecutar($trabajo->fresh());

    expect($datos['incidencias'])->toBe([
        ['tipo' => 'clima', 'evidencia_url' => $evidencia->archivo_url],
    ]);
});

it('sin incidencias registradas, la lista del reporte sigue vacía', function () {
    $trabajo = trabajoListoParaReporte('sin-incidencias');
    conformarTrabajoParaReporte($trabajo, usuarioConPermisoReporte(), 'sin-incidencias');

    $datos = app(ArmarContenidoReporteTecnico::class)->ejecutar($trabajo->fresh());

    expect($datos['incidencias'])->toBe([]);
});
