<?php

use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Api\OrdenAplicacionController;
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
| TODO(HU-03): envolver estas rutas en auth:sanctum (token por dispositivo)
| cuando exista el módulo Identidad. Hasta entonces quedan sin middleware de
| autenticación a propósito — no implementar auth ad hoc acá.
|
*/

// Espec §8: GET /api/ordenes?lote_id=&estado= — órdenes para el piloto.
Route::get('/ordenes', [OrdenAplicacionController::class, 'index'])->name('api.ordenes.index');
