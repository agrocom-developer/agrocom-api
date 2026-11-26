<?php

use App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Api\CalificacionesDelClienteController;
use App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Api\CalificacionesDelProveedorController;
use App\Dominios\Disputas\Infraestructura\Http\Controllers\Api\DisputasController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\DronesController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\EquiposController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Api\FotosDeDronController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\MensajesController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\MercadoController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\OfertasController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\PagoController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\PrecioFinalController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\PreferenciasController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api\SeguimientoController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Api\DispositivosPushController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Api\NotificacionesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\EvidenciaController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\DocumentosController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\MiProveedorController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\PilotosController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api\RegistroProveedorController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\DispositivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\FotoDePerfilController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\SesionCampoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\CatalogoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\SyncController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\ClientesController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Api\ContactosController;
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

// Foto del estado de un terreno: la URL es firmada y de corta vida (la entregan el terreno del
// cliente dueño y el detalle de la solicitud abierta), así que la imagen carga sin token.
Route::get('/terrenos/fotos/{foto}/archivo', [TerrenosController::class, 'archivoDeFoto'])
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->whereNumber('foto')
    ->name('api.terrenos.fotos.archivo');

// Foto de perfil de una cuenta: URL firmada y de corta vida que solo emite el servidor
// (la propia sesión y la tarjeta del proveedor que ve el cliente), así que carga sin token.
Route::get('/personas/{usuario}/foto', [FotoDePerfilController::class, 'archivo'])
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->whereNumber('usuario')
    ->name('api.personas.foto');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/sesion', [SesionCampoController::class, 'show'])->name('api.auth.sesion');

    // Cierra la sesión de ESTE dispositivo (el del token que firma el
    // request). Revocar el de otro es cosa del panel, con permiso propio.
    Route::delete('/auth/token', [SesionCampoController::class, 'destroy'])->name('api.auth.token.revocar');

    // Foto de perfil de la propia cuenta (cliente, proveedor o piloto).
    Route::put('/perfil/foto', [FotoDePerfilController::class, 'update'])->name('api.perfil.foto.update');
    Route::delete('/perfil/foto', [FotoDePerfilController::class, 'destroy'])->name('api.perfil.foto.destroy');

    // Dispositivos del propio operario. El scoping vive en los casos de uso
    // (consultan desde la relación del usuario del token, nunca desde la
    // tabla global): un id ajeno devuelve 404, igual que en el portal del
    // cliente (invariante 5 de CLAUDE.md).
    Route::get('/dispositivos', [DispositivoController::class, 'index'])->name('api.dispositivos.index');
    // Token de push del aparato (`negociacion-contrato.md` §7): se registra al iniciar sesión y se retira al cerrarla.
    Route::post('/dispositivos', [DispositivosPushController::class, 'store'])->name('api.dispositivos.push.store');
    Route::delete('/dispositivos/{token}', [DispositivosPushController::class, 'destroy'])
        ->where('token', '.+')
        ->name('api.dispositivos.push.destroy');
    Route::get('/dispositivos/{id}', [DispositivoController::class, 'show'])
        ->whereNumber('id')
        ->name('api.dispositivos.show');

    // Espec §2.1 punto 6: GET /api/sync/catalogo?desde= — pull de catálogo
    // con cursor: personas, servicios asignados al equipo del operario (ADR 0037)
    // y límites de condiciones de vuelo.
    Route::get('/sync/catalogo', [CatalogoController::class, 'index'])->name('api.sync.catalogo');

    // Espec §2.1 puntos 3 a 5: POST /api/sync — push en lote de la cola
    // offline. TE-05 (tarea 09): solo trabajo y sesion; ver "Recorte de
    // alcance" en el prompt de la tarea para mezcla/recarga/incidencia/acta.
    Route::post('/sync', [SyncController::class, 'store'])->name('api.sync.store');

    // Espec §2.1 punto 7: cola separada de evidencias — fuera del "sobre"
    // JSON de /api/sync a propósito (lleva un binario). TE-07 parte servidor
    // (tarea 19).
    Route::post('/evidencias', [EvidenciaController::class, 'store'])->name('api.evidencias.store');

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

    Route::post('/proveedores/drones/{id}/fotos', [FotosDeDronController::class, 'store'])
        ->whereNumber('id')
        ->name('api.proveedores.drones.fotos.store');
    Route::get('/proveedores/drones/{id}/fotos', [FotosDeDronController::class, 'index'])
        ->whereNumber('id')
        ->name('api.proveedores.drones.fotos.index');
    Route::delete('/proveedores/drones/{id}/fotos/{foto}', [FotosDeDronController::class, 'destroy'])
        ->whereNumber(['id', 'foto'])
        ->name('api.proveedores.drones.fotos.destroy');
    Route::get('/drones/fotos/{foto}/archivo', [FotosDeDronController::class, 'archivo'])
        ->whereNumber('foto')
        ->name('api.drones.fotos.archivo');

    Route::get('/proveedores/equipos', [EquiposController::class, 'index'])->name('api.proveedores.equipos.index');
    Route::post('/proveedores/equipos', [EquiposController::class, 'store'])->name('api.proveedores.equipos.store');
    Route::put('/proveedores/equipos/{id}', [EquiposController::class, 'update'])
        ->whereNumber('id')
        ->name('api.proveedores.equipos.update');

    // Negociación oferta-demanda (`negociacion-contrato.md`): el piloto ve el mapa de solicitudes
    // abiertas, ofrece su precio por hectárea y negocia por rondas con el cliente, que elige. Requieren
    // red (no pasan por el outbox). El permiso del rol del token es la puerta; el alcance (solo lo
    // propio, un id ajeno es 404) lo hacen los casos de uso.
    Route::get('/solicitudes-abiertas', [MercadoController::class, 'index'])->name('api.solicitudes-abiertas.index');
    Route::get('/solicitudes-abiertas/resumen-zonas', [MercadoController::class, 'resumen'])->name('api.solicitudes-abiertas.resumen');
    Route::get('/solicitudes-abiertas/{id}', [MercadoController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes-abiertas.show');

    Route::get('/ofertas', [OfertasController::class, 'index'])->name('api.ofertas.index');
    Route::get('/ofertas/{id}', [OfertasController::class, 'show'])
        ->whereNumber('id')
        ->name('api.ofertas.show');
    Route::post('/ofertas/{id}/contraofertar', [OfertasController::class, 'contraofertar'])
        ->whereNumber('id')
        ->name('api.ofertas.contraofertar');
    Route::post('/ofertas/{id}/aceptar', [OfertasController::class, 'aceptar'])
        ->whereNumber('id')
        ->name('api.ofertas.aceptar');
    Route::post('/ofertas/{id}/rechazar', [OfertasController::class, 'rechazar'])
        ->whereNumber('id')
        ->name('api.ofertas.rechazar');
    Route::post('/ofertas/{id}/retirar', [OfertasController::class, 'retirar'])
        ->whereNumber('id')
        ->name('api.ofertas.retirar');
    Route::post('/solicitudes/{id}/ofertas', [OfertasController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.ofertas.store');
    Route::get('/solicitudes/{id}/ofertas', [OfertasController::class, 'deLaSolicitud'])
        ->whereNumber('id')
        ->name('api.solicitudes.ofertas.index');

    // Ejecución: el piloto marca inicio, avance y cierre (idempotentes por `uuid_cliente`), sube
    // fotos y envía su última ubicación; el cliente ve el avance, las fotos y dónde está el piloto.
    Route::post('/solicitudes/{id}/inicio', [SeguimientoController::class, 'inicio'])
        ->whereNumber('id')
        ->name('api.solicitudes.inicio');
    Route::post('/solicitudes/{id}/avance', [SeguimientoController::class, 'avance'])
        ->whereNumber('id')
        ->name('api.solicitudes.avance.store');
    Route::post('/solicitudes/{id}/cierre', [SeguimientoController::class, 'cierre'])
        ->whereNumber('id')
        ->name('api.solicitudes.cierre');
    Route::get('/solicitudes/{id}/avance', [SeguimientoController::class, 'mostrar'])
        ->whereNumber('id')
        ->name('api.solicitudes.avance.show');
    Route::post('/solicitudes/{id}/evidencias', [SeguimientoController::class, 'subirEvidencia'])
        ->whereNumber('id')
        ->name('api.solicitudes.evidencias.store');
    Route::get('/evidencias/{id}/archivo', [SeguimientoController::class, 'archivo'])
        ->whereNumber('id')
        ->name('api.evidencias.archivo');
    // Pago fuera de la aplicación (ADR 0034): el proveedor informa que lo recibió; el cliente lo consulta.
    Route::post('/solicitudes/{id}/pago-confirmado', [PagoController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.pago-confirmado.store');
    Route::get('/solicitudes/{id}/pago-confirmado', [PagoController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes.pago-confirmado.show');
    // Precio final: el cliente, que paga, lo confirma o abre una disputa; ambas partes lo consultan.
    Route::get('/solicitudes/{id}/precio-final', [PrecioFinalController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes.precio-final.show');
    Route::post('/solicitudes/{id}/precio-final/confirmar', [PrecioFinalController::class, 'confirmar'])
        ->whereNumber('id')
        ->name('api.solicitudes.precio-final.confirmar');
    Route::put('/ubicacion', [SeguimientoController::class, 'enviarUbicacion'])->name('api.ubicacion.update');
    Route::get('/solicitudes/{id}/ubicacion-piloto', [SeguimientoController::class, 'verUbicacion'])
        ->whereNumber('id')
        ->name('api.solicitudes.ubicacion-piloto');
    Route::post('/solicitudes/{id}/cancelar-proveedor', [SeguimientoController::class, 'cancelarProveedor'])
        ->whereNumber('id')
        ->name('api.solicitudes.cancelar-proveedor');
    Route::get('/reputacion', [SeguimientoController::class, 'reputacion'])->name('api.reputacion.show');

    // Chat sencillo entre el cliente y el proveedor adjudicado: consulta incremental por cursor.
    Route::get('/solicitudes/{id}/mensajes', [MensajesController::class, 'index'])
        ->whereNumber('id')
        ->name('api.solicitudes.mensajes.index');
    Route::post('/solicitudes/{id}/mensajes', [MensajesController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.mensajes.store');
    Route::post('/solicitudes/{id}/mensajes/fotos', [MensajesController::class, 'foto'])
        ->whereNumber('id')
        ->name('api.solicitudes.mensajes.fotos');
    Route::get('/mensajes/{id}/archivo', [MensajesController::class, 'archivo'])
        ->whereNumber('id')
        ->name('api.mensajes.archivo');
    Route::post('/solicitudes/{id}/mensajes/leidos', [MensajesController::class, 'leidos'])
        ->whereNumber('id')
        ->name('api.solicitudes.mensajes.leidos');

    // Preferencias de aviso: el piloto activa o desactiva las nuevas solicitudes en sus zonas.
    Route::get('/notificaciones/preferencias', [PreferenciasController::class, 'show'])->name('api.notificaciones.preferencias.show');
    Route::put('/notificaciones/preferencias', [PreferenciasController::class, 'update'])->name('api.notificaciones.preferencias.update');

    // Cliente: su propio perfil (el alta es pública, arriba).
    Route::get('/clientes/mi', [ClientesController::class, 'show'])->name('api.clientes.mi');

    // Contactos de referencia del cliente: las personas con quienes el proveedor coordina el servicio.
    Route::get('/clientes/mi/contactos', [ContactosController::class, 'index'])->name('api.clientes.contactos.index');
    Route::post('/clientes/mi/contactos', [ContactosController::class, 'store'])->name('api.clientes.contactos.store');
    Route::put('/clientes/mi/contactos/{id}', [ContactosController::class, 'update'])
        ->whereNumber('id')
        ->name('api.clientes.contactos.update');
    Route::delete('/clientes/mi/contactos/{id}', [ContactosController::class, 'destroy'])
        ->whereNumber('id')
        ->name('api.clientes.contactos.destroy');

    // Marketplace (ADR 0027): el cliente registra sus terrenos y pide servicios.
    // Superficie online, sin outbox (ADR 0028). El alcance (solo lo propio, un
    // id ajeno es 404) lo hacen los casos de uso; el permiso del rol del token
    // es la puerta.
    Route::get('/terrenos', [TerrenosController::class, 'index'])->name('api.terrenos.index');
    Route::post('/terrenos', [TerrenosController::class, 'store'])->name('api.terrenos.store');
    Route::get('/terrenos/{id}', [TerrenosController::class, 'show'])
        ->whereNumber('id')
        ->name('api.terrenos.show');
    Route::post('/terrenos/{id}/fotos', [TerrenosController::class, 'subirFoto'])
        ->whereNumber('id')
        ->name('api.terrenos.fotos.store');
    Route::delete('/terrenos/{id}/fotos/{foto}', [TerrenosController::class, 'borrarFoto'])
        ->whereNumber(['id', 'foto'])
        ->name('api.terrenos.fotos.destroy');
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
    Route::post('/solicitudes/{id}/precio', [SolicitudesController::class, 'precio'])
        ->whereNumber('id')
        ->name('api.solicitudes.precio');
    Route::post('/solicitudes/{id}/renovar', [SolicitudesController::class, 'renovar'])
        ->whereNumber('id')
        ->name('api.solicitudes.renovar');

    // Calificaciones (ADR 0031 y 0039): el cliente califica al proveedor y al piloto
    // de un servicio completado; el proveedor califica al cliente en
    // privado, ve lo que recibe y reporta lo abusivo. Requieren red; el alcance lo
    // hacen los casos de uso (un id ajeno es 404).
    Route::get('/solicitudes/{id}/calificaciones', [CalificacionesDelClienteController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes.calificaciones.show');
    Route::post('/solicitudes/{id}/calificaciones', [CalificacionesDelClienteController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.calificaciones.store');
    Route::get('/solicitudes/{id}/calificar-cliente', [CalificacionesDelProveedorController::class, 'show'])
        ->whereNumber('id')
        ->name('api.solicitudes.calificar-cliente.show');
    Route::post('/solicitudes/{id}/calificar-cliente', [CalificacionesDelProveedorController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.calificar-cliente.store');
    Route::get('/calificaciones/recibidas', [CalificacionesDelProveedorController::class, 'recibidas'])->name('api.calificaciones.recibidas');
    Route::post('/calificaciones/{id}/reportar', [CalificacionesDelProveedorController::class, 'reportar'])
        ->whereNumber('id')
        ->name('api.calificaciones.reportar');

    // Disputas (ADR 0033 y 0039): el cliente o el proveedor abren una disputa por
    // servicio, la argumentan, la retiran o apelan la resolución. Ambas apps comparten
    // la superficie; el lado lo decide el permiso del rol del token. Requieren red; el
    // alcance lo hacen los casos de uso (una disputa ajena es 404).
    Route::post('/solicitudes/{id}/disputas', [DisputasController::class, 'store'])
        ->whereNumber('id')
        ->name('api.solicitudes.disputas.store');
    Route::get('/solicitudes/{id}/disputa', [DisputasController::class, 'deLaSolicitud'])
        ->whereNumber('id')
        ->name('api.solicitudes.disputa.show');
    Route::get('/disputas', [DisputasController::class, 'index'])->name('api.disputas.index');
    Route::get('/disputas/{id}', [DisputasController::class, 'show'])
        ->whereNumber('id')
        ->name('api.disputas.show');
    Route::post('/disputas/{id}/mensajes', [DisputasController::class, 'mensaje'])
        ->whereNumber('id')
        ->name('api.disputas.mensajes');
    Route::post('/disputas/{id}/retirar', [DisputasController::class, 'retirar'])
        ->whereNumber('id')
        ->name('api.disputas.retirar');
    Route::post('/disputas/{id}/apelar', [DisputasController::class, 'apelar'])
        ->whereNumber('id')
        ->name('api.disputas.apelar');

    // Avisos in-app de la cuenta (ADR 0025): las apps no tienen campana de panel.
    Route::get('/notificaciones', [NotificacionesController::class, 'index'])->name('api.notificaciones.index');
    Route::post('/notificaciones/{id}/leida', [NotificacionesController::class, 'leer'])
        ->whereNumber('id')
        ->name('api.notificaciones.leida');
});
