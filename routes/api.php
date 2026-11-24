<?php

use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Api\VersionController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\DronesController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\EquiposController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Api\NotificacionesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\ActaController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\EvidenciaController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\OrdenAplicacionController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\ReporteTecnicoController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\AuxiliaresController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\DocumentosController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\MiProveedorController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\PilotosController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\RegistroProveedorController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\DispositivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\SesionCampoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\CatalogoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\SyncController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\ClientesController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\SolicitudesController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\TerrenosController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\ZonasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API — apps de campo (agrocom-field)
|--------------------------------------------------------------------------
|
| Este archivo sirve exclusivamente a las apps de campo (ADR 0008): nunca
| comparte middleware ni guards con routes/web.php. Los paths siguen la
| espec §8 ("Endpoints principales").
|
| Autenticación por token de dispositivo (HU-03): `auth:sanctum` con el
| guard declarado en config/auth.php sobre el provider `usuarios_internos`, y
| `sanctum.guard` vacío (config/sanctum.php) para que la sesión del panel
| NUNCA autentique acá — que es lo que el ADR 0008 exige y lo que el default
| del paquete (`['web']`) haría si no se lo apagara.
|
*/

// Emisión del token: es el login de la app, así que va sin autenticación —
// pero con throttle, porque un endpoint de credenciales sin límite es un
// oráculo de fuerza bruta. Limitador `login-campo` (SeguridadServiceProvider):
// 6 intentos por minuto por usuario + IP y 60 por IP. No es solo por IP porque
// los dispositivos del lote comparten la IP del enlace satelital.
Route::post('/auth/token', [SesionCampoController::class, 'store'])
    ->middleware('throttle:login-campo')
    ->name('api.auth.token.emitir');

// HU-20: la app todavía no tiene token cuando consulta esto — es lo primero
// que hace antes de operar. Mismo throttle que el login: sin límite sería
// un endpoint de descubrimiento gratis para golpear el bucket de r2.
Route::get('/version', [VersionController::class, 'show'])
    ->middleware('throttle:6,1')
    ->name('api.version.show');

// Marketplace (ADR 0036): registro público de clientes y proveedores. Crean
// cuentas sin autenticación, así que llevan el límite `registro-publico`
// (SeguridadServiceProvider): 5 por minuto y 20 por hora por IP.
Route::post('/clientes/registro', [ClientesController::class, 'store'])
    ->middleware('throttle:registro-publico')
    ->name('api.clientes.registro');

Route::post('/proveedores/registro', [RegistroProveedorController::class, 'store'])
    ->middleware('throttle:registro-publico')
    ->name('api.proveedores.registro');

