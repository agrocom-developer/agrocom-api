<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * `GET /panel/trabajos` (HU-05, tarea 13): pantalla mínima de Operaciones —
 * el jefe ve que algo se cerró. Gateada por `operaciones.trabajo.ver` (CA
 * obligatorio: sin el permiso, 403). Mismo patrón que
 * tests/Feature/Distribucion/VersionesApkPanelTest.php.
 *
 * HU-15 (tarea 15): se extiende con filtros (estado de tablero, lote,
 * orden), paginado y el detalle de un trabajo (`GET
 * /panel/trabajos/{trabajo}`) — mismos helpers de autenticación, más
 * `crearTrabajo()` (fixture parametrizable, en vez de duplicar
 * `trabajoCerradoDemo()` para cada combinación de filtro).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaTrabajos(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaTrabajos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function trabajoCerradoDemo(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel trabajos', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo panel']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-PANEL', 'hectareas' => '40.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '40.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '40.00']);
    $piloto = PerPersona::create(['nombre' => 'Piloto panel', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-panel',
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T09:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'cierre_uuid_cliente' => 'uuid-cierre-trabajo-panel',
    ]);

    Sesion::create([
        'uuid_cliente' => 'uuid-sesion-panel',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T09:05:00-04:00',
        'fin' => '2026-09-01T10:05:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-sesion-panel',
    ]);

    return $trabajo;
}

it('un usuario con el permiso ve el trabajo cerrado en la pantalla', function () {
    trabajoCerradoDemo();
    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.campo', 'jefe_campo');

    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos')
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.estado.cerrado'));
});

it('sin el permiso operaciones.trabajo.ver, la pantalla responde 403', function () {
    trabajoCerradoDemo();
    [$piloto, $idRol] = usuarioConRolParaTrabajos('piloto.curioso', 'piloto');

    entrarAlPanelParaTrabajos($piloto, $idRol);

    $this->get('/panel/trabajos')->assertForbidden();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/trabajos')->assertRedirect();
});

it('publica el ítem de menú de trabajos gateado por operaciones.trabajo.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.operacion.items.trabajos')->sole();

    $idPermiso = (int) SecPermission::query()->where('code', 'operaciones.trabajo.ver')->value('id');

    expect($itemMenu->ruta)->toBe('panel.trabajos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

/*
 * HU-15 (tarea 15): tablero — filtros, paginado y detalle.
 */

/**
 * Fixture parametrizable: crea la cadena completa (Cliente → Campo → Lote →
 * Contrato → OrdenAplicacion → Trabajo, con sesiones opcionales) — las FKs
 * son reales, no hay atajo. `$atributosTrabajo` acepta `estado` (default
 * Cerrado) y `nro_aplicacion` (default 1); `$sesiones` es una lista de
 * arreglos de atributos de `Sesion` (fusionados sobre un default "cerrada,
 * completa" — solo se sobreescribe lo que el test necesita, p. ej. `estado`
 * o `anulada_en`).
 *
 * @param  array<string, mixed>  $atributosTrabajo
 * @param  list<array<string, mixed>>  $sesiones
 */
function crearTrabajo(array $atributosTrabajo = [], array $sesiones = []): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel trabajos '.Str::random(6), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo panel '.Str::random(6)]);
    $lote = Lote::create([
        'campo_id' => $campo->id,
        'codigo' => 'L-'.Str::random(6),
        'hectareas' => '40.00',
    ]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '40.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $nroAplicacion = $atributosTrabajo['nro_aplicacion'] ?? 1;
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => $nroAplicacion,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '40.00']);

    $estadoTrabajo = $atributosTrabajo['estado'] ?? EstadoTrabajo::Cerrado;

    $trabajo = Trabajo::create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => $nroAplicacion,
        'estado' => $estadoTrabajo,
        'inicio' => '2026-09-01T09:00:00-04:00',
        'fin' => $estadoTrabajo === EstadoTrabajo::Cerrado ? '2026-09-01T12:00:00-04:00' : null,
        'cierre_uuid_cliente' => $estadoTrabajo === EstadoTrabajo::Cerrado ? (string) Str::uuid() : null,
    ]);

    foreach ($sesiones as $atributosSesion) {
        $piloto = PerPersona::create(['nombre' => 'Piloto '.Str::random(6), 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

        Sesion::create([
            'uuid_cliente' => (string) Str::uuid(),
            'trabajo_id' => $trabajo->id,
            'secuencia' => 1,
            'piloto_id' => $piloto->id,
            'hectareas_declaradas' => '12.00',
            'estado' => EstadoSesion::Cerrado,
            'inicio' => '2026-09-01T09:05:00-04:00',
            'fin' => '2026-09-01T10:05:00-04:00',
            'motivo_cierre' => 'completado',
            'cierre_uuid_cliente' => (string) Str::uuid(),
            ...$atributosSesion,
        ]);
    }

    return $trabajo;
}

it('el filtro por estado abierto devuelve solo los trabajos abiertos', function () {
    $abierto = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);
    $cerrado = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.abierto', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos?estado=abierto')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$abierto->id]
            && ! $trabajos->pluck('id')->contains($cerrado->id));
});

