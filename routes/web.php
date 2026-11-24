<?php

use App\Dominios\Asignacion\Infraestructura\Http\Controllers\Web\AsignacionesController;
use App\Dominios\Asignacion\Infraestructura\Http\Controllers\Web\SancionesController;
use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Web\DronesController as DronesFlotaController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Web\EquiposController as EquiposFlotaController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\BateriasController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\FichasDronController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web\NotificacionesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\AlertasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\DronesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\PausasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ReportesTecnicosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\TrabajosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ValidacionSesionesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\BasesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\CuadrillasController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\PersonasController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\AprobacionController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\AuxiliaresController as AuxiliaresPanelController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\DocumentosController as DocumentosPanelController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\PilotosController as PilotosPanelController;
use App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web\ProveedoresController;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\BitacoraController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\BusquedaController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\ConfiguracionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DashboardController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DispositivosController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\OrganizacionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PerfilController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PerfilPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PreferenciasController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\PreferenciasPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RecuperarContrasenaController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RestablecerContrasenaController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RestablecerContrasenaPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolActivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolesController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\SesionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\SesionPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\UsuariosController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\VistaComoController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web\SolicitudesController as SolicitudesPanelController;
use Illuminate\Http\Request;
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

Route::get('/', function (AutorizacionPanelWeb $autorizacion, Request $request) {
    if (! auth('interno')->check()) {
        // Un solo login para todos (16/9/2026): quien ya entró como cliente
        // vuelve a su portal, no a un formulario de ingreso.
        return auth('cliente')->check()
            ? redirect()->route('portal.perfil.edit')
            : redirect()->route('login.form');
    }

    // Primer ítem visible del menú del rol activo (tarea 62, fuga 2): un
    // usuario ya logueado que revisita "/" (favorito, refresh) no puede
    // rebotar a un `panel.dashboard` fijo si su rol activo no tiene ese
    // permiso — mismo destino que ya resuelven login y cambio de rol.
    return redirect()->to($autorizacion->primerDestinoVisible($request));
});

// Única puerta de entrada del sistema (16/9/2026): personal de Agrocom y
// clientes ingresan por acá. El portal del cliente (HU-41) conserva su guard
// `cliente` y sus pantallas, pero ya no tiene URL de login propia —
// `SesionController::store()` prueba los dos guards.
Route::get('/login', function () {
    return view('seguridad::pages.login');
})->name('login.form');

Route::post('/login', [SesionController::class, 'store'])->name('login');

