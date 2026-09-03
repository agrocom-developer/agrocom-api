<?php

use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\CamposController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ClientesController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ContratosController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\FacturasController;
use App\Dominios\Comercial\Infraestructura\Http\Controllers\Web\ReportesComercialesController;
use App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\AnticiposController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\DevengosController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\GastosController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\PlanillasController;
use App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\RendicionesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\AlertasController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\DronesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\OrdenesController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\TrabajosController;
use App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web\ValidacionSesionesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\BasesController;
use App\Dominios\Personal\Infraestructura\Http\Controllers\Web\PersonasController;
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

        // HU-24 (tarea 35): administración de campos con sus lotes. Un campo
        // se crea/edita con sus lotes en la misma operación (mismo criterio
        // que `clientes` arriba con sus contactos) — no hay ABM separado de
        // lotes ni rutas propias para ellos. Cuatro permisos de grano fino
        // (`comercial.campo.ver`/`.crear`/`.editar`/`.eliminar`) verificados
        // DENTRO del controlador contra el ROL ACTIVO, mismo criterio que
        // `clientes`/`contratos` arriba.
        Route::get('/panel/campos', [CamposController::class, 'index'])
            ->name('panel.campos.index');

        Route::get('/panel/campos/crear', [CamposController::class, 'create'])
            ->name('panel.campos.create');

        Route::post('/panel/campos', [CamposController::class, 'store'])
            ->name('panel.campos.store');

        Route::get('/panel/campos/{campo}/editar', [CamposController::class, 'edit'])
            ->name('panel.campos.edit');

        Route::put('/panel/campos/{campo}', [CamposController::class, 'update'])
            ->name('panel.campos.update');

        Route::delete('/panel/campos/{campo}', [CamposController::class, 'destroy'])
            ->name('panel.campos.destroy');

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

        // HU-32 (tarea 46): "como dueño, quiero un reporte comercial de
        // avance por cliente, contrato y campaña, para saber cuánto queda
        // por aplicar y por cobrar" — cierra Sprint 9. Un único permiso
        // (`comercial.reporte.ver`), exclusivo del dueño (no entra en
        // `PERMISOS_ENCARGADO_OPERACIONES`, mismo criterio que
        // `finanzas.planilla.aprobar`), verificado DENTRO del controlador
        // contra el ROL ACTIVO. `exportar` reusa el mismo permiso y respeta
        // el filtro activo — CSV nativo, sin librería de Excel.
        Route::get('/panel/reportes/comercial', [ReportesComercialesController::class, 'index'])
            ->name('panel.reportes.comercial.index');

        Route::get('/panel/reportes/comercial/exportar', [ReportesComercialesController::class, 'exportar'])
            ->name('panel.reportes.comercial.exportar');
    });
});
