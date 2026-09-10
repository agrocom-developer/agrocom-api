<?php

use App\Dominios\Comercial\Aplicacion\ObtenerInformeAvanceContratos;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
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

/*
 * HU-52 (tarea 75): "como dueño, quiero un informe de avance de contratos,
 * por cultivo y por cliente" — reemplaza HU-32 (tarea 46). Entrada obligatoria
 * de cliente y cultivo, filtros avanzados en offcanvas, chips de filtros
 * aplicados, dos pestañas de visualización, barras de avance por tramo de
 * color (espec §9.1).
 *
 * Los datos de avance se sacan del caso de uso backend
 * `ObtenerInformeAvanceContratos` (no se prueban acá, viven en
 * `InformeAvanceContratosTest.php`). La vista se ejercita por HTTP contra
 * el controlador `ReportesComercialesController`, sin burlar la
 * autenticación.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

// Helpers para construir datos de prueba

function clientePantalla(string $sufijo): Cliente
{
    return Cliente::create(['razon_social' => "Cliente informe {$sufijo}", 'tipo_persona' => 'juridica']);
}

function campaniaPantalla(Cliente $cliente, string $sufijo): object
{
    $campania = DB::table('cpn_campanias')->insertGetId([
        'cliente_id' => $cliente->id,
        'codigo' => "CAM-{$sufijo}",
        'nombre' => "Campaña {$sufijo}",
        'fecha_inicio' => '2026-09-01',
        'fecha_fin' => '2026-12-31',
        'estado' => 'abierta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) [
        'id' => $campania,
        'codigo' => "CAM-{$sufijo}",
        'cliente_id' => $cliente->id,
    ];
}

function cultivoPantalla(string $sufijo): Cultivo
{
    return Cultivo::create(['nombre' => "Cultivo {$sufijo}", 'activo' => true]);
}

function contratoPantalla(string $sufijo, Cliente $cliente, object $campania, string $hectareasContratadas = '100.00'): Contrato
{
    return Contrato::create([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => $hectareasContratadas,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '25.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'fecha_fin' => '2026-12-15',
        'estado' => EstadoContrato::Vigente,
    ]);
}

function siembraPantalla(Contrato $contrato, Cultivo $cultivo): void
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo siembra {$contrato->id}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-{$contrato->id}", 'hectareas' => '100.00']);

    DB::table('com_lote_campania')->insert([
        'lote_id' => $lote->id,
        'campania_id' => $contrato->campania_id,
        'cultivo_id' => $cultivo->id,
        'hectareas_sembradas' => '100.00',
        'fecha_siembra' => '2026-09-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ordenSiembraPantalla(Contrato $contrato, Cultivo $cultivo, string $sufijo, string $hectareas = '50.00'): OrdenAplicacion
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo orden {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-ORD-{$sufijo}", 'hectareas' => $hectareas]);

    // Registra la siembra del lote en esta campaña
    DB::table('com_lote_campania')->insert([
        'lote_id' => $lote->id,
        'campania_id' => $contrato->campania_id,
        'cultivo_id' => $cultivo->id,
        'hectareas_sembradas' => $hectareas,
        'fecha_siembra' => '2026-09-01',
        'created_at' => now(),
        'updated_at' => now(),
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

function actaFirmadaPantalla(string $sufijo, Contrato $contrato, Cultivo $cultivo, string $hectareas = '50.00'): Acta
{
    $orden = ordenSiembraPantalla($contrato, $cultivo, $sufijo, $hectareas);
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-informe-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $piloto = PerPersona::create(['nombre' => "Piloto {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-informe-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Validado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);

    // Genera y firma el acta vía API
    $usuario = SecUser::factory()->create();
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-informe-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $evidenciaUuid = "uuid-firma-informe-{$sufijo}";
    Evidencia::create([
        'uuid_cliente' => $evidenciaUuid,
        'tipo' => TipoEvidencia::FirmaActa,
        'archivo_url' => "evidencias/firma_acta/2026/09/{$evidenciaUuid}.jpg",
        'hash' => hash('sha256', $evidenciaUuid),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaUuid,
            'firmante' => 'Ing. Agrónoma de Prueba',
            'fecha_firma' => '2026-09-02T16:00:00-04:00',
        ])
        ->assertOk();

    return $acta->fresh();
}

function usuarioConRolPantalla(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelPantalla(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function duenoPantalla(): SecUser
{
    [$duenoPantalla, $idRol] = usuarioConRolPantalla('dueno.informe.'.uniqid(), 'dueno');
    entrarAlPanelPantalla($duenoPantalla, $idRol);

    return $duenoPantalla;
}

// Tests

it('la pantalla responde 200 con el rol dueño', function () {
    duenoPantalla();

    $this->get(route('panel.reportes.comercial.index'))
        ->assertOk()
        ->assertSee(__('comercial.reportes_comerciales.titulo'));
});

it('un rol sin el permiso recibe 403, incluido encargado_operaciones', function () {
    [$encargado, $idRol] = usuarioConRolPantalla('encargado.informe', 'encargado_operaciones');
    entrarAlPanelPantalla($encargado, $idRol);

    $this->get(route('panel.reportes.comercial.index'))->assertForbidden();
});

it('el ítem de menú de reportes comerciales apunta a la ruta correcta gateado por comercial.reporte.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.reportes.items.comerciales')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.reporte.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.reportes.comercial.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

it('primera visita (sin consultado ni filtros): muestra estado vacío "primera visita"', function () {
    duenoPantalla();

    $this->get(route('panel.reportes.comercial.index'))
        ->assertOk()
        ->assertSee(__('comercial.reportes_comerciales.estado.primera_visita'));
});

it('sin cliente_ids en un submit con consultado=1: muestra error bajo el selector cliente', function () {
    $cliente = clientePantalla('error-cliente');
    $cultivo = cultivoPantalla('error-cliente');
    $campania = campaniaPantalla($cliente, 'error-cliente');
    contratoPantalla('error-cliente', $cliente, $campania);
    siembraPantalla(Contrato::first(), $cultivo);

    duenoPantalla();

    // POST con consultado=1 pero sin cliente_ids[] (cultivo sí)
    $this->get(route('panel.reportes.comercial.index', ['consultado' => 1, 'cultivo_ids' => [$cultivo->id]]))
        ->assertOk()
        ->assertSee(__('comercial.reportes_comerciales.entrada.error_cliente'));
    // Solo verifica que aparezca el error, no que desaparezca algo específico
});

it('sin cultivo_ids en un submit con consultado=1: muestra error bajo el selector cultivo', function () {
    $cliente = clientePantalla('error-cultivo');
    $cultivo = cultivoPantalla('error-cultivo');
    $campania = campaniaPantalla($cliente, 'error-cultivo');
    contratoPantalla('error-cultivo', $cliente, $campania);

    duenoPantalla();

    // POST con consultado=1 pero sin cultivo_ids[] (cliente sí)
    $this->get(route('panel.reportes.comercial.index', ['consultado' => 1, 'cliente_ids' => [$cliente->id]]))
        ->assertOk()
        ->assertSee(__('comercial.reportes_comerciales.entrada.error_cultivo'));
    // Solo verifica que aparezca el error, no que desaparezca algo específico
});

it('con filtros válidos que no matchean nada: muestra estado vacío "sin resultados"', function () {
    $cliente = clientePantalla('sin-resultados');
    $cultivo = cultivoPantalla('sin-resultados');

    duenoPantalla();

    // Existe el cliente y el cultivo, pero sin contrato que los combine
    $respuesta = $this->get(route('panel.reportes.comercial.index', [
        'consultado' => 1,
        'cliente_ids' => [$cliente->id],
        'cultivo_ids' => [$cultivo->id],
    ]));

    $respuesta->assertOk()
        ->assertSee(__('comercial.reportes_comerciales.estado.sin_resultados'));
});

it('con datos válidos: muestra cultivo y contrato en la pestaña "Por cultivo"', function () {
    $cliente = clientePantalla('con-datos');
    $cultivo = cultivoPantalla('con-datos');
    $campania = campaniaPantalla($cliente, 'con-datos');
    $contrato = contratoPantalla('con-datos', $cliente, $campania);
    siembraPantalla($contrato, $cultivo);
    actaFirmadaPantalla('con-datos', $contrato, $cultivo, '50.00');

    duenoPantalla();

    $respuesta = $this->get(route('panel.reportes.comercial.index', [
        'consultado' => 1,
        'cliente_ids' => [$cliente->id],
        'cultivo_ids' => [$cultivo->id],
        'tab' => 'por_cultivo',
    ]));

    $respuesta->assertOk()
        ->assertSee('Cultivo con-datos') // Nombre del cultivo
        ->assertSee('Contrato #'.$contrato->id) // Identificador del contrato
        ->assertSee('50,00'); // Hectáreas aplicadas (con formato local)
});

it('con datos válidos: muestra cliente dentro del cultivo en la pestaña "Por cliente"', function () {
    $cliente = clientePantalla('por-cliente');
    $cultivo = cultivoPantalla('por-cliente');
    $campania = campaniaPantalla($cliente, 'por-cliente');
    $contrato = contratoPantalla('por-cliente', $cliente, $campania);
    siembraPantalla($contrato, $cultivo);
    actaFirmadaPantalla('por-cliente', $contrato, $cultivo, '50.00');

    duenoPantalla();

    $respuesta = $this->get(route('panel.reportes.comercial.index', [
        'consultado' => 1,
        'cliente_ids' => [$cliente->id],
        'cultivo_ids' => [$cultivo->id],
        'tab' => 'por_cliente',
    ]));

    $respuesta->assertOk()
        ->assertSee('Cultivo por-cliente') // Encabezado de cultivo
        ->assertSee('Cliente informe por-cliente') // Nombre del cliente dentro
        ->assertSee('Contrato #'.$contrato->id);
});

it('quitar un chip de cliente regenera el informe sin ese cliente', function () {
    $clienteA = clientePantalla('chip-a');
    $clienteB = clientePantalla('chip-b');
    $cultivo = cultivoPantalla('chip');
    $campaniaA = campaniaPantalla($clienteA, 'chip-a');
    $campaniaB = campaniaPantalla($clienteB, 'chip-b');
    $contratoA = contratoPantalla('chip-a', $clienteA, $campaniaA, '50.00');
    $contratoB = contratoPantalla('chip-b', $clienteB, $campaniaB, '100.00');
    siembraPantalla($contratoA, $cultivo);
    siembraPantalla($contratoB, $cultivo);
    actaFirmadaPantalla('chip-a', $contratoA, $cultivo, '30.00');
    actaFirmadaPantalla('chip-b', $contratoB, $cultivo, '80.00');

    duenoPantalla();

    // Primero, con ambos clientes
    $respuesta1 = $this->get(route('panel.reportes.comercial.index', [
        'consultado' => 1,
        'cliente_ids' => [$clienteA->id, $clienteB->id],
        'cultivo_ids' => [$cultivo->id],
    ]));

    // Pestaña "Por cultivo" (default): la tabla identifica cada fila por
    // contrato, no por nombre de cliente — el nombre de cliente aparece
    // igual en la lista de opciones del checkbox-group del offcanvas de
    // filtros (`clientesDisponibles` no se acota a los ya elegidos, lista
    // TODOS los clientes con contrato para poder sumar más), así que
    // `assertSee`/`assertDontSee` sobre el nombre del cliente no distingue
    // "está en el informe" de "está disponible como opción de filtro". El
    // identificador de contrato sí es exclusivo del informe.
    $respuesta1->assertSee('Contrato #'.$contratoA->id)
        ->assertSee('Contrato #'.$contratoB->id);

    // Quitar clienteB: la URL pide solo clienteA (el chip de cliente
    // lleva un link que hace `except('cliente_ids')` — simula quitarlo)
    $respuesta2 = $this->get(route('panel.reportes.comercial.index', [
        'consultado' => 1,
        'cliente_ids' => [$clienteA->id], // Solo A
        'cultivo_ids' => [$cultivo->id],
    ]));

    $respuesta2->assertOk()
        ->assertSee('Contrato #'.$contratoA->id)
        ->assertDontSee('Contrato #'.$contratoB->id);
});