// Recuperación de contraseña por correo (tarea 66; ADR 0004, ampliación
// 9/9/2026) — públicas, sin guard: quien las usa todavía no tiene sesión.
// `POST /recuperar` sirve a los dos guards (un solo login, un solo "recuperar
// acceso"); restablecer sí tiene una URL por guard, porque el enlace del
// correo ya sabe de qué tipo de cuenta es.
// `throttle:6,1` por IP, además del throttle por email que ya aplica el
// broker (`config('auth.passwords.*.throttle')`, 60 s).
Route::post('/recuperar', [RecuperarContrasenaController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('recuperar.store');

Route::get('/restablecer/{token}', [RestablecerContrasenaController::class, 'create'])
    ->name('restablecer.form');

Route::post('/restablecer', [RestablecerContrasenaController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('restablecer.store');

Route::get('/portal/restablecer/{token}', [RestablecerContrasenaPortalController::class, 'create'])
    ->name('portal.restablecer.form');

Route::post('/portal/restablecer', [RestablecerContrasenaPortalController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('portal.restablecer.store');

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

    // Salida de la vista "como otro usuario" (tarea 140). Sin `rol.activo` por
    // la misma razón que el cambio de rol: mientras hay una vista abierta, la
    // sesión guarda el rol activo de la cuenta OBSERVADA, que no es un rol del
    // administrador — con ese middleware la salida rebotaría al selector. Es
    // además la única escritura que `AplicarVistaComo` deja pasar en ese modo,
    // y la única ruta donde ese middleware NO sustituye el guard: la bitácora
    // tiene que firmar el regreso con el administrador real.
    Route::post('/vista-como/salir', [VistaComoController::class, 'salir'])
        ->name('vista-como.salir');

    // Selector de rol (GET): misma vía de escape que la ruta de arriba,
    // deliberadamente sin `rol.activo` — ver RolActivoController::create().
    Route::get('/panel/seleccionar-rol', [RolActivoController::class, 'create'])
        ->name('panel.rol-activo.selector');

    // Persistencia del tema claro/oscuro (quinta vuelta). Sin `rol.activo`:
    // el tema es del usuario, no del rol — y el toggle también vive en la
    // pantalla de selección de rol, que corre sin rol activo resuelto.
    Route::post('/panel/preferencias/tema', [PreferenciasController::class, 'actualizarTema'])
        ->name('panel.preferencias.tema');

    // Zona horaria IANA del usuario (tarea 63), mismo criterio sin
    // `rol.activo` que el tema de arriba: cambia desde el mismo lugar del
    // topbar y también en la pantalla de selección de rol.
    Route::post('/panel/preferencias/zona-horaria', [PreferenciasController::class, 'actualizarZonaHoraria'])
        ->name('panel.preferencias.zona-horaria');

    Route::middleware(['rol.activo', 'origen.navegacion'])->group(function () {
        Route::get('/panel/dashboard', [DashboardController::class, 'index'])
            ->name('panel.dashboard');

        // Perfil propio (tarea 66): cualquier rol activo, sin permiso de
        // grano fino — el sujeto es siempre quien está logueado, nunca un
        // `{usuario}` de ruta (eso es UsuariosController, cuentas AJENAS).
        Route::get('/panel/perfil', [PerfilController::class, 'edit'])
            ->name('panel.perfil.edit');

        Route::put('/panel/perfil', [PerfilController::class, 'update'])
            ->name('panel.perfil.update');

        // HU-45 (tarea 39): ABM de usuarios internos con sus roles. Permisos
        // `seguridad.usuario.*` verificados DENTRO del controlador (contra
        // el ROL ACTIVO, no la unión) — no hay middleware de permiso
        // genérico todavía, así que se resuelve ahí (ver UsuariosController).
        Route::get('/panel/usuarios', [UsuariosController::class, 'index'])
            ->name('panel.usuarios.index');

        Route::get('/panel/usuarios/crear', [UsuariosController::class, 'create'])
            ->name('panel.usuarios.create');

        Route::post('/panel/usuarios', [UsuariosController::class, 'store'])
            ->name('panel.usuarios.store');

        Route::get('/panel/usuarios/{usuario}/editar', [UsuariosController::class, 'edit'])
            ->name('panel.usuarios.edit');

        Route::put('/panel/usuarios/{usuario}', [UsuariosController::class, 'update'])
            ->name('panel.usuarios.update');

        Route::delete('/panel/usuarios/{usuario}', [UsuariosController::class, 'destroy'])
            ->name('panel.usuarios.destroy');

        // Toggle de `sec_user.state` (permiso `seguridad.usuario.bloquear`):
        // bloquear NO es una baja, sigue vivo — ver AlternarBloqueoUsuario.
        Route::post('/panel/usuarios/{usuario}/bloqueo', [UsuariosController::class, 'alternarBloqueo'])
            ->name('panel.usuarios.bloqueo');

        // Tarea 140: mirar el panel o el portal COMO esta cuenta, en solo
        // lectura. Permiso propio `seguridad.usuario.ver_como` (solo
        // `admin_plataforma`), verificado dentro del controlador contra el ROL
        // ACTIVO. No cambia nada de la cuenta observada: abre una bandera de
        // sesión y una fila de bitácora — ver IniciarVistaComo.
        Route::post('/panel/usuarios/{usuario}/ver-como', [VistaComoController::class, 'iniciar'])
            ->name('panel.usuarios.ver-como');

        // Administración del catálogo de roles y de la matriz rol↔permiso.
        // Es lo último del modelo `sec_*` que solo existía como seeder: los
        // cinco permisos `seguridad.rol.*` se siembran únicamente para
        // `dueno`, porque quien edita la matriz puede concederse cualquier
        // permiso del sistema. Verificados DENTRO del controlador contra el
        // ROL ACTIVO, igual que usuarios y dispositivos.
        Route::get('/panel/roles', [RolesController::class, 'index'])
            ->name('panel.roles.index');

        Route::get('/panel/roles/crear', [RolesController::class, 'create'])
            ->name('panel.roles.create');

        Route::post('/panel/roles', [RolesController::class, 'store'])
            ->name('panel.roles.store');

        Route::get('/panel/roles/{rol}/editar', [RolesController::class, 'edit'])
            ->name('panel.roles.edit');

        Route::put('/panel/roles/{rol}', [RolesController::class, 'update'])
            ->name('panel.roles.update');

        Route::delete('/panel/roles/{rol}', [RolesController::class, 'destroy'])
            ->name('panel.roles.destroy');

        // Tarea 63 (invariante 9 de CLAUDE.md): pantalla de bitácora de
        // auditoría, solo lectura — sin POST/PUT/DELETE, es un libro de
        // solo-inserción que escribe únicamente `BitacoraObserver` (ADR
        // 0007). Permiso `seguridad.bitacora.ver` verificado DENTRO del
        // controlador contra el ROL ACTIVO, mismo criterio que el resto.
        Route::get('/panel/bitacora', [BitacoraController::class, 'index'])
            ->name('panel.bitacora.index');

        // Buscador global del header (9/9/2026). SIN permiso propio: buscar no
        // es una capacidad que se conceda — lo que se concede es ver cada
        // cosa, y eso lo decide `BuscarEnElPanel` bloque por bloque contra el
        // rol activo. Un rol sin `comercial.cliente.ver` escribe en el
        // buscador y no le aparece ningún cliente.
        Route::get('/panel/buscar', [BusquedaController::class, 'index'])
            ->name('panel.buscar');

        // Campana del header (tarea 141, ADR 0025): los avisos del motor de
        // notificaciones. SIN permiso propio, mismo criterio que el perfil: el
        // sujeto es siempre la cuenta autenticada, nunca un id de la petición
        // — `abrir` busca el aviso ENTRE LOS DE ESA CUENTA (404 si es de otra)
        // y resuelve el destino contra el rol activo.
        Route::get('/panel/notificaciones/{notificacion}/abrir', [NotificacionesController::class, 'abrir'])
            // Hasta 18 dígitos: un id más largo no cabe en un bigint ni en el `int` del controlador y daría 500.
            ->where('notificacion', '[0-9]{1,18}')
            ->name('panel.notificaciones.abrir');

        // Las alertas técnicas de Operaciones también se abren desde la campana: dejan leída
        // la alerta para esta cuenta (no la atienden) y llevan a su pantalla.
        Route::get('/panel/notificaciones/alertas/{alerta}/abrir', [NotificacionesController::class, 'abrirAlerta'])
            ->where('alerta', '[0-9]{1,18}')
            ->name('panel.notificaciones.abrir-alerta');

        Route::post('/panel/notificaciones/marcar-todas', [NotificacionesController::class, 'marcarTodas'])
            ->name('panel.notificaciones.marcar-todas');

        Route::post('/panel/notificaciones/limpiar', [NotificacionesController::class, 'limpiar'])
            ->name('panel.notificaciones.limpiar');

        // La matriz vive en su propia URL y no como pestaña del formulario:
        // son dos operaciones con permisos distintos (`editar` cambia el
        // nombre; `asignar_permiso` reparte poder) y mezclarlas en un submit
        // obligaría a exigir los dos para cualquiera de las dos.
        Route::get('/panel/roles/{rol}/permisos', [RolesController::class, 'editarPermisos'])
            ->name('panel.roles.permisos.edit');

        Route::put('/panel/roles/{rol}/permisos', [RolesController::class, 'actualizarPermisos'])
            ->name('panel.roles.permisos.update');

        // Revocación de sesiones de la app de campo (HU-03). Los permisos
        // `seguridad.dispositivo.ver`/`.revocar` se verifican DENTRO del
        // controlador contra el ROL ACTIVO, igual que en usuarios: no hay
        // middleware de permiso genérico todavía.
        Route::get('/panel/dispositivos', [DispositivosController::class, 'index'])
            ->name('panel.dispositivos.index');

        Route::delete('/panel/dispositivos/{dispositivo}', [DispositivosController::class, 'destroy'])
            ->name('panel.dispositivos.revocar');

        // "Registro de la compañía": el plan de suscripción y multi-sucursal
        // siguen siendo mockup sin persistencia (visión multi-tenant futura,
        // sin ADR todavía). "Datos de empresa"/"Datos de contacto" y
        // "Facturación" (tarea 78, HU-55) SÍ persisten, vía los POST de abajo.
        Route::get('/panel/organizacion', [OrganizacionController::class, 'index'])
            ->name('panel.organizacion.index');

        Route::post('/panel/organizacion/empresa', [OrganizacionController::class, 'actualizarEmpresa'])
            ->name('panel.organizacion.empresa.actualizar');

        Route::post('/panel/organizacion/facturacion', [OrganizacionController::class, 'actualizarFacturacion'])
            ->name('panel.organizacion.facturacion.actualizar');

        // Tarea 78 (HU-55): configuración del sistema (llaves y tokens),
        // exclusiva del dueño — separada de "Organización" de arriba (datos
        // de la empresa). `{grupo}` restringido a los tres sectores del
        // catálogo (`config/configuracion.php`): fuera de esa lista, 404.
        Route::get('/panel/configuracion', [ConfiguracionController::class, 'index'])
            ->name('panel.configuracion.index');

        Route::post('/panel/configuracion/{grupo}', [ConfiguracionController::class, 'actualizar'])
            ->where('grupo', 'mapas|correo|integraciones')
            ->name('panel.configuracion.actualizar');

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

        // Trabajos (ADR 0037): los que los equipos abrieron sobre solicitudes de
        // servicio. El listado es `panel.trabajos.index`; el detalle de UNO —
        // sesiones, acta, reporte, evidencias y eliminar mientras no esté
        // validado (HU-93)— cuelga del prefijo `detalle/`. Los trabajos nacen
        // en la app de campo: el panel no los crea ni los edita.
        Route::get('/panel/trabajos', [TrabajosController::class, 'index'])
            ->name('panel.trabajos.index');

        Route::get('/panel/trabajos/detalle/{trabajo}', [TrabajosController::class, 'show'])
            ->name('panel.trabajos.detalle');

        Route::delete('/panel/trabajos/detalle/{trabajo}', [TrabajosController::class, 'destroy'])
            ->name('panel.trabajos.detalle-eliminar');

        // HU-17 (tarea 24): descarga del PDF del acta desde el panel — solo
        // lectura, mismo permiso `operaciones.trabajo.ver` que el detalle
        // (generar/firmar el acta es de `agrocom-field`, no del panel).
        Route::get('/panel/trabajos/detalle/{trabajo}/acta/pdf', [TrabajosController::class, 'actaPdf'])
            ->name('panel.trabajos.acta-pdf');

        // HU-18 (tarea 25): descarga del reporte técnico desde el panel —
        // solo lectura, permiso propio `operaciones.reporte.ver` (espec §3
        // línea 89: jefe de campo/encargado/dueño, no piloto/auxiliar; ver
        // runs/25.md). El reporte se genera solo al firmar el acta
        // (`GenerarReporteTecnico`); esta ruta nunca lo genera.
        Route::get('/panel/trabajos/detalle/{trabajo}/reporte/pdf', [TrabajosController::class, 'reporteTecnicoPdf'])
            ->name('panel.trabajos.reporte-pdf');

        // HU-42 (tarea 56): galería de evidencias de un trabajo — solo
        // lectura, mismo permiso `operaciones.trabajo.ver` que el detalle.
        // El streaming del archivo real vive en una ruta propia porque no
        // cuelga de un `{trabajo}` (la evidencia puede venir de una sesión).
        Route::get('/panel/trabajos/detalle/{trabajo}/evidencias', [TrabajosController::class, 'evidencias'])
            ->name('panel.trabajos.evidencias');

        Route::get('/panel/evidencias/{evidencia}/archivo', [TrabajosController::class, 'evidenciaArchivo'])
            ->name('panel.evidencias.archivo');

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

        // HU-44 (tarea 58): pausas de sesión con causa atribuible (DS-01).
        // Permisos `operaciones.pausa.ver`/`.registrar` verificados DENTRO
        // del controlador contra el ROL ACTIVO, mismo criterio que las
        // rutas de arriba.
        Route::get('/panel/pausas', [PausasController::class, 'index'])
            ->name('panel.pausas.index');

        Route::get('/panel/pausas/crear', [PausasController::class, 'create'])
            ->name('panel.pausas.create');

        Route::post('/panel/pausas', [PausasController::class, 'store'])
            ->name('panel.pausas.store');

        // HU-19 (tarea 26): bandeja de alertas por excepción. Permiso
        // `operaciones.alerta.ver` gatea la pantalla,
        // `operaciones.alerta.atender` gatea la acción — ambos verificados
        // DENTRO del controlador contra el ROL ACTIVO, mismo criterio que
        // las rutas de arriba.
        Route::get('/panel/alertas', [AlertasController::class, 'index'])
            ->name('panel.alertas.index');

        Route::post('/panel/alertas/{alerta}/atender', [AlertasController::class, 'atender'])
            ->name('panel.alertas.atender');

        // HU-27 (tarea 36): administración de la flota de drones con su
        // modelo y capacidad de carga. Sin sub-entidad (a diferencia de
        // `clientes`/`campos`): un dron no tiene contactos ni lotes. Cuatro
        // permisos de grano fino
        // (`operaciones.dron.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `clientes`/`campos` arriba.
        Route::get('/panel/drones', [DronesController::class, 'index'])
            ->name('panel.drones.index');

        Route::get('/panel/drones/crear', [DronesController::class, 'create'])
            ->name('panel.drones.create');

        Route::post('/panel/drones', [DronesController::class, 'store'])
            ->name('panel.drones.store');

        Route::get('/panel/drones/{dron}/editar', [DronesController::class, 'edit'])
            ->name('panel.drones.edit');

        Route::put('/panel/drones/{dron}', [DronesController::class, 'update'])
            ->name('panel.drones.update');

        Route::delete('/panel/drones/{dron}', [DronesController::class, 'destroy'])
            ->name('panel.drones.destroy');

        // HU-26 (tarea 37): administración de bases y personas operativas,
        // dos ABMs INDEPENDIENTES (una base es catálogo simple; una persona
        // la referencia por `base_id`, FK nullable, pero cada una tiene su
        // propia pantalla). Sin sub-entidad en ninguna de las dos. Cuatro
        // permisos de grano fino por recurso
        // (`personal.base.*`/`personal.persona.*`) verificados DENTRO del
        // controlador contra el ROL ACTIVO, mismo criterio que
        // `clientes`/`campos`/`drones` arriba.
        Route::get('/panel/bases', [BasesController::class, 'index'])
            ->name('panel.bases.index');

        Route::get('/panel/bases/crear', [BasesController::class, 'create'])
            ->name('panel.bases.create');

        Route::post('/panel/bases', [BasesController::class, 'store'])
            ->name('panel.bases.store');

        Route::get('/panel/bases/{base}/editar', [BasesController::class, 'edit'])
            ->name('panel.bases.edit');

        Route::put('/panel/bases/{base}', [BasesController::class, 'update'])
            ->name('panel.bases.update');

        Route::delete('/panel/bases/{base}', [BasesController::class, 'destroy'])
            ->name('panel.bases.destroy');

        Route::get('/panel/personas', [PersonasController::class, 'index'])
            ->name('panel.personas.index');

        Route::get('/panel/personas/crear', [PersonasController::class, 'create'])
            ->name('panel.personas.create');

        Route::post('/panel/personas', [PersonasController::class, 'store'])
            ->name('panel.personas.store');

        Route::get('/panel/personas/{persona}/editar', [PersonasController::class, 'edit'])
            ->name('panel.personas.edit');

        Route::put('/panel/personas/{persona}', [PersonasController::class, 'update'])
            ->name('panel.personas.update');

        Route::delete('/panel/personas/{persona}', [PersonasController::class, 'destroy'])
            ->name('panel.personas.destroy');

        // HU-58 (tarea 81): "¿qué hizo esta persona esta campaña?" — ficha
        // de desempeño por sesión (nunca por equipo de trabajo, ADR 0015
        // punto 3). Permiso propio `personal.persona.desempenio`, más fino
        // que `.ver`: es información sensible que no ve cualquiera con
        // acceso al listado de personas.
        Route::get('/panel/personas/{persona}/desempeno', [PersonasController::class, 'desempenio'])
            ->name('panel.personas.desempenio');

        // HU-39 (tarea 51): catálogo de baterías con sus ciclos acumulados y
        // estado, para retirarlas antes de que fallen en vuelo. ABM de
        // `Mantenimiento`. Cuatro permisos de grano fino
        // (`mantenimiento.bateria.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que el resto del panel.
        Route::get('/panel/baterias', [BateriasController::class, 'index'])
            ->name('panel.baterias.index');

        Route::get('/panel/baterias/crear', [BateriasController::class, 'create'])
            ->name('panel.baterias.create');

        Route::post('/panel/baterias', [BateriasController::class, 'store'])
            ->name('panel.baterias.store');

        Route::get('/panel/baterias/{bateria}/editar', [BateriasController::class, 'edit'])
            ->name('panel.baterias.edit');

        Route::put('/panel/baterias/{bateria}', [BateriasController::class, 'update'])
            ->name('panel.baterias.update');

        Route::delete('/panel/baterias/{bateria}', [BateriasController::class, 'destroy'])
            ->name('panel.baterias.destroy');

        // Solicitudes de servicio (ADR 0027): el administrador las consulta y, con
        // el permiso de cancelar, las cancela con motivo. El cliente las crea
        // desde la app; el panel no crea ni edita solicitudes.
        Route::get('/panel/solicitudes', [SolicitudesPanelController::class, 'index'])
            ->name('panel.solicitudes.index');

        Route::get('/panel/solicitudes/{solicitud}', [SolicitudesPanelController::class, 'show'])
            ->whereNumber('solicitud')
            ->name('panel.solicitudes.show');

        Route::post('/panel/solicitudes/{solicitud}/cancelar', [SolicitudesPanelController::class, 'cancelar'])
            ->whereNumber('solicitud')
            ->name('panel.solicitudes.cancelar');

        // Asignación (ADR 0030 y 0038): la cola de solicitudes que esperan
        // proveedor, las rondas y ofertas de cada una, la asignación a mano con
        // motivo y las sanciones de los proveedores. El motor genera las ofertas;
        // el panel las consulta y decide donde el motor no pudo.
        Route::get('/panel/asignaciones', [AsignacionesController::class, 'index'])
            ->name('panel.asignaciones.index');

        Route::get('/panel/asignaciones/{solicitud}', [AsignacionesController::class, 'show'])
            ->whereNumber('solicitud')
            ->name('panel.asignaciones.show');

        Route::post('/panel/asignaciones/{solicitud}/forzar', [AsignacionesController::class, 'forzar'])
            ->whereNumber('solicitud')
            ->name('panel.asignaciones.forzar');

        Route::get('/panel/sanciones', [SancionesController::class, 'index'])
            ->name('panel.sanciones.index');

        Route::post('/panel/sanciones/{sancion}/levantar', [SancionesController::class, 'levantar'])
            ->whereNumber('sancion')
            ->name('panel.sanciones.levantar');

        // Marketplace (ADR 0028, 0029, 0032): cola de aprobación de proveedores,
        // pilotos, auxiliares, drones y documentos. Los permisos son de grano
        // fino por entidad y por acción (`proveedores.proveedor.ver`/`.aprobar`/
        // `.suspender`/`.dar_baja`, y sus pares de piloto, auxiliar y
        // `flota.dron.*`), verificados DENTRO de cada controlador contra el ROL
        // ACTIVO. La ficha de cada uno ofrece las acciones que el rol puede hacer.
        Route::get('/panel/proveedores', [ProveedoresController::class, 'index'])
            ->name('panel.proveedores.index');

        Route::get('/panel/proveedores/{proveedor}', [ProveedoresController::class, 'show'])
            ->whereNumber('proveedor')
            ->name('panel.proveedores.show');

        Route::post('/panel/proveedores/{id}/resolver', [AprobacionController::class, 'proveedor'])
            ->whereNumber('id')
            ->name('panel.proveedores.resolver');

        Route::get('/panel/pilotos', [PilotosPanelController::class, 'index'])
            ->name('panel.pilotos.index');

        Route::get('/panel/pilotos/{piloto}', [PilotosPanelController::class, 'show'])
            ->whereNumber('piloto')
            ->name('panel.pilotos.show');

        Route::post('/panel/pilotos/{id}/resolver', [AprobacionController::class, 'piloto'])
            ->whereNumber('id')
            ->name('panel.pilotos.resolver');

        Route::get('/panel/auxiliares', [AuxiliaresPanelController::class, 'index'])
            ->name('panel.auxiliares.index');

        Route::get('/panel/auxiliares/{auxiliar}', [AuxiliaresPanelController::class, 'show'])
            ->whereNumber('auxiliar')
            ->name('panel.auxiliares.show');

        Route::post('/panel/auxiliares/{id}/resolver', [AprobacionController::class, 'auxiliar'])
            ->whereNumber('id')
            ->name('panel.auxiliares.resolver');

        // `drones-flota` y `equipos-flota`: el nombre corto ya lo usan el catálogo
        // operativo de drones (`panel.drones.*`) y las cuadrillas.
        Route::get('/panel/drones-flota', [DronesFlotaController::class, 'index'])
            ->name('panel.drones-flota.index');

        Route::get('/panel/drones-flota/{dron}', [DronesFlotaController::class, 'show'])
            ->whereNumber('dron')
            ->name('panel.drones-flota.show');

        Route::post('/panel/drones-flota/{dron}/resolver', [DronesFlotaController::class, 'resolver'])
            ->whereNumber('dron')
            ->name('panel.drones-flota.resolver');

        Route::get('/panel/equipos-flota', [EquiposFlotaController::class, 'index'])
            ->name('panel.equipos-flota.index');

        Route::get('/panel/documentos', [DocumentosPanelController::class, 'index'])
            ->name('panel.documentos.index');

        Route::get('/panel/documentos/{documento}/archivo', [DocumentosPanelController::class, 'archivo'])
            ->whereNumber('documento')
            ->name('panel.documentos.archivo');

        Route::post('/panel/documentos/{documento}/revisar', [DocumentosPanelController::class, 'revisar'])
            ->whereNumber('documento')
            ->name('panel.documentos.revisar');

        // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — el piloto
        // y su auxiliar, con el equipamiento asignado, cada uno con su
        // propia vigencia. Cuatro permisos de grano fino
        // (`personal.equipo_trabajo.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO. La ficha
        // (`show`) y las rutas de integrantes/recursos exigen `.editar` para
        // mutar — asignar o finalizar una vigencia es mantener el equipo,
        // no un permiso aparte.
        Route::get('/panel/cuadrillas', [CuadrillasController::class, 'index'])
            ->name('panel.cuadrillas.index');

        Route::get('/panel/cuadrillas/crear', [CuadrillasController::class, 'create'])
            ->name('panel.cuadrillas.create');

        Route::post('/panel/cuadrillas', [CuadrillasController::class, 'store'])
            ->name('panel.cuadrillas.store');

        Route::get('/panel/cuadrillas/{equipoTrabajo}', [CuadrillasController::class, 'show'])
            ->name('panel.cuadrillas.show');

        Route::get('/panel/cuadrillas/{equipoTrabajo}/editar', [CuadrillasController::class, 'edit'])
            ->name('panel.cuadrillas.edit');

        Route::put('/panel/cuadrillas/{equipoTrabajo}', [CuadrillasController::class, 'update'])
            ->name('panel.cuadrillas.update');

        // Tarea "cuadrillas-estadias" (19/9/2026): máquina de estados
        // `activo ⇄ inactivo` (`step-arrow` en la ficha de edición) — pasa por
        // `CambiarEstadoEquipoTrabajo`, nunca un `estado = ...` suelto.
        Route::post('/panel/cuadrillas/{equipoTrabajo}/estado', [CuadrillasController::class, 'cambiarEstado'])
            ->name('panel.cuadrillas.cambiar-estado');

        Route::delete('/panel/cuadrillas/{equipoTrabajo}', [CuadrillasController::class, 'destroy'])
            ->name('panel.cuadrillas.destroy');

        Route::post('/panel/cuadrillas/{equipoTrabajo}/integrantes', [CuadrillasController::class, 'asignarIntegrante'])
            ->name('panel.cuadrillas.integrantes.store');

        Route::delete('/panel/cuadrillas/{equipoTrabajo}/integrantes/{integrante}', [CuadrillasController::class, 'desasignarIntegrante'])
            ->name('panel.cuadrillas.integrantes.destroy');

        Route::post('/panel/cuadrillas/{equipoTrabajo}/recursos', [CuadrillasController::class, 'asignarRecurso'])
            ->name('panel.cuadrillas.recursos.store');

        Route::delete('/panel/cuadrillas/{equipoTrabajo}/recursos/{recurso}', [CuadrillasController::class, 'desasignarRecurso'])
            ->name('panel.cuadrillas.recursos.destroy');

        // Tarea "cuadrillas-estadias" (19/9/2026): accesorios (machete,
        // palas, linternas…) que lleva la cuadrilla, con cantidad — sin
        // vigencia propia, a diferencia de integrantes/recursos.
        Route::post('/panel/cuadrillas/{equipoTrabajo}/accesorios', [CuadrillasController::class, 'agregarAccesorio'])
            ->name('panel.cuadrillas.accesorios.store');

        Route::delete('/panel/cuadrillas/{equipoTrabajo}/accesorios/{accesorio}', [CuadrillasController::class, 'quitarAccesorio'])
            ->name('panel.cuadrillas.accesorios.destroy');

        // HU-82 (tarea 97): ficha de inventario del dron — serie, chasis,
        // versión de software, región, serie del control y accesorios. ABM
        // sin máquina de estados, mismo molde que `baterias` arriba.
        // Cuatro permisos de grano fino
        // (`mantenimiento.ficha_dron.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que el resto del panel. `identificador_dron` correlaciona
        // por TEXTO contra `ope_drones.identificador`, sin FK real (ver
        // docblock de la migración `man_drones`).
        Route::get('/panel/fichas-dron', [FichasDronController::class, 'index'])
            ->name('panel.fichas-dron.index');

        Route::get('/panel/fichas-dron/crear', [FichasDronController::class, 'create'])
            ->name('panel.fichas-dron.create');

        Route::post('/panel/fichas-dron', [FichasDronController::class, 'store'])
            ->name('panel.fichas-dron.store');

        Route::get('/panel/fichas-dron/{fichaDron}/editar', [FichasDronController::class, 'edit'])
            ->name('panel.fichas-dron.edit');

        Route::put('/panel/fichas-dron/{fichaDron}', [FichasDronController::class, 'update'])
            ->name('panel.fichas-dron.update');

        Route::delete('/panel/fichas-dron/{fichaDron}', [FichasDronController::class, 'destroy'])
            ->name('panel.fichas-dron.destroy');

        // HU-43 (tarea 57): "como encargado, quiero listar y descargar los
        // reportes técnicos generados, para reenviarlos al agrónomo" — cierra
        // Sprint 12. Reusa el permiso `operaciones.reporte.ver` que ya gatea
        // la descarga individual (`panel.trabajos.reporte-pdf`), misma acción
        // de negocio. Filtrable por cliente y por período de generación.
        Route::get('/panel/reportes/tecnicos', [ReportesTecnicosController::class, 'index'])
            ->name('panel.reportes.tecnicos.index');
    });
});

// HU-41 (tarea 55): portal del cliente — avance, actas firmadas y reportes
// técnicos, SIEMPRE resueltos desde el `contrato_id` de la sesión de portal
// (invariante 5 de CLAUDE.md), nunca desde un id de ruta sin verificar. Sin
// `sec_permission`: el único gate es el guard `cliente` + el contrato
// resuelto por AutorizacionPortalCliente, verificado DENTRO de cada
// controlador (mismo patrón que el panel interno).
Route::middleware('auth:cliente')->group(function () {
    Route::post('/portal/logout', [SesionPortalController::class, 'destroy'])->name('portal.logout');

    Route::post('/portal/preferencias/tema', [PreferenciasPortalController::class, 'actualizarTema'])
        ->name('portal.preferencias.tema');

    // Perfil propio (tarea 66): mismo mecanismo que `/panel/perfil`, para el
    // guard `cliente` — ver PerfilPortalController.
    Route::get('/portal/perfil', [PerfilPortalController::class, 'edit'])
        ->name('portal.perfil.edit');

    Route::put('/portal/perfil', [PerfilPortalController::class, 'update'])
        ->name('portal.perfil.update');

    Route::post('/portal/preferencias/zona-horaria', [PreferenciasPortalController::class, 'actualizarZonaHoraria'])
        ->name('portal.preferencias.zona-horaria');

});
