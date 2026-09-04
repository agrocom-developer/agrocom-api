<?php

/**
 * Fixture de datos para tests/Visual/portal-*.spec.ts (HU-41, tarea 55).
 * Crea un contrato para un cliente de portal, con un acta firmada y su
 * reporte técnico generado. Las tres pantallas del portal (avance, actas,
 * reportes) muestran datos de este contrato. Crea también una cuenta de
 * usuario tipo Cliente (SecUser type=Cliente con contrato_id) para login
 * real en los tests visuales.
 *
 * Reutiliza clientes/campos/lotes de `NucleoComercialSeeder` si es posible
 * (ve la nota en `devengos-demo.php` sobre por qué). Como el portal no
 * necesita del mismo cliente que `camila.rojas` (cada cliente es su propio
 * portal), crea un contrato nuevo dentro de un cliente nuevo. La tabla de
 * clientes en el listado de panel interno sí gana una fila nueva, pero ese
 * listado está fuera del scope de esta suite — solo las pantallas del panel
 * (`/panel/*`) y del portal (`/portal/*`) capturan snapshots.
 *
 * Idempotente: usa `firstOrCreate` y chequea si el acta ya existe antes de
 * crear una nueva. Invocado desde `portal-login.spec.ts` vía `docker compose
 * exec app php tests/Visual/fixtures/portal-demo.php`.
 */
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\FirmarActa;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
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
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Crea o reutiliza el cliente de portal
$cliente = Cliente::firstOrCreate(
    ['razon_social' => 'Cliente Portal Demo Visual'],
    ['rut' => '20.999.999-9'],
);

// Crea o reutiliza el contrato del cliente de portal
$contrato = Contrato::firstOrCreate(
    ['cliente_id' => $cliente->id],
    [
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ],
);

// Crea o reutiliza el campo y lote
$campo = Campo::firstOrCreate(
    ['cliente_id' => $cliente->id, 'nombre' => 'Campo Portal Demo'],
    [],
);
$lote = Lote::firstOrCreate(
    ['campo_id' => $campo->id, 'codigo' => 'L-PORTAL-VISUAL'],
    ['hectareas' => '25.00'],
);

// Crea o reutiliza la orden de aplicación
$orden = OrdenAplicacion::firstOrCreate(
    ['contrato_id' => $contrato->id, 'lote_id' => $lote->id, 'nro_aplicacion' => 1],
    [
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ],
);

// Chequea si ya hay un acta firmada para esta orden
$actaExistente = Acta::whereHas('trabajo', fn ($q) => $q->where('orden_id', $orden->id))
    ->whereNotNull('fecha_firma')
    ->exists();

if (! $actaExistente) {
    // Crea el trabajo
    $trabajo = Trabajo::create([
        'uuid_cliente' => 't-'.Str::random(33),
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    // Crea la sesión
    $sesion = Sesion::create([
        'uuid_cliente' => 's-'.Str::random(33),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => PerPersona::firstOrCreate(
            ['nombre' => 'Piloto Portal Visual'],
            ['rol' => RolOperativoPersona::Piloto, 'activo' => true],
        )->id,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoSesion::Validado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);

    // Genera el acta y la firma por el flujo real (mismo camino que
    // `POST /api/trabajos/{uuid}/acta` + `POST /api/actas/{uuid}/firmar`):
    // es lo único que fija `Acta.pdf_path` y dispara `GenerarReporteTecnico`
    // (que a su vez fija `ReporteTecnico.pdf_path`) — saltarse ambos flujos,
    // como hacía la versión anterior de este fixture con `Acta::create()` +
    // `update()` manual, dejaba las dos pantallas de descarga sin el botón
    // que muestran casi siempre en producción (mismo criterio que
    // `devengos-demo.php` con `ValidarSesion`, invariante 3 de CLAUDE.md).
    $acta = app(GenerarActaTrabajo::class)->ejecutar($trabajo, 'a-'.Str::random(33));

    $evidenciaUuid = 'f-'.Str::random(33);
    $evidencia = Evidencia::create([
        'uuid_cliente' => $evidenciaUuid,
        'tipo' => TipoEvidencia::FirmaActa,
        'archivo_url' => "evidencias/firma_acta/2026/09/{$evidenciaUuid}.jpg",
        'hash' => hash('sha256', $evidenciaUuid),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    app(FirmarActa::class)->ejecutar($acta, $evidenciaUuid, 'Ing. Agrónoma Visual', '2026-09-02T16:00:00-04:00');
}

// Crea o reutiliza la cuenta de portal (updateOrCreate para asegurar contraseña correcta)
SecUser::updateOrCreate(
    ['username' => 'cliente.visual.portal'],
    [
        'name' => 'Cliente Portal Visual',
        'password' => bcrypt('Secreta123'),
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
        'state' => true,
    ],
);

echo "portal-demo: OK\n";