it('el filtro por estado cerrado devuelve solo los cerrados con alguna sesión vigente sin validar', function () {
    $cerrado = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);
    $validado = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Validado]]);
    $abierto = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.cerrado', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos?estado=cerrado')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$cerrado->id]);
});

it('el filtro por estado validado devuelve solo trabajos con TODAS sus sesiones vigentes validadas', function () {
    $validado = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Validado]]);
    $parcial = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [
        ['estado' => EstadoSesion::Validado],
        ['estado' => EstadoSesion::Cerrado],
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.validado', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos?estado=validado')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$validado->id]
            && ! $trabajos->pluck('id')->contains($parcial->id));
});

it('un trabajo cerrado sin ninguna sesión vigente cuenta como cerrado, nunca como validado', function () {
    $sinSesiones = crearTrabajo(['estado' => EstadoTrabajo::Cerrado]);
    $soloRechazada = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [
        ['estado' => EstadoSesion::Cerrado, 'anulada_en' => now()],
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.sinvigentes', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos?estado=validado')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->isEmpty());

    $this->get('/panel/trabajos?estado=cerrado')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->sort()->values()->all()
            === collect([$sinSesiones->id, $soloRechazada->id])->sort()->values()->all());
});

it('una sesión rechazada no impide que el trabajo cuente como validado', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [
        ['estado' => EstadoSesion::Validado],
        ['estado' => EstadoSesion::Cerrado, 'anulada_en' => now()],
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.rechazada', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos?estado=validado')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$trabajo->id]);
});

it('el filtro por lote devuelve solo los trabajos de ese lote', function () {
    $trabajoA = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);
    $trabajoB = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.lote', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos?lote_id={$trabajoA->lote_id}")
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$trabajoA->id]
            && ! $trabajos->pluck('id')->contains($trabajoB->id));
});

it('el filtro por orden de aplicación devuelve solo los trabajos de esa orden', function () {
    $trabajoA = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);
    $trabajoB = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.filtro.orden', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos?orden_id={$trabajoA->orden_id}")
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->pluck('id')->all() === [$trabajoA->id]
            && ! $trabajos->pluck('id')->contains($trabajoB->id));
});

it('pagina el listado de trabajos', function () {
    collect(range(1, 16))->each(fn () => crearTrabajo(['estado' => EstadoTrabajo::Abierto]));

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.paginado', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->count() === 15 && $trabajos->total() === 16);

    $this->get('/panel/trabajos?page=2')
        ->assertOk()
        ->assertViewHas('trabajos', fn ($trabajos) => $trabajos->count() === 1);
});

it('el detalle de un trabajo muestra sus sesiones asociadas', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [
        ['estado' => EstadoSesion::Cerrado, 'hectareas_declaradas' => '12.00'],
    ]);
    $sesion = $trabajo->sesiones()->sole();

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.detalle', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}")
        ->assertOk()
        ->assertViewHas('trabajo', fn ($trabajoVista) => $trabajoVista->sesiones->pluck('id')->all() === [$sesion->id])
        ->assertSee(__('operaciones.trabajos.sesion_piloto', ['id' => $sesion->piloto_id]))
        ->assertSee(__('operaciones.trabajos.detalle_evidencias_vacio'));
});

it('el detalle de un trabajo sin sesiones no rompe y muestra la ausencia con normalidad', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Abierto]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.detalle.vacio', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}")
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.sesiones_vacio'))
        ->assertSee(__('operaciones.trabajos.detalle_evidencias_vacio'));
});

it('el detalle muestra que una sesión fue rechazada, con su motivo, sin tocar la fila original', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [
        ['estado' => EstadoSesion::Cerrado, 'anulada_en' => now()],
    ]);
    $sesion = $trabajo->sesiones()->sole();
    $jefeQueRechazo = PerPersona::create(['nombre' => 'Jefe rechazo', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    SesionRechazo::create([
        'anula_a_id' => $sesion->id,
        'motivo' => 'Hectáreas informadas no coinciden con el vuelo real',
        'rechazado_por' => $jefeQueRechazo->id,
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.detalle.rechazo', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}")
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.sesion_rechazada'))
        ->assertSee('Hectáreas informadas no coinciden con el vuelo real');

    // La fila original de `Sesion` no se tocó (invariante 2): sigue
    // `cerrado`, con sus columnas de negocio intactas — solo la marca
    // `anulada_en` distingue el rechazo.
    expect($sesion->refresh()->estado)->toBe(EstadoSesion::Cerrado)
        ->and($sesion->anulada_en)->not->toBeNull();
});

it('sin el permiso operaciones.trabajo.ver, el detalle de un trabajo responde 403', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);

    [$piloto, $idRol] = usuarioConRolParaTrabajos('piloto.detalle.403', 'piloto');
    entrarAlPanelParaTrabajos($piloto, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}")->assertForbidden();
});

