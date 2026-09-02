<?php

use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\AlertasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\TrabajosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ValidacionSesionesController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DashboardController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DispositivosController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\OrganizacionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PreferenciasController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolActivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\SesionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\UsuariosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web — panel (AdminLTE/Livewire) y, a futuro, portal del cliente
|--------------------------------------------------------------------------
|
| Este archivo sirve exclusivamente al panel/portal, con sesión (ADR 0008):
| nunca comparte middleware ni guards con routes/api.php (apps de campo).
|
| HU-02 (ADR 0004, extensión 27/8/2026): login por username + password contra
| el guard `interno`, con selección/cambio de rol activo. Las pantallas Blade
| que consumen estos endpoints (login, selector de rol, layout del panel) son
| responsabilidad de otro agente (`frontend`/`design-ui`) — acá solo vive la
| lógica y las rutas que HU-02 necesita del lado de backend.
|
*/

Route::get('/', function () {
    return redirect()->route(auth('interno')->check() ? 'panel.dashboard' : 'login.form');
});

Route::get('/login', function () {
    return view('seguridad::pages.login');
})->name('login.form');

Route::post('/login', [SesionController::class, 'store'])->name('login');

Route::middleware('auth:interno')->group(function () {
    Route::post('/logout', [SesionController::class, 'destroy'])->name('logout');

    // Cambio de rol activo sin volver a loguearse (ADR 0004, extensión
    // 27/8/2026, punto 4). Deliberadamente SIN el middleware
    // `ResolverRolActivo`: esta es la ruta de escape para fijar el rol activo
    // la primera vez (selector tras el login con 2+ roles) — si llevara ese
    // middleware, un usuario sin rol activo resuelto nunca podría llegar acá
    // para resolverlo.
    Route::post('/panel/rol-activo', [RolActivoController::class, 'update'])
        ->name('panel.rol-activo.actualizar');

    // Selector de rol (GET): misma vía de escape que la ruta de arriba,
    // deliberadamente sin `rol.activo` — ver RolActivoController::create().
    Route::get('/panel/seleccionar-rol', [RolActivoController::class, 'create'])
        ->name('panel.rol-activo.selector');

    // Persistencia del tema claro/oscuro (quinta vuelta). Sin `rol.activo`:
    // el tema es del usuario, no del rol — y el toggle también vive en la
    // pantalla de selección de rol, que corre sin rol activo resuelto.
    Route::post('/panel/preferencias/tema', [PreferenciasController::class, 'actualizarTema'])
        ->name('panel.preferencias.tema');

    Route::middleware('rol.activo')->group(function () {
        Route::get('/panel/dashboard', [DashboardController::class, 'index'])
            ->name('panel.dashboard');

        // Permiso `seguridad.usuario.ver` verificado dentro del controlador
        // (contra el ROL ACTIVO, no la unión) — no hay middleware de permiso
        // genérico todavía, así que se resuelve ahí (ver UsuariosController).
        Route::get('/panel/usuarios', [UsuariosController::class, 'index'])
            ->name('panel.usuarios.index');

        // Revocación de sesiones de la app de campo (HU-03). Los permisos
        // `seguridad.dispositivo.ver`/`.revocar` se verifican DENTRO del
        // controlador contra el ROL ACTIVO, igual que en usuarios: no hay
        // middleware de permiso genérico todavía.
        Route::get('/panel/dispositivos', [DispositivosController::class, 'index'])
            ->name('panel.dispositivos.index');

        Route::delete('/panel/dispositivos/{dispositivo}', [DispositivosController::class, 'destroy'])
            ->name('panel.dispositivos.revocar');

        // Mockup visual de "Registro de la compañía" — GET/solo-lectura, sin
        // persistencia real, para demostración de visión multi-tenant futura.
        Route::get('/panel/organizacion', [OrganizacionController::class, 'index'])
            ->name('panel.organizacion.index');

        // HU-20: autorizar versiones del APK. Un único permiso
        // (`distribucion.version.autorizar`) gatea listar, subir y autorizar
        // — verificado DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que usuarios y dispositivos.
        Route::get('/panel/versiones-apk', [VersionesApkController::class, 'index'])
            ->name('panel.versiones-apk.index');

        Route::post('/panel/versiones-apk', [VersionesApkController::class, 'store'])
            ->name('panel.versiones-apk.subir');

        Route::post('/panel/versiones-apk/{version}/autorizar', [VersionesApkController::class, 'autorizar'])
            ->name('panel.versiones-apk.autorizar');

        // HU-05 (tarea 13; extendida en HU-15, tarea 15): tablero de
        // trabajos/sesiones con filtros y detalle. Permiso
        // `operaciones.trabajo.ver` verificado DENTRO del controlador
        // contra el ROL ACTIVO, mismo criterio que las rutas de arriba.
        Route::get('/panel/trabajos', [TrabajosController::class, 'index'])
            ->name('panel.trabajos.index');

        Route::get('/panel/trabajos/{trabajo}', [TrabajosController::class, 'show'])
            ->name('panel.trabajos.show');

        // HU-14 (tarea 14): cola de validación de sesiones cerradas.
        // Permiso `operaciones.sesion.validar` verificado DENTRO del
        // controlador contra el ROL ACTIVO, mismo criterio que las rutas de
        // arriba; la policy validador≠piloto (invariante 4) se reverifica
        // del lado del servidor en cada acción, no solo en el listado.
        Route::get('/panel/sesiones/validacion', [ValidacionSesionesController::class, 'index'])
            ->name('panel.sesiones.validacion.index');

        Route::post('/panel/sesiones/{sesion}/validar', [ValidacionSesionesController::class, 'validar'])
            ->name('panel.sesiones.validacion.validar');

        Route::post('/panel/sesiones/{sesion}/rechazar', [ValidacionSesionesController::class, 'rechazar'])
            ->name('panel.sesiones.validacion.rechazar');

        // HU-19 (tarea 26): bandeja de alertas por excepción. Permiso
        // `operaciones.alerta.ver` gatea la pantalla,
        // `operaciones.alerta.atender` gatea la acción — ambos verificados
        // DENTRO del controlador contra el ROL ACTIVO, mismo criterio que
        // las rutas de arriba.
        Route::get('/panel/alertas', [AlertasController::class, 'index'])
            ->name('panel.alertas.index');

        Route::post('/panel/alertas/{alerta}/atender', [AlertasController::class, 'atender'])
            ->name('panel.alertas.atender');
    });
});
