<?php

use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\OrdenAplicacionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\DispositivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Api\SesionCampoController;
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
});
