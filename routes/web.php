<?php

use App\Dominios\Campania\Infraestructura\Http\Controllers\Web\CampaniasController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ClientesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ContratosController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\CultivosController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\FacturasController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\GenerarLotesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\LotesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\PropiedadesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\PropiedadMapaController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ReportesComercialesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\SiembraController;
use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\AnticiposController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\CombustibleController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\DevengosController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\GastosController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\PlanillasController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\RendicionesController;
use App\Dominios\Inventario\Infraestructura\Http\Controllers\Web\RepuestosController;
use App\Dominios\Inventario\Infraestructura\Http\Controllers\Web\StockController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\BateriasController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\FichasDronController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\GeneradoresController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\OrdenesMantenimientoController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\PlanesMantenimientoController;
use App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web\VehiculosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\AlertasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\AsignacionEquiposController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\DronesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\EstadiasHaciendaController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\OrdenesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\PausasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ReportesTecnicosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\TrabajosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ValidacionSesionesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\BasesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\EquiposTrabajoController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\PersonasController;
use App\Dominios\Portal\Infraestructura\Http\Controllers\Web\ActasPortalController;
use App\Dominios\Portal\Infraestructura\Http\Controllers\Web\AvancePortalController;
use App\Dominios\Portal\Infraestructura\Http\Controllers\Web\ReportesPortalController;
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
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RecuperarContrasenaPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RestablecerContrasenaController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RestablecerContrasenaPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolActivoController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolesController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\SesionController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\SesionPortalController;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\UsuariosController;
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
        return redirect()->route('login.form');
    }

    // Primer ítem visible del menú del rol activo (tarea 62, fuga 2): un
    // usuario ya logueado que revisita "/" (favorito, refresh) no puede
    // rebotar a un `panel.dashboard` fijo si su rol activo no tiene ese
    // permiso — mismo destino que ya resuelven login y cambio de rol.
    return redirect()->to($autorizacion->primerDestinoVisible($request));
});

Route::get('/login', function () {
    return view('seguridad::pages.login');
})->name('login.form');

Route::post('/login', [SesionController::class, 'store'])->name('login');

// HU-41 (tarea 55): portal del cliente, guard `cliente` (ADR 0002 punto 6,
// ADR 0004). Sin selección de rol: una cuenta de portal no tiene
// `sec_user_role` — ver SesionPortalController.
Route::get('/portal/login', function () {
    return view('seguridad::pages.portal-login');
})->name('portal.login.form');

Route::post('/portal/login', [SesionPortalController::class, 'store'])->name('portal.login');

// Recuperación de contraseña por correo (tarea 66; ADR 0004, ampliación
// 9/9/2026) — públicas, sin guard: quien las usa todavía no tiene sesión.
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

