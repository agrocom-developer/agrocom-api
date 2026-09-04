<?php

/**
 * Fixture de datos para tests/Visual/evidencias-trabajo.spec.ts (HU-42,
 * tarea 56). `panel.trabajos.evidencias` necesita un trabajo con las tres
 * evidencias pobladas a la vez (imagen de campo, firma del acta, incidencia
 * con foto) para capturar el estado "más interesante" — mismo criterio que
 * `rendiciones-demo.php` con el estado `presentada`.
 *
 * Reusa el cliente/lote/orden de `NucleoComercialSeeder` (Agropecuaria San
 * Jorge, lote L-01) y la persona "Piloto Demo Facturas" ya sembrada por
 * `facturas-demo.php`, mismo criterio que esa y `devengos-demo.php`: evita
 * filas nuevas en `com_clientes`/`com_campos`/`com_contratos`/
 * `ope_ordenes_aplicacion`/`per_personas` que romperían snapshots de otras
 * pantallas (`clientes`, `campos`, `contratos`, `ordenes`, `personas`).
 *
 * `Storage::fake('r2')` (mismo motivo que `facturas-demo.php`): el disco
 * `r2` no tiene credenciales reales en este entorno local (`R2_*` vacías),
 * así que las tres miniaturas de la galería van a salir con imagen rota en
 * la captura — es una limitación del entorno que ya afecta a
 * `panel.trabajos.acta-pdf`/`reporte-pdf` (mismo disco), no algo introducido
 * por esta pantalla. La captura sigue siendo válida y estable para
 * regresión visual: verifica layout/tokens/contraste del grid y las
 * tarjetas, no el contenido real de la foto (ver runs/56.md).
 *
 * Bootea Laravel manualmente, mismo patrón que `facturas-demo.php`/
 * `devengos-demo.php`. Se invoca desde `evidencias-trabajo.spec.ts` vía
 * `docker compose exec app php tests/Visual/fixtures/evidencias-trabajo-demo.php`.
 *
 * Idempotente: el trabajo nace con un `uuid_cliente` fijo; si ya existe, se
 * reusa en vez de reconstruir la cadena de evidencias.
 */
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Storage::fake('r2');

const NIT_DEMO = '1023456022';
const UUID_TRABAJO = 'uuid-trabajo-demo-evidencias';

$cliente = Cliente::where('nit', NIT_DEMO)->firstOrFail();
$lote = Lote::whereHas('campo', fn ($consulta) => $consulta->where('cliente_id', $cliente->id))
    ->where('codigo', 'L-01')
    ->firstOrFail();
$orden = OrdenAplicacion::where('lote_id', $lote->id)->where('nro_aplicacion', 1)->firstOrFail();

$piloto = PerPersona::firstOrCreate(
    ['nombre' => 'Piloto Demo Facturas'],
    ['rol' => RolOperativoPersona::Piloto, 'activo' => true],
);

function evidenciaDemoGaleria(TipoEvidencia $tipo, string $uuid): Evidencia
{
    $existente = Evidencia::where('uuid_cliente', $uuid)->first();

    if ($existente !== null) {
        return $existente;
    }

    $ruta = "evidencias/{$tipo->value}/2026/09/{$uuid}.jpg";
    Storage::disk('r2')->put($ruta, "contenido-demo-{$uuid}");

    return Evidencia::create([
        'uuid_cliente' => $uuid,
        'tipo' => $tipo,
        'archivo_url' => $ruta,
        'hash' => hash('sha256', $uuid),
        'fecha' => now(),
    ]);
}

$trabajo = Trabajo::where('uuid_cliente', UUID_TRABAJO)->first();

if ($trabajo === null) {
    $trabajo = Trabajo::create([
        'uuid_cliente' => UUID_TRABAJO,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => now(),
        'fin' => now(),
    ]);
}

$sesion = Sesion::where('uuid_cliente', 'uuid-sesion-demo-evidencias')->first();

if ($sesion === null) {
    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-demo-evidencias',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => now(),
        'fin' => now(),
        'motivo_cierre' => 'completado',
    ]);
}

if ($trabajo->imagen_campo_evidencia_id === null) {
    $imagenCampo = evidenciaDemoGaleria(TipoEvidencia::ImagenCampo, 'uuid-evidencia-demo-imagen-campo');
    $trabajo->update(['imagen_campo_evidencia_id' => $imagenCampo->id]);
}

$acta = Acta::where('trabajo_id', $trabajo->id)->first();

if ($acta === null) {
    $firma = evidenciaDemoGaleria(TipoEvidencia::FirmaActa, 'uuid-evidencia-demo-firma-acta');

    $acta = Acta::create([
        'uuid_cliente' => 'uuid-acta-demo-evidencias',
        'trabajo_id' => $trabajo->id,
        'hectareas_conformadas' => '12.00',
        'estado' => EstadoActa::Firmada,
        'evidencia_firma_id' => $firma->id,
        'firmante' => 'Ing. Agrónoma Demo',
        'fecha_firma' => now(),
    ]);
}

$incidencia = Incidencia::where('uuid_cliente', 'uuid-incidencia-demo-evidencias')->first();

if ($incidencia === null) {
    $fotoIncidencia = evidenciaDemoGaleria(TipoEvidencia::FotoIncidencia, 'uuid-evidencia-demo-foto-incidencia');

    Incidencia::create([
        'uuid_cliente' => 'uuid-incidencia-demo-evidencias',
        'sesion_id' => $sesion->id,
        'tipo' => TipoIncidencia::Mecanica,
        'descripcion' => 'Falla de motor en pleno vuelo, aterrizaje de emergencia.',
        'hora' => now(),
        'evidencia_foto_id' => $fotoIncidencia->id,
    ]);
}

// Última línea, parseada por evidencias-trabajo.spec.ts para navegar directo
// a `/panel/trabajos/{id}/evidencias` sin depender de un texto de fila único
// en el índice (varios fixtures de otras HU comparten la misma orden L-01).
echo json_encode(['trabajo_id' => $trabajo->id]).PHP_EOL;
