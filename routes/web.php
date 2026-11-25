<?php

use App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Web\CalificacionesController;
use App\Dominios\Disputas\Infraestructura\Http\Controllers\Web\DisputasController;
use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Web\DronesController as DronesFlotaController;
use App\Dominios\Flota\Infraestructura\Http\Controllers\Web\EquiposController as EquiposFlotaController;
use App\Dominios\Metricas\Infraestructura\Http\Controllers\Web\MetricasController;
use App\Dominios\Negociacion\Infraestructura\Http\Controllers\Web\IntervencionController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web\AvisosController;
use App\Dominios\Notificaciones\Infraestructura\Http\Controllers\Web\NotificacionesController;
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
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web\ClientesController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web\SolicitudesController as SolicitudesPanelController;
use App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web\ZonasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web — panel (AdminLTE/Blade) y, a futuro, portal del cliente
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

        // Calificaciones (ADR 0031 y 0039): la cola de moderación; ocultar con causa y
        // motivo o dejar visible. Ocultar no borra: deja de contar para el promedio.
        Route::post('/panel/solicitudes/{solicitud}/proveedores/{proveedor}/cerrar-negociacion', [IntervencionController::class, 'cerrar'])
            ->whereNumber(['solicitud', 'proveedor'])
            ->name('panel.solicitudes.cerrar-negociacion');

        Route::get('/panel/evidencias/{evidencia}/archivo', [IntervencionController::class, 'evidencia'])
            ->whereNumber('evidencia')
            ->name('panel.evidencias.archivo');

        Route::get('/panel/clientes', [ClientesController::class, 'index'])
            ->name('panel.clientes.index');

        Route::get('/panel/clientes/{cliente}', [ClientesController::class, 'show'])
            ->whereNumber('cliente')
            ->name('panel.clientes.show');

        Route::post('/panel/clientes/{cliente}/suspender', [ClientesController::class, 'suspender'])
            ->whereNumber('cliente')
            ->name('panel.clientes.suspender');

        Route::post('/panel/clientes/{cliente}/reactivar', [ClientesController::class, 'reactivar'])
            ->whereNumber('cliente')
            ->name('panel.clientes.reactivar');

        // Avisos que el sistema envió a clientes, proveedores y administradores
        // (`notificaciones.aviso.ver`). Solo lectura.
        Route::get('/panel/avisos', [AvisosController::class, 'index'])
            ->name('panel.avisos.index');

        Route::get('/panel/zonas', [ZonasController::class, 'index'])
            ->name('panel.zonas.index');

        Route::get('/panel/zonas/crear', [ZonasController::class, 'create'])
            ->name('panel.zonas.create');

        Route::post('/panel/zonas', [ZonasController::class, 'store'])
            ->name('panel.zonas.store');

        Route::get('/panel/zonas/{zona}/editar', [ZonasController::class, 'edit'])
            ->whereNumber('zona')
            ->name('panel.zonas.edit');

        Route::put('/panel/zonas/{zona}', [ZonasController::class, 'update'])
            ->whereNumber('zona')
            ->name('panel.zonas.update');

        Route::delete('/panel/zonas/{zona}', [ZonasController::class, 'destroy'])
            ->whereNumber('zona')
            ->name('panel.zonas.destroy');

        Route::get('/panel/calificaciones', [CalificacionesController::class, 'index'])
            ->name('panel.calificaciones.index');

        Route::post('/panel/calificaciones/{calificacion}/ocultar', [CalificacionesController::class, 'ocultar'])
            ->whereNumber('calificacion')
            ->name('panel.calificaciones.ocultar');

        Route::post('/panel/calificaciones/{calificacion}/restablecer', [CalificacionesController::class, 'restablecer'])
            ->whereNumber('calificacion')
            ->name('panel.calificaciones.restablecer');

        // Disputas (ADR 0033 y 0039): la cola, la ficha con la conversación, la
        // mediación de soporte y la resolución con resultado, reembolso y motivo.
        Route::get('/panel/disputas', [DisputasController::class, 'index'])
            ->name('panel.disputas.index');

        Route::get('/panel/disputas/{disputa}', [DisputasController::class, 'show'])
            ->whereNumber('disputa')
            ->name('panel.disputas.show');

        Route::post('/panel/disputas/{disputa}/tomar', [DisputasController::class, 'tomar'])
            ->whereNumber('disputa')
            ->name('panel.disputas.tomar');

        Route::post('/panel/disputas/{disputa}/mensajes', [DisputasController::class, 'mensaje'])
            ->whereNumber('disputa')
            ->name('panel.disputas.mensaje');

        Route::post('/panel/disputas/{disputa}/resolver', [DisputasController::class, 'resolver'])
            ->whereNumber('disputa')
            ->name('panel.disputas.resolver');

        // Métricas del mercado (especificación §14): solicitudes, asignación, aceptación,
        // calificación, disputas, tiempos e ingreso. Solo lectura.
        Route::get('/panel/metricas', [MetricasController::class, 'index'])
            ->name('panel.metricas.index');

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

        Route::get('/panel/drones-flota', [DronesFlotaController::class, 'index'])
            ->name('panel.drones-flota.index');

        Route::get('/panel/drones-flota/fotos/{foto}/archivo', [DronesFlotaController::class, 'foto'])
            ->whereNumber('foto')
            ->name('panel.drones-flota.foto');

        Route::post('/panel/drones-flota/fotos/{foto}/revisar', [DronesFlotaController::class, 'revisarFoto'])
            ->whereNumber('foto')
            ->name('panel.drones-flota.foto-revisar');

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