// Catálogo de zonas: el formulario de registro lo necesita antes de tener
// cuenta. Mismo throttle suave que /version: es lectura pública.
Route::get('/zonas', [ZonasController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.zonas.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/sesion', [SesionCampoController::class, 'show'])->name('api.auth.sesion');

    // Cierra la sesión de ESTE dispositivo (el del token que firma el
    // request). Revocar el de otro es cosa del panel, con permiso propio.
    Route::delete('/auth/token', [SesionCampoController::class, 'destroy'])->name('api.auth.token.revocar');

    // Dispositivos del propio operario. El scoping vive en los casos de uso
    // (consultan desde la relación del usuario del token, nunca desde la
    // tabla global): un id ajeno devuelve 404, igual que en el portal del
    // cliente (invariante 5 de CLAUDE.md).
    Route::get('/dispositivos', [DispositivoController::class, 'index'])->name('api.dispositivos.index');
    Route::get('/dispositivos/{id}', [DispositivoController::class, 'show'])
        ->whereNumber('id')
        ->name('api.dispositivos.show');

    // Espec §8: GET /api/ordenes?lote_id=&estado= — órdenes para el piloto.
    Route::get('/ordenes', [OrdenAplicacionController::class, 'index'])->name('api.ordenes.index');

    // Espec §2.1 punto 6: GET /api/sync/catalogo?desde= — pull de catálogo
    // con cursor (TE-06 parcial: órdenes, lotes y personas; ver
    // runs/08-diseno.md para lo que queda afuera y por qué).
    Route::get('/sync/catalogo', [CatalogoController::class, 'index'])->name('api.sync.catalogo');

    // Espec §2.1 puntos 3 a 5: POST /api/sync — push en lote de la cola
    // offline. TE-05 (tarea 09): solo trabajo y sesion; ver "Recorte de
    // alcance" en el prompt de la tarea para mezcla/recarga/incidencia/acta.
    Route::post('/sync', [SyncController::class, 'store'])->name('api.sync.store');

    // Espec §2.1 punto 7: cola separada de evidencias — fuera del "sobre"
    // JSON de /api/sync a propósito (lleva un binario). TE-07 parte servidor
    // (tarea 19).
    Route::post('/evidencias', [EvidenciaController::class, 'store'])->name('api.evidencias.store');

    // Espec §4.3/§314-315, reinterpretados sobre `uuid_cliente` en vez de
    // `id` (HU-17, tarea 24; ver runs/24.md): acta de conformidad por lote.
    // Primeros endpoints de esta API que además del token exigen un permiso
    // `sec_permission` puntual (`operaciones.acta.generar`/`.firmar`).
    Route::post('/trabajos/{trabajo:uuid_cliente}/acta', [ActaController::class, 'generar'])->name('api.actas.generar');
    Route::post('/actas/{acta:uuid_cliente}/firmar', [ActaController::class, 'firmar'])->name('api.actas.firmar');
    Route::get('/actas/{acta:uuid_cliente}/pdf', [ActaController::class, 'pdf'])->name('api.actas.pdf');

    // Espec §8/§9/§328 (HU-18, tarea 25): reporte técnico por lote — generado
    // solo al firmar el acta (`GenerarReporteTecnico`, enganchado en
    // `FirmarActa`), nunca por este endpoint. `{id}` = `trabajo_id` (no
    // `uuid_cliente`: el reporte no nace de una acción del dispositivo, ver
    // runs/25.md).
    Route::get('/reportes/lote/{trabajo}', [ReporteTecnicoController::class, 'mostrar'])
        ->whereNumber('trabajo')
        ->name('api.reportes.lote');

    // Marketplace (ADR 0036): el proveedor gestiona lo SUYO. El permiso del rol
    // del token es la puerta; el alcance (solo sus registros, un id ajeno es
    // 404) lo hacen los casos de uso.
    Route::get('/proveedores/mi', [MiProveedorController::class, 'show'])->name('api.proveedores.mi');
    Route::post('/proveedores/mi/reenviar', [MiProveedorController::class, 'reenviar'])->name('api.proveedores.mi.reenviar');

    Route::get('/proveedores/pilotos', [PilotosController::class, 'index'])->name('api.proveedores.pilotos.index');
    Route::post('/proveedores/pilotos', [PilotosController::class, 'store'])->name('api.proveedores.pilotos.store');
    Route::post('/proveedores/pilotos/{id}/reenviar', [PilotosController::class, 'reenviar'])
        ->whereNumber('id')
        ->name('api.proveedores.pilotos.reenviar');

    Route::get('/proveedores/auxiliares', [AuxiliaresController::class, 'index'])->name('api.proveedores.auxiliares.index');
    Route::post('/proveedores/auxiliares', [AuxiliaresController::class, 'store'])->name('api.proveedores.auxiliares.store');
    Route::post('/proveedores/auxiliares/{id}/reenviar', [AuxiliaresController::class, 'reenviar'])
        ->whereNumber('id')
        ->name('api.proveedores.auxiliares.reenviar');

    Route::get('/proveedores/documentos', [DocumentosController::class, 'index'])->name('api.proveedores.documentos.index');
    Route::post('/proveedores/documentos', [DocumentosController::class, 'store'])->name('api.proveedores.documentos.store');

    Route::get('/proveedores/drones', [DronesController::class, 'index'])->name('api.proveedores.drones.index');
    Route::post('/proveedores/drones', [DronesController::class, 'store'])->name('api.proveedores.drones.store');
    Route::put('/proveedores/drones/{id}', [DronesController::class, 'update'])
        ->whereNumber('id')
        ->name('api.proveedores.drones.update');
    Route::post('/proveedores/drones/{id}/reenviar', [DronesController::class, 'reenviar'])
        ->whereNumber('id')
        ->name('api.proveedores.drones.reenviar');
    Route::post('/proveedores/drones/{id}/documentos', [DronesController::class, 'cargarDocumento'])
        ->whereNumber('id')
        ->name('api.proveedores.drones.documentos');

    Route::get('/proveedores/equipos', [EquiposController::class, 'index'])->name('api.proveedores.equipos.index');
    Route::post('/proveedores/equipos', [EquiposController::class, 'store'])->name('api.proveedores.equipos.store');
    Route::put('/proveedores/equipos/{id}', [EquiposController::class, 'update'])
        ->whereNumber('id')
        ->name('api.proveedores.equipos.update');

    // Cliente: su propio perfil (el alta es pública, arriba).
    Route::get('/clientes/mi', [ClientesController::class, 'show'])->name('api.clientes.mi');

    // Marketplace (ADR 0027): el cliente registra sus terrenos y pide servicios.
    // Superficie online, sin outbox (ADR 0028). El alcance (solo lo propio, un
    // id ajeno es 404) lo hacen los casos de uso; el permiso del rol del token
    // es la puerta.
    Route::get('/terrenos', [TerrenosController::class, 'index'])->name('api.terrenos.index');
    Route::post('/terrenos', [TerrenosController::class, 'store'])->name('api.terrenos.store');
    Route::get('/terrenos/{id}', [TerrenosController::class, 'show'])
        ->whereNumber('id')
        ->name('api.terrenos.show');
    Route::put('/terrenos/{id}', [TerrenosController::class, 'update'])
        ->whereNumber('id')
        ->name('api.terrenos.update');

    Route::get('/solicitudes', [SolicitudesController::class, 'index'])->name('api.solicitudes.index');
    Route::post('/solicitudes', [SolicitudesController::class, 'store'])->name('api.solicitudes.store');
    Route::get('/solicitudes/{id}', [SolicitudesController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes.show');
    Route::get('/solicitudes/{id}/estimacion', [SolicitudesController::class, 'estimacion'])
        ->whereNumber('id')
        ->name('api.solicitudes.estimacion');
    Route::post('/solicitudes/{id}/publicar', [SolicitudesController::class, 'publicar'])
        ->whereNumber('id')
        ->name('api.solicitudes.publicar');
    Route::post('/solicitudes/{id}/cancelar', [SolicitudesController::class, 'cancelar'])
        ->whereNumber('id')
        ->name('api.solicitudes.cancelar');

    // Avisos in-app de la cuenta (ADR 0025): las apps no tienen campana de panel.
    Route::get('/notificaciones', [NotificacionesController::class, 'index'])->name('api.notificaciones.index');
    Route::post('/notificaciones/{id}/leida', [NotificacionesController::class, 'leer'])
        ->whereNumber('id')
        ->name('api.notificaciones.leida');
});