/*
 * HU-42 (tarea 56): galería de evidencias de un trabajo — imagen de campo,
 * firma del acta e incidencias con foto por sesión. Mismo permiso que el
 * detalle (`operaciones.trabajo.ver`); el streaming del archivo real hace
 * pruebas contra el disco `r2` (fake), nunca contra `archivo_url` expuesto.
 */
function evidenciaDemo(TipoEvidencia $tipo, string $contenido = 'contenido-evidencia-panel'): Evidencia
{
    $ruta = 'evidencias/'.Str::uuid().'.jpg';
    Storage::disk('r2')->put($ruta, $contenido);

    return Evidencia::create([
        'uuid_cliente' => (string) Str::uuid(),
        'tipo' => $tipo,
        'archivo_url' => $ruta,
        'hash' => hash('sha256', $contenido),
        'fecha' => now(),
    ]);
}

it('la galería muestra la imagen de campo, la firma del acta y las incidencias con foto', function () {
    Storage::fake('r2');

    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);
    $sesion = $trabajo->sesiones()->sole();

    $imagenCampo = evidenciaDemo(TipoEvidencia::ImagenCampo);
    $trabajo->update(['imagen_campo_evidencia_id' => $imagenCampo->id]);

    $firma = evidenciaDemo(TipoEvidencia::FirmaActa);
    Acta::create([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'hectareas_conformadas' => '12.00',
        'estado' => EstadoActa::Firmada,
        'evidencia_firma_id' => $firma->id,
        'firmante' => 'Agrónomo demo',
        'fecha_firma' => now(),
    ]);

    $fotoIncidencia = evidenciaDemo(TipoEvidencia::FotoIncidencia);
    Incidencia::create([
        'uuid_cliente' => (string) Str::uuid(),
        'sesion_id' => $sesion->id,
        'tipo' => TipoIncidencia::Mecanica,
        'descripcion' => 'Falla de motor',
        'hora' => now(),
        'evidencia_foto_id' => $fotoIncidencia->id,
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.evidencias', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}/evidencias")
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.evidencias_imagen_campo_titulo'))
        ->assertSee(__('operaciones.trabajos.evidencias_firma_acta_titulo'))
        ->assertSee(__('operaciones.trabajos.incidencia_tipo.mecanica'))
        ->assertSee(route('panel.evidencias.archivo', $imagenCampo))
        ->assertSee(route('panel.evidencias.archivo', $firma))
        ->assertSee(route('panel.evidencias.archivo', $fotoIncidencia));
});

it('sin ninguna evidencia, la galería muestra las tres secciones vacías sin romper', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.evidencias.vacio', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}/evidencias")
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.evidencias_imagen_campo_vacio'))
        ->assertSee(__('operaciones.trabajos.evidencias_firma_acta_vacio'))
        ->assertSee(__('operaciones.trabajos.evidencias_incidencias_vacio'));
});

it('sin el permiso operaciones.trabajo.ver, la galería de evidencias responde 403', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);

    [$piloto, $idRol] = usuarioConRolParaTrabajos('piloto.evidencias.403', 'piloto');
    entrarAlPanelParaTrabajos($piloto, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}/evidencias")->assertForbidden();
});

it('el detalle de un trabajo linkea a la galería de evidencias', function () {
    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.link.evidencias', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get("/panel/trabajos/{$trabajo->id}")
        ->assertOk()
        ->assertSee(route('panel.trabajos.evidencias', $trabajo));
});

it('el streaming del archivo de una evidencia responde con su contenido real', function () {
    Storage::fake('r2');

    $trabajo = crearTrabajo(['estado' => EstadoTrabajo::Cerrado], [['estado' => EstadoSesion::Cerrado]]);
    $evidencia = evidenciaDemo(TipoEvidencia::ImagenCampo, 'bytes-de-la-foto');

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.archivo.evidencia', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get(route('panel.evidencias.archivo', $evidencia))
        ->assertOk()
        ->assertSee('bytes-de-la-foto', false);
});

it('el streaming de una evidencia sin archivo en disco responde 404', function () {
    Storage::fake('r2');

    $evidencia = Evidencia::create([
        'uuid_cliente' => (string) Str::uuid(),
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => 'evidencias/inexistente.jpg',
        'hash' => hash('sha256', 'nada'),
        'fecha' => now(),
    ]);

    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.archivo.404', 'jefe_campo');
    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get(route('panel.evidencias.archivo', $evidencia))->assertNotFound();
});

it('sin el permiso operaciones.trabajo.ver, el streaming del archivo responde 403', function () {
    Storage::fake('r2');

    $evidencia = evidenciaDemo(TipoEvidencia::ImagenCampo);

    [$piloto, $idRol] = usuarioConRolParaTrabajos('piloto.archivo.403', 'piloto');
    entrarAlPanelParaTrabajos($piloto, $idRol);

    $this->get(route('panel.evidencias.archivo', $evidencia))->assertForbidden();
});