Route::post('/portal/recuperar', [RecuperarContrasenaPortalController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('portal.recuperar.store');

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

    Route::middleware('rol.activo')->group(function () {
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

        // ADR 0015 punto 1 (tarea 69): alta y mantenimiento de campañas. Sin
        // `.destroy`: la baja es una transición de estado hacia `cerrada`, no
        // un soft delete fuera de la máquina de estados (invariante 7).
        // Cuatro permisos de grano fino
        // (`campania.campania.ver`/`.crear`/`.editar`/`.cambiar_estado`)
        // verificados DENTRO del controlador contra el ROL ACTIVO — mismo
        // criterio que `contratos` arriba. `.cambiar_estado` es exclusivo del
        // rol `dueno`: "solo el dueño cierra una campaña".
        Route::get('/panel/campanias', [CampaniasController::class, 'index'])
            ->name('panel.campanias.index');

        Route::get('/panel/campanias/crear', [CampaniasController::class, 'create'])
            ->name('panel.campanias.create');

        Route::post('/panel/campanias', [CampaniasController::class, 'store'])
            ->name('panel.campanias.store');

        Route::get('/panel/campanias/{campania}/editar', [CampaniasController::class, 'edit'])
            ->name('panel.campanias.edit');

        Route::put('/panel/campanias/{campania}', [CampaniasController::class, 'update'])
            ->name('panel.campanias.update');

        Route::post('/panel/campanias/{campania}/estado', [CampaniasController::class, 'cambiarEstado'])
            ->name('panel.campanias.cambiar-estado');

        // HU-05 (tarea 13; extendida en HU-15, tarea 15): tablero de
        // trabajos/sesiones con filtros y detalle. Permiso
        // `operaciones.trabajo.ver` verificado DENTRO del controlador
        // contra el ROL ACTIVO, mismo criterio que las rutas de arriba.
        Route::get('/panel/trabajos', [TrabajosController::class, 'index'])
            ->name('panel.trabajos.index');

        Route::get('/panel/trabajos/{trabajo}', [TrabajosController::class, 'show'])
            ->name('panel.trabajos.show');

        // HU-17 (tarea 24): descarga del PDF del acta desde el panel — solo
        // lectura, mismo permiso `operaciones.trabajo.ver` que el detalle
        // (generar/firmar el acta es de `agrocom-field`, no del panel).
        Route::get('/panel/trabajos/{trabajo}/acta/pdf', [TrabajosController::class, 'actaPdf'])
            ->name('panel.trabajos.acta-pdf');

        // HU-18 (tarea 25): descarga del reporte técnico desde el panel —
        // solo lectura, permiso propio `operaciones.reporte.ver` (espec §3
        // línea 89: jefe de campo/encargado/dueño, no piloto/auxiliar; ver
        // runs/25.md). El reporte se genera solo al firmar el acta
        // (`GenerarReporteTecnico`); esta ruta nunca lo genera.
        Route::get('/panel/trabajos/{trabajo}/reporte/pdf', [TrabajosController::class, 'reporteTecnicoPdf'])
            ->name('panel.trabajos.reporte-pdf');

        // HU-42 (tarea 56): galería de evidencias de un trabajo — solo
        // lectura, mismo permiso `operaciones.trabajo.ver` que el detalle.
        // El streaming del archivo real vive en una ruta propia porque no
        // cuelga de un `{trabajo}` (la evidencia puede venir de una sesión).
        Route::get('/panel/trabajos/{trabajo}/evidencias', [TrabajosController::class, 'evidencias'])
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

        // HU-51 (tarea 74): estadías del equipo en cada hacienda, solo
        // lectura — se cargan desde la app de campo, nunca desde el panel.
        // Permiso `operaciones.estadia.ver` verificado DENTRO del
        // controlador, mismo criterio que las rutas de arriba.
        Route::get('/panel/estadias', [EstadiasHaciendaController::class, 'index'])
            ->name('panel.estadias.index');

        // HU-19 (tarea 26): bandeja de alertas por excepción. Permiso
        // `operaciones.alerta.ver` gatea la pantalla,
        // `operaciones.alerta.atender` gatea la acción — ambos verificados
        // DENTRO del controlador contra el ROL ACTIVO, mismo criterio que
        // las rutas de arriba.
        Route::get('/panel/alertas', [AlertasController::class, 'index'])
            ->name('panel.alertas.index');

        Route::post('/panel/alertas/{alerta}/atender', [AlertasController::class, 'atender'])
            ->name('panel.alertas.atender');

        // HU-22 (tarea 33): alta y mantenimiento de clientes con sus
        // contactos — primer ABM completo del panel. Cuatro permisos de
        // grano fino (`comercial.cliente.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que las rutas de arriba.
        Route::get('/panel/clientes', [ClientesController::class, 'index'])
            ->name('panel.clientes.index');

        Route::get('/panel/clientes/crear', [ClientesController::class, 'create'])
            ->name('panel.clientes.create');

        Route::post('/panel/clientes', [ClientesController::class, 'store'])
            ->name('panel.clientes.store');

        Route::get('/panel/clientes/{cliente}/editar', [ClientesController::class, 'edit'])
            ->name('panel.clientes.edit');

        Route::put('/panel/clientes/{cliente}', [ClientesController::class, 'update'])
            ->name('panel.clientes.update');

        Route::delete('/panel/clientes/{cliente}', [ClientesController::class, 'destroy'])
            ->name('panel.clientes.destroy');

        // HU-23 (tarea 34): administración de contratos con sus ventanas de
        // aplicación. Sin `.destroy`: la baja es una transición de estado
        // (`cambiarEstado` hacia `cancelado`), no un soft delete fuera de la
        // máquina de estados (invariante 7). Cuatro permisos de grano fino
        // (`comercial.contrato.ver`/`.crear`/`.editar`/`.cambiar_estado`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `clientes` arriba.
        Route::get('/panel/contratos', [ContratosController::class, 'index'])
            ->name('panel.contratos.index');

        Route::get('/panel/contratos/crear', [ContratosController::class, 'create'])
            ->name('panel.contratos.create');

        Route::post('/panel/contratos', [ContratosController::class, 'store'])
            ->name('panel.contratos.store');

        Route::get('/panel/contratos/{contrato}/editar', [ContratosController::class, 'edit'])
            ->name('panel.contratos.edit');

        Route::put('/panel/contratos/{contrato}', [ContratosController::class, 'update'])
            ->name('panel.contratos.update');

        Route::post('/panel/contratos/{contrato}/estado', [ContratosController::class, 'cambiarEstado'])
            ->name('panel.contratos.cambiar-estado');

        // ADR 0018: propiedades del cliente — nivel de terreno entre
        // `Cliente` y `Campo`. Sin sub-entidad propia en esta pantalla: los
        // campos de una propiedad se cargan desde `campos` (abajo). Cuatro
        // permisos de grano fino
        // (`comercial.propiedad.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `clientes`/`contratos` arriba.
        Route::get('/panel/propiedades', [PropiedadesController::class, 'index'])
            ->name('panel.propiedades.index');

        Route::get('/panel/propiedades/crear', [PropiedadesController::class, 'create'])
            ->name('panel.propiedades.create');

        Route::post('/panel/propiedades', [PropiedadesController::class, 'store'])
            ->name('panel.propiedades.store');

        Route::get('/panel/propiedades/{propiedad}/editar', [PropiedadesController::class, 'edit'])
            ->name('panel.propiedades.edit');

        Route::put('/panel/propiedades/{propiedad}', [PropiedadesController::class, 'update'])
            ->name('panel.propiedades.update');

        Route::delete('/panel/propiedades/{propiedad}', [PropiedadesController::class, 'destroy'])
            ->name('panel.propiedades.destroy');

        // Tarea 77 (HU-54, etapa 2): ficha propia de un lote — antes solo se
        // podía tocar entrando por su propiedad. Cuatro permisos de grano
        // fino (`comercial.lote.ver`/`.crear`/`.editar`/`.eliminar`,
        // sembrados en la etapa 1 de esta misma tarea) verificados DENTRO
        // del controlador contra el ROL ACTIVO, mismo criterio que
        // `campos` arriba. El caso de uso de guardado es el mismo que usa
        // `CamposController` (ver docblock de `LotesController`).
        Route::get('/panel/lotes', [LotesController::class, 'index'])
            ->name('panel.lotes.index');

        Route::get('/panel/lotes/crear', [LotesController::class, 'create'])
            ->name('panel.lotes.create');

        Route::post('/panel/lotes', [LotesController::class, 'store'])
            ->name('panel.lotes.store');

        Route::get('/panel/lotes/{lote}/editar', [LotesController::class, 'edit'])
            ->name('panel.lotes.edit');

        Route::put('/panel/lotes/{lote}', [LotesController::class, 'update'])
            ->name('panel.lotes.update');

        Route::delete('/panel/lotes/{lote}', [LotesController::class, 'destroy'])
            ->name('panel.lotes.destroy');

        // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos, sin
        // sub-entidad. Cuatro permisos de grano fino
        // (`comercial.cultivo.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `campos`/`lotes` arriba.
        Route::get('/panel/cultivos', [CultivosController::class, 'index'])
            ->name('panel.cultivos.index');

        Route::get('/panel/cultivos/crear', [CultivosController::class, 'create'])
            ->name('panel.cultivos.create');

        Route::post('/panel/cultivos', [CultivosController::class, 'store'])
            ->name('panel.cultivos.store');

        Route::get('/panel/cultivos/{cultivo}/editar', [CultivosController::class, 'edit'])
            ->name('panel.cultivos.edit');

        Route::put('/panel/cultivos/{cultivo}', [CultivosController::class, 'update'])
            ->name('panel.cultivos.update');

        Route::delete('/panel/cultivos/{cultivo}', [CultivosController::class, 'destroy'])
            ->name('panel.cultivos.destroy');

        // HU-48 (tarea 71, etapa 3, ADR 0015 punto 4): qué se sembró en cada
        // lote de la propiedad, por campaña. Entra desde la ficha de la propiedad, no
        // desde `cultivos` (catálogo) ni desde `lotes` (estructura) — reusa
        // el permiso `comercial.propiedad.editar`: no es un ABM propio, es parte
        // de mantener los datos de ESA propiedad.
        Route::get('/panel/propiedades/{propiedad}/siembra', [SiembraController::class, 'mostrar'])
            ->name('panel.propiedades.siembra');

        Route::post('/panel/propiedades/{propiedad}/siembra', [SiembraController::class, 'guardar'])
            ->name('panel.propiedades.siembra.guardar');

        // "Crear Lotes" con un solo botón (HU-72 reconstruida, 16/9/2026 —
        // ver CrearLotesMasivo): entra desde la ficha de la propiedad,
        // reusa el permiso de Lote (`comercial.lote.crear`), no el de
        // Propiedad — mismo criterio que el resto del aside "Lotes".
        Route::get('/panel/propiedades/{propiedad}/lotes/generar', [GenerarLotesController::class, 'mostrar'])
            ->name('panel.propiedades.lotes.generar');

        Route::post('/panel/propiedades/{propiedad}/lotes/generar', [GenerarLotesController::class, 'guardar'])
            ->name('panel.propiedades.lotes.generar.guardar');

        // Adenda 16/9/2026 a ADR 0018 / ADR 0020 ("el editor de mapa
        // multi-polígono se construye en un feature aparte"): punto de
        // referencia (latitud/longitud) y perímetro (`geometria`) de la
        // propiedad, en pantalla propia — no en el formulario principal.
        // Reusa `comercial.propiedad.editar`: no es un ABM propio.
        Route::get('/panel/propiedades/{propiedad}/mapa', [PropiedadMapaController::class, 'mostrar'])
            ->name('panel.propiedades.mapa');

        Route::post('/panel/propiedades/{propiedad}/mapa', [PropiedadMapaController::class, 'guardar'])
            ->name('panel.propiedades.mapa.guardar');

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

        // HU-25 (tarea 38): órdenes de aplicación con su propia máquina de
        // estados (`emitida → vigente`). `activar` separada de `update`
        // (invariante 7): cambiar el estado no es la misma responsabilidad
        // que corregir un dato, mismo criterio que
        // `panel.contratos.cambiar-estado`. Cinco permisos de grano fino
        // (`operaciones.orden.ver`/`.crear`/`.editar`/`.activar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `clientes`/`contratos`/`campos`/`drones` arriba.
        Route::get('/panel/ordenes', [OrdenesController::class, 'index'])
            ->name('panel.ordenes.index');

        Route::get('/panel/ordenes/crear', [OrdenesController::class, 'create'])
            ->name('panel.ordenes.create');

        Route::post('/panel/ordenes', [OrdenesController::class, 'store'])
            ->name('panel.ordenes.store');

        Route::get('/panel/ordenes/{orden}/editar', [OrdenesController::class, 'edit'])
            ->name('panel.ordenes.edit');

        Route::put('/panel/ordenes/{orden}', [OrdenesController::class, 'update'])
            ->name('panel.ordenes.update');

        Route::post('/panel/ordenes/{orden}/activar', [OrdenesController::class, 'activar'])
            ->name('panel.ordenes.activar');

        Route::delete('/panel/ordenes/{orden}', [OrdenesController::class, 'destroy'])
            ->name('panel.ordenes.destroy');

        // HU-70 (tarea 85): reparto de una orden vigente entre equipos de
        // trabajo — ficha propia, no sub-recurso de `ordenes` (ver docblock
        // de `AsignacionEquiposController`). Un único permiso
        // (`operaciones.orden.asignar_equipos`) gatea las tres rutas.
        Route::get('/panel/asignacion-equipos', [AsignacionEquiposController::class, 'index'])
            ->name('panel.asignacion-equipos.index');

        Route::get('/panel/asignacion-equipos/{orden}', [AsignacionEquiposController::class, 'mostrar'])
            ->name('panel.asignacion-equipos.show');

        Route::post('/panel/asignacion-equipos/{orden}', [AsignacionEquiposController::class, 'asignar'])
            ->name('panel.asignacion-equipos.store');

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

        // HU-40 (tarea 50): administración de la flota de vehículos, con su
        // asignación a base y estado. Primer ABM del módulo `Mantenimiento`
        // (ADR 0011, extensión 3/9/2026). Sin sub-entidad, mismo molde que
        // `drones` arriba. Cuatro permisos de grano fino
        // (`mantenimiento.vehiculo.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que `clientes`/`campos`/`drones`/`bases`/`personas` arriba.
        Route::get('/panel/vehiculos', [VehiculosController::class, 'index'])
            ->name('panel.vehiculos.index');

        Route::get('/panel/vehiculos/crear', [VehiculosController::class, 'create'])
            ->name('panel.vehiculos.create');

        Route::post('/panel/vehiculos', [VehiculosController::class, 'store'])
            ->name('panel.vehiculos.store');

        Route::get('/panel/vehiculos/{vehiculo}/editar', [VehiculosController::class, 'edit'])
            ->name('panel.vehiculos.edit');

        Route::put('/panel/vehiculos/{vehiculo}', [VehiculosController::class, 'update'])
            ->name('panel.vehiculos.update');

        Route::delete('/panel/vehiculos/{vehiculo}', [VehiculosController::class, 'destroy'])
            ->name('panel.vehiculos.destroy');

        // HU-39 (tarea 51): catálogo de baterías con sus ciclos acumulados y
        // estado, para retirarlas antes de que fallen en vuelo. Segundo ABM
        // de `Mantenimiento`, mismo molde que `vehiculos` arriba. Cuatro
        // permisos de grano fino
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

        // Tarea 72 (HU-49, ADR 0015 punto 3): catálogo de generadores, ABM
        // mínimo — no es una HU propia, es la tabla que hace falta para
        // poder asignar un generador como equipamiento de un equipo de
        // trabajo. Mismo molde que `vehiculos`/`baterias` arriba. Cuatro
        // permisos de grano fino
        // (`mantenimiento.generador.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO.
        Route::get('/panel/generadores', [GeneradoresController::class, 'index'])
            ->name('panel.generadores.index');

        Route::get('/panel/generadores/crear', [GeneradoresController::class, 'create'])
            ->name('panel.generadores.create');

        Route::post('/panel/generadores', [GeneradoresController::class, 'store'])
            ->name('panel.generadores.store');

        Route::get('/panel/generadores/{generador}/editar', [GeneradoresController::class, 'edit'])
            ->name('panel.generadores.edit');

        Route::put('/panel/generadores/{generador}', [GeneradoresController::class, 'update'])
            ->name('panel.generadores.update');

        Route::delete('/panel/generadores/{generador}', [GeneradoresController::class, 'destroy'])
            ->name('panel.generadores.destroy');

        // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — el piloto
        // y su auxiliar, con el equipamiento asignado, cada uno con su
        // propia vigencia. Cuatro permisos de grano fino
        // (`personal.equipo_trabajo.ver`/`.crear`/`.editar`/`.eliminar`)
        // verificados DENTRO del controlador contra el ROL ACTIVO. La ficha
        // (`show`) y las rutas de integrantes/recursos exigen `.editar` para
        // mutar — asignar o finalizar una vigencia es mantener el equipo,
        // no un permiso aparte.
        Route::get('/panel/equipos-trabajo', [EquiposTrabajoController::class, 'index'])
            ->name('panel.equipos-trabajo.index');

        Route::get('/panel/equipos-trabajo/crear', [EquiposTrabajoController::class, 'create'])
            ->name('panel.equipos-trabajo.create');

        Route::post('/panel/equipos-trabajo', [EquiposTrabajoController::class, 'store'])
            ->name('panel.equipos-trabajo.store');

        Route::get('/panel/equipos-trabajo/{equipoTrabajo}', [EquiposTrabajoController::class, 'show'])
            ->name('panel.equipos-trabajo.show');

        Route::get('/panel/equipos-trabajo/{equipoTrabajo}/editar', [EquiposTrabajoController::class, 'edit'])
            ->name('panel.equipos-trabajo.edit');

        Route::put('/panel/equipos-trabajo/{equipoTrabajo}', [EquiposTrabajoController::class, 'update'])
            ->name('panel.equipos-trabajo.update');

        Route::delete('/panel/equipos-trabajo/{equipoTrabajo}', [EquiposTrabajoController::class, 'destroy'])
            ->name('panel.equipos-trabajo.destroy');

        Route::post('/panel/equipos-trabajo/{equipoTrabajo}/integrantes', [EquiposTrabajoController::class, 'asignarIntegrante'])
            ->name('panel.equipos-trabajo.integrantes.store');

        Route::delete('/panel/equipos-trabajo/{equipoTrabajo}/integrantes/{integrante}', [EquiposTrabajoController::class, 'desasignarIntegrante'])
            ->name('panel.equipos-trabajo.integrantes.destroy');

        Route::post('/panel/equipos-trabajo/{equipoTrabajo}/recursos', [EquiposTrabajoController::class, 'asignarRecurso'])
            ->name('panel.equipos-trabajo.recursos.store');

        Route::delete('/panel/equipos-trabajo/{equipoTrabajo}/recursos/{recurso}', [EquiposTrabajoController::class, 'desasignarRecurso'])
            ->name('panel.equipos-trabajo.recursos.destroy');

        // HU-36 (tarea 52): catálogo de repuestos con stock por base y
        // alerta de mínimo. Módulo nuevo `Inventario` (ADR 0011, extensión
        // 3/9/2026, punto 15) — no comparte tablas con `Mantenimiento`.
        // Cuatro permisos de grano fino
        // (`inventario.repuesto.ver`/`.crear`/`.editar`/`.eliminar`) para el
        // catálogo, verificados DENTRO del controlador contra el ROL ACTIVO,
        // mismo criterio que el resto del panel.
        Route::get('/panel/repuestos', [RepuestosController::class, 'index'])
            ->name('panel.repuestos.index');

        Route::get('/panel/repuestos/crear', [RepuestosController::class, 'create'])
            ->name('panel.repuestos.create');

        Route::post('/panel/repuestos', [RepuestosController::class, 'store'])
            ->name('panel.repuestos.store');

        Route::get('/panel/repuestos/{repuesto}/editar', [RepuestosController::class, 'edit'])
            ->name('panel.repuestos.edit');

        Route::put('/panel/repuestos/{repuesto}', [RepuestosController::class, 'update'])
            ->name('panel.repuestos.update');

        Route::delete('/panel/repuestos/{repuesto}', [RepuestosController::class, 'destroy'])
            ->name('panel.repuestos.destroy');

        // Stock agregado por (repuesto, base) y alta de movimientos
        // (compra/salida/ajuste/traslado). Sin edit/destroy: `inv_stock` es
        // un agregado derivado, toda mutación pasa por
        // `RegistrarMovimientoStock`. Dos permisos de grano fino
        // (`inventario.movimiento.ver`/`.crear`).
        Route::get('/panel/stock', [StockController::class, 'index'])
            ->name('panel.stock.index');

        Route::get('/panel/stock/movimientos/crear', [StockController::class, 'create'])
            ->name('panel.stock.movimientos.create');

        Route::post('/panel/stock/movimientos', [StockController::class, 'store'])
            ->name('panel.stock.movimientos.store');

        // HU-37 (tarea 53): "como encargado, quiero abrir órdenes de
        // mantenimiento y cerrarlas consumiendo repuestos, para que el costo
        // quede imputado". Sin `update`/`destroy` de negocio libre: `edit` es
        // la pantalla de detalle desde la que se dispara `cerrar`, el único
        // cambio de `estado` posible (invariante 7 de CLAUDE.md). Tres
        // permisos de grano fino
        // (`mantenimiento.orden.ver`/`.crear`/`.cerrar`), verificados DENTRO
        // del controlador contra el ROL ACTIVO, mismo criterio que el resto
        // del panel.
        Route::get('/panel/ordenes-mantenimiento', [OrdenesMantenimientoController::class, 'index'])
            ->name('panel.ordenes-mantenimiento.index');

        Route::get('/panel/ordenes-mantenimiento/crear', [OrdenesMantenimientoController::class, 'create'])
            ->name('panel.ordenes-mantenimiento.create');

        Route::post('/panel/ordenes-mantenimiento', [OrdenesMantenimientoController::class, 'store'])
            ->name('panel.ordenes-mantenimiento.store');

        Route::get('/panel/ordenes-mantenimiento/{orden}/editar', [OrdenesMantenimientoController::class, 'edit'])
            ->name('panel.ordenes-mantenimiento.edit');

        Route::post('/panel/ordenes-mantenimiento/{orden}/cerrar', [OrdenesMantenimientoController::class, 'cerrar'])
            ->name('panel.ordenes-mantenimiento.cerrar');

        // HU-38 (tarea 54): "como encargado, quiero planes de mantenimiento
        // preventivo por horas de vuelo, para que el sistema me avise antes
        // de la falla" — cierra Sprint 11. Cuatro permisos de grano fino
        // (`mantenimiento.plan.ver`/`.crear`/`.editar`/`.eliminar`),
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que el resto del panel. Nombre de ruta
        // `planes-mantenimiento` (no `planes`, a secas) por el mismo motivo
        // que `ordenes-mantenimiento` arriba: evitar cualquier ambigüedad de
        // nombre entre módulos, aunque hoy no exista colisión real.
        Route::get('/panel/planes-mantenimiento', [PlanesMantenimientoController::class, 'index'])
            ->name('panel.planes-mantenimiento.index');

        Route::get('/panel/planes-mantenimiento/crear', [PlanesMantenimientoController::class, 'create'])
            ->name('panel.planes-mantenimiento.create');

        Route::post('/panel/planes-mantenimiento', [PlanesMantenimientoController::class, 'store'])
            ->name('panel.planes-mantenimiento.store');

        Route::get('/panel/planes-mantenimiento/{plan}/editar', [PlanesMantenimientoController::class, 'edit'])
            ->name('panel.planes-mantenimiento.edit');

        Route::put('/panel/planes-mantenimiento/{plan}', [PlanesMantenimientoController::class, 'update'])
            ->name('panel.planes-mantenimiento.update');

        Route::delete('/panel/planes-mantenimiento/{plan}', [PlanesMantenimientoController::class, 'destroy'])
            ->name('panel.planes-mantenimiento.destroy');

        // HU-82 (tarea 97): ficha de inventario del dron — serie, chasis,
        // versión de software, región, serie del control y accesorios. ABM
        // nuevo sin máquina de estados, mismo molde que `baterias` arriba.
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

        // HU-28 (tarea 40): "como piloto o auxiliar, quiero ver mis devengos
        // por período" — primer permiso de panel para esos dos roles
        // (`finanzas.devengo.ver`), verificado DENTRO del controlador contra
        // el ROL ACTIVO, mismo criterio que las rutas de arriba. `index`
        // redirige a `show` con la persona del usuario autenticado; `show`
        // hace 404 ante cualquier `persona_id` que no sea la propia,
        // acceso cruzado incluido — no solo sin permiso.
        Route::get('/panel/devengos', [DevengosController::class, 'index'])
            ->name('panel.devengos.index');

        Route::get('/panel/devengos/{persona}', [DevengosController::class, 'show'])
            ->name('panel.devengos.show');

        // HU-29 (tarea 41): "como encargado, quiero registrar anticipos
        // validando el tope, para no adelantar más de lo devengado" — ABM
        // acotado (sin edición ni máquina de estados), gateado por tres
        // permisos de grano fino (`finanzas.anticipo.ver`/`.crear`/
        // `.eliminar`), verificados DENTRO del controlador contra el ROL
        // ACTIVO, mismo criterio que las rutas de arriba.
        Route::get('/panel/anticipos', [AnticiposController::class, 'index'])
            ->name('panel.anticipos.index');

        Route::get('/panel/anticipos/crear', [AnticiposController::class, 'create'])
            ->name('panel.anticipos.create');

        Route::post('/panel/anticipos', [AnticiposController::class, 'store'])
            ->name('panel.anticipos.store');

        Route::delete('/panel/anticipos/{anticipo}', [AnticiposController::class, 'destroy'])
            ->name('panel.anticipos.destroy');

        // HU-33 (tarea 47): "como encargado, quiero cargar gastos con su
        // categoría y comprobante, para que la campaña tenga costo real" —
        // ABM acotado (sin edición), gateado por tres permisos de grano fino
        // (`finanzas.gasto.ver`/`.crear`/`.eliminar`), verificados DENTRO
        // del controlador contra el ROL ACTIVO, mismo criterio que las
        // rutas de arriba.
        Route::get('/panel/gastos', [GastosController::class, 'index'])
            ->name('panel.gastos.index');

        Route::get('/panel/gastos/crear', [GastosController::class, 'create'])
            ->name('panel.gastos.create');

        Route::post('/panel/gastos', [GastosController::class, 'store'])
            ->name('panel.gastos.store');

        Route::delete('/panel/gastos/{gasto}', [GastosController::class, 'destroy'])
            ->name('panel.gastos.destroy');

        Route::get('/panel/gastos/{gasto}/comprobante', [GastosController::class, 'comprobante'])
            ->name('panel.gastos.comprobante');

        // HU-30 (tarea 44): "como dueño, quiero generar la planilla del
        // período desde los devengos y aprobarla, para pagar con un respaldo
        // que cuadre" — cierra el Sprint 8. Tres permisos de grano fino
        // (`finanzas.planilla.ver`/`.generar`/`.aprobar`), verificados
        // DENTRO del controlador contra el ROL ACTIVO, mismo criterio que
        // las rutas de arriba. `.aprobar` es exclusivo del rol `dueno`
        // (`SeguridadSeeder`).
        Route::get('/panel/planillas', [PlanillasController::class, 'index'])
            ->name('panel.planillas.index');

        Route::get('/panel/planillas/{planilla}', [PlanillasController::class, 'show'])
            ->name('panel.planillas.show');

        Route::post('/panel/planillas', [PlanillasController::class, 'store'])
            ->name('panel.planillas.store');

        Route::post('/panel/planillas/{planilla}/aprobar', [PlanillasController::class, 'aprobar'])
            ->name('panel.planillas.aprobar');

        Route::get('/panel/planillas/{planilla}/detalles/{detalle}/recibo', [PlanillasController::class, 'recibo'])
            ->name('panel.planillas.recibo');

        // HU-34 (tarea 48): "como jefe de campo, quiero rendir los gastos que
        // hice en campo; el encargado los aprueba para reponer el fondo" —
        // máquina de estados propia (abierta → presentada → aprobada), a
        // diferencia de `AnticiposController`. Cuatro permisos de grano fino
        // (`finanzas.rendicion.ver`/`.crear`/`.presentar`/`.aprobar`),
        // verificados DENTRO del controlador contra el ROL ACTIVO, mismo
        // criterio que las rutas de arriba. El aprobador nunca puede ser el
        // mismo jefe de campo que rindió (invariante 4 de CLAUDE.md,
        // `PoliticaAprobacionRendicion`) — eso lo resuelve el caso de uso,
        // no el permiso.
        Route::get('/panel/rendiciones', [RendicionesController::class, 'index'])
            ->name('panel.rendiciones.index');

        Route::get('/panel/rendiciones/crear', [RendicionesController::class, 'create'])
            ->name('panel.rendiciones.create');

        Route::post('/panel/rendiciones', [RendicionesController::class, 'store'])
            ->name('panel.rendiciones.store');

        Route::get('/panel/rendiciones/{rendicion}', [RendicionesController::class, 'show'])
            ->name('panel.rendiciones.show');

        Route::post('/panel/rendiciones/{rendicion}/gastos/{gasto}', [RendicionesController::class, 'asociarGasto'])
            ->name('panel.rendiciones.asociar_gasto');

        Route::post('/panel/rendiciones/{rendicion}/presentar', [RendicionesController::class, 'presentar'])
            ->name('panel.rendiciones.presentar');

        Route::post('/panel/rendiciones/{rendicion}/aprobar', [RendicionesController::class, 'aprobar'])
            ->name('panel.rendiciones.aprobar');

        // HU-35 (tarea 49): "como encargado, quiero registrar el
        // combustible del generador y de los vehículos, para imputarlo a la
        // campaña" — cierra Sprint 10. ABM acotado (sin edición), gateado
        // por tres permisos de grano fino (`finanzas.combustible.ver`/
        // `.crear`/`.eliminar`), verificados DENTRO del controlador contra
        // el ROL ACTIVO, mismo criterio que las rutas de arriba. Entidad
        // independiente de `ope_recargas.litros_combustible_generador` —
        // ver el docblock de la migración.
        Route::get('/panel/combustible', [CombustibleController::class, 'index'])
            ->name('panel.combustible.index');

        Route::get('/panel/combustible/crear', [CombustibleController::class, 'create'])
            ->name('panel.combustible.create');

        Route::post('/panel/combustible', [CombustibleController::class, 'store'])
            ->name('panel.combustible.store');

        Route::delete('/panel/combustible/{combustible}', [CombustibleController::class, 'destroy'])
            ->name('panel.combustible.destroy');

        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada, para cobrar sobre hectáreas ya
        // firmadas" — abre Sprint 9. Dos permisos de grano fino
        // (`comercial.factura.ver`/`.crear`), verificados DENTRO del
        // controlador contra el ROL ACTIVO, mismo criterio que las rutas de
        // arriba. Sin edición ni baja: una factura emitida es un snapshot
        // inmutable.
        Route::get('/panel/facturas', [FacturasController::class, 'index'])
            ->name('panel.facturas.index');

        Route::get('/panel/facturas/crear', [FacturasController::class, 'create'])
            ->name('panel.facturas.create');

        Route::post('/panel/facturas', [FacturasController::class, 'store'])
            ->name('panel.facturas.store');

        // HU-52 (tarea 75, espec §9.1): informe de avance de contratos, por
        // cultivo y por cliente — reemplaza en la misma ruta a la pantalla
        // plana de HU-32 (tarea 46). Un único permiso (`comercial.reporte.ver`),
        // exclusivo del dueño (no entra en `PERMISOS_ENCARGADO_OPERACIONES`,
        // mismo criterio que `finanzas.planilla.aprobar`), verificado DENTRO
        // del controlador contra el ROL ACTIVO. Sin `exportar`: el CSV plano
        // de HU-32 no tiene una forma razonable para un informe agrupado en
        // dos niveles — fuera de alcance de esta tarea.
        Route::get('/panel/reportes/comercial', [ReportesComercialesController::class, 'index'])
            ->name('panel.reportes.comercial.index');

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

    Route::get('/portal/avance', [AvancePortalController::class, 'index'])->name('portal.avance.index');

    Route::get('/portal/actas', [ActasPortalController::class, 'index'])->name('portal.actas.index');
    Route::get('/portal/actas/{acta}/pdf', [ActasPortalController::class, 'pdf'])->name('portal.actas.pdf');

    Route::get('/portal/reportes', [ReportesPortalController::class, 'index'])->name('portal.reportes.index');
    Route::get('/portal/reportes/{reporte}/pdf', [ReportesPortalController::class, 'pdf'])->name('portal.reportes.pdf');
});
