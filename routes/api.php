<?php

use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Api\VersionController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\EvidenciaController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\OrdenAplicacionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\DispositivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\SesionCampoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\CatalogoController;
use App\Dominios\Sincronizacion\Infraestructura\Http\Controllers\Api\SyncController;
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
// oráculo de fuerza bruta. 6 intentos por minuto y por IP.
Route::post('/auth/token', [SesionCampoController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('api.auth.token.emitir');

// HU-20: la app todavía no tiene token cuando consulta esto — es lo primero
// que hace antes de operar. Mismo throttle que el login: sin límite sería
// un endpoint de descubrimiento gratis para golpear el bucket de r2.
Route::get('/version', [VersionController::class, 'show'])
    ->middleware('throttle:6,1')
    ->name('api.version.show');

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
});
