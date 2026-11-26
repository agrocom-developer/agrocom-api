# Limpieza del sistema de negociación: auditoría

Inventario priorizado de lo que ya no pertenece al sistema vigente o está duplicado en `agrocom-api`. Es una auditoría: no cambia código ni esquema. Cada fila se ejecuta como un PR independiente, en el orden de la sección 9.

Sistema vigente: el cliente publica una solicitud con precio por hectárea, los pilotos aceptan o contraofertan (hasta 5 rondas), el cliente elige, el piloto ejecuta con fotos y avance, el cierre fija las hectáreas ejecutadas, el precio final es hectáreas ejecutadas × precio acordado (sin cobro en la app) y ambas partes califican. La app móvil tiene dos flavors, `cliente` y `piloto`; el flavor y el rol móvil `auxiliar` se retiraron (ADR 0021 de `agrocom-field`). Lo obsoleto se identifica como «modelo de campo anterior»: sesión de vuelo, condiciones, mezcla, recargas, acta de conformidad, reporte técnico, cuadrillas y auxiliar.

Medición: rama `develop` (commit 191f8963). Los consumidores de la app se buscaron en `lib/` de `agrocom-field` en `origin/develop` (rutas `/api/...` literales y construidas). La base de producción no se consultó: antes de cualquier `DROP` se ejecuta `SELECT count(*)` sobre la tabla y se anota el resultado en el PR.

Convenciones de las tablas:

- **Riesgo**: bajo, medio o alto. «Datos» indica si hay filas que perder (demo, producción o desconocido).
- **Forma**: `borrar` (se elimina en un PR sin migración), `migración` (exige `down()` y respaldo) o `doc` (solo texto).
- **REVISIÓN HUMANA**: el dueño revisa línea por línea (CLAUDE.md: zonas críticas).

## 1. Hallazgos que condicionan todo

1. **El panel ya no tiene pantallas del modelo de campo anterior.** `Operaciones`, `Personal`, `Mantenimiento` y `Mezclas` no tienen controladores web, vistas ni menú. Solo viven por `POST /api/sync`, `POST /api/evidencias`, las rutas de actas y reportes, y el catálogo `GET /api/sync/catalogo`. Retirarlos es retirar API y esquema, no pantallas.
2. **La app todavía llama a `/api/sync` y a `/api/evidencias`** (motor `SyncEngine` y `EvidenciaSyncEngine`, que sirven a `features/sesion_vuelo`). Ese código de la app también es candidato a retiro; el servidor ya no puede forzar una versión mínima (`GET /api/version` se retiró con el dominio `Distribucion`), así que el retiro de esas rutas se coordina con el lanzamiento de la app en las tiendas.
3. **Los esquemas `respaldo_*` ya no existen** (`2026_12_04_100100_elimina_esquemas_de_respaldo`). El motor de asignación (`asg_*`, `flo_disponibilidad`), la operación interna (`fin_*`, `inv_*`, `man_*` parciales) y contratos y campañas (`com_*`, `cpn_campanias`, `ope_ordenes_*`) ya salieron de `public` y su respaldo se eliminó con `DROP SCHEMA ... CASCADE`; `down()` solo recrea esquemas vacíos. El código no referencia `asg_` fuera de las migraciones históricas (grep de `app/`, `routes/`, `config/`, `lang/` y `tests/`).
4. **Las migraciones de retiro anteriores sirven de plantilla** para lo que sigue: `2026_11_23_100000_retira_operacion_interna` (mover a esquema `respaldo_*` con `SET SCHEMA`, `down()` los devuelve), pero el respaldo no debe borrarse en otro PR hasta que el dueño confirme (ver sección 5).
5. **Las convenciones de documentación (`docs/`, `.claude/`, `prompts/`) siguen hablando de tres apps** y del auxiliar como usuario móvil (sección 7).

## 2. Backend: dominios, casos de uso, controladores, modelos, eventos, comandos

Archivos bajo `app/Dominios/` salvo indicación.

| # | Ítem | Evidencia de que ya no aplica | Riesgo y datos | Forma | Notas |
|---|---|---|---|---|---|
| B1 | `Operaciones/Infraestructura/Http/Controllers/Api/ActaController.php`, `Aplicacion/GenerarActaTrabajo`, `FirmarActa`, `Dominio/MaquinaEstados/TransicionesActa`, `Aplicacion/MaquinaEstados/MaquinaEstadosActa`, `Eloquent/Acta`, requests `GenerarActaRequest` y `FirmarActaRequest`, vistas `Views/pdf/acta.blade.php` y `estilos-base.blade.php`, excepciones `FirmaActaNoDisponible`, `TrabajoNoListoParaActa`, `TransicionActaNoPermitida` | Rutas `POST /api/trabajos/{uuid}/acta`, `POST /api/actas/{uuid}/firmar`, `GET /api/actas/{uuid}/pdf` sin ninguna referencia en `lib/` de la app. El producto no tiene acta: el cierre lo fija el piloto con las hectáreas ejecutadas | Bajo. Datos: filas en `ope_actas` (demo; producción desconocido) | `borrar` el código y las 3 rutas; la tabla va en B14 | Quita también los permisos `operaciones.acta.generar` y `operaciones.acta.firmar` (F3) y los ejemplos de `docs/api/ejemplos/` y del contrato |
| B2 | `Operaciones/.../Api/ReporteTecnicoController.php`, `Aplicacion/GenerarReporteTecnico`, `ArmarContenidoReporteTecnico` (281 líneas), `Eloquent/ReporteTecnico`, `Views/pdf/reporte-tecnico.blade.php`, `Infraestructura/Pdf/PaletaPdf` | `GET /api/reportes/lote/{trabajo}` sin consumidor; el reporte lee mezcla, recargas y sesión de vuelo del modelo anterior | Bajo; `ope_reportes_tecnicos` con datos de demo | `borrar` código y ruta; tabla en B14 | Depende de B1 solo por los PDF |
| B3 | **REVISIÓN HUMANA**: `Sincronizacion/Aplicacion/SincronizarLote` (309), `Http/Controllers/Api/SyncController` (250), `SincronizarLoteRequest`, `Operaciones/Infraestructura/EscrituraSincronizacionEloquent` (792), `Operaciones/Contratos/EscrituraSincronizacion` y los DTO `AperturaSesion`, `CierreSesion`, `AperturaTrabajo`, `CierreTrabajo`, `RegistroCondiciones`, `RegistroRecarga`, `RegistroRecepcionCaldo`, `RegistroIncidencia`, `RegistroEvidencia*`, `ResultadoSincronizacion` | `POST /api/sync` acepta diez tipos de registro (`trabajo`, `sesion`, `condiciones`, `recarga`, `mezcla`, `recepcion_caldo`, `evidencia_equipo`, `incidencia`, `cierre_trabajo`, `cierre_sesion`). Los registros del servicio vigente (`inicio`, `avance`, `cierre`, `mensaje`) viajan por sus rutas REST, no por el lote (`estado-api.md`). La app aún lo llama (`nucleo/sync/sync_engine.dart`) | Alto: motor de sync, invariantes 1 y 2. Datos: ninguno propio; escribe en `ope_*` y `mez_*` | `borrar` en un PR propio **después** de que la app mínima no lo llame. Reemplaza al lote L5 de `auditoria-calidad.md`: retirar es más barato que partir el escritor | Conservar mientras tanto la prueba de replay; `ContratoOpenApiTest` y los ejemplos `sync-lote-*` se retiran con la ruta |
| B4 | `Sincronizacion/Aplicacion/ObtenerCatalogoDesdeCursor` y `CatalogoController`: secciones de personas, drones y equipos del modelo anterior | `GET /api/sync/catalogo` **sí** lo consume la app (`nucleo/catalogo`, `ejecucion_sync_engine`) para `servicios_asignados` y `mensajes`; las secciones que leen `Personal` y `Operaciones` no se consumen en el flujo vigente (no verificado campo por campo: revisar en el PR) | Medio | Recortar secciones; la ruta se conserva | `ObtenerCatalogoDesdeCursor` importa `Personal` y `Operaciones`; al retirarlos el catálogo debe quedarse con solicitudes y mensajes |
| B5 | `Operaciones/Aplicacion/ValidarSesion`, `RechazarSesion`, `PoliticaValidacionSesion`, `MaquinaEstadosSesion`, `Eloquent/Sesion` (208), `SesionRechazo`, excepciones de sesión, evento `SesionValidada` | La sesión de vuelo no existe en el producto. `auditoria-calidad.md` §2.4 ya las listaba como «código sin entrada». Invariantes 3 y 4 de `CLAUDE.md` hablan de sesiones | Medio: hay que reescribir las invariantes 3 y 4 antes de borrar | `borrar` con el PR de B3; luego tablas (B14) | Decisión humana: qué invariante reemplaza a «validar una sola vez» (el cierre del servicio) |
| B6 | `Operaciones/Aplicacion/RegistrarPausa`, `RegistrarPausaRequest`, `Eloquent/Pausa`, `CausaPausa`; `Condiciones`, `LimitesEfectivos`, `LecturaLimitesDeVuelo`, `LimitesDeVueloDelSistema`; `Recarga`, `RecepcionCaldo`, `TipoInsumo`, `CategoriaInsumo`; `Alerta`, `GenerarAlertaExcepcion` (170), `AtenderAlerta`, `MaquinaEstadosAlerta`, `TipoAlerta`, `LecturaAlertasTemperaturaBateria*` | Sin controlador ni ruta; solo los escribe el sync (B3). Sin permiso en el menú del panel | Bajo | `borrar` con B3 | `config/operaciones.php` (27 líneas) y `lang/es/operaciones.php` salen con ellos |
| B7 | `Operaciones/Aplicacion/EliminarTrabajo`, `CalcularCoberturaTrabajo`, `Eloquent/Trabajo` (273), `MaquinaEstadosTrabajo`, `EstadoTableroTrabajo`, `EstadoCoberturaTrabajo`, evento `TrabajoCerrado` y `Notificaciones/Aplicacion/Reglas/ReglaTrabajoCerrado`; `Solicitudes/Contratos/SolicitudParaTrabajo` y `DatosSolicitudParaTrabajo` | El «trabajo» duplica a la solicitud en ejecución: hectáreas, cierre y estado ya viven en `sol_solicitudes_servicio` y `neg_avances`. `ope_trabajos.solicitud_id` los ata (migración `2026_11_24_200000`) | Medio. La regla de notificación `TrabajoCerrado` debe reemplazarse por la del cierre de la solicitud (`SolicitudFinalizada`) si aún no existe | `borrar` tras comprobar que `SolicitudFinalizada` cubre el aviso | `CierreTrabajoDadoDeBajaTest` y `SincronizacionDeTrabajoDeSolicitudTest` salen aquí |
| B8 | `Operaciones/Aplicacion/CrearDron`, `ActualizarDron`, `Eloquent/Dron` (`ope_drones`), `LecturaDronesEloquent`, `Contratos/DronCatalogo`, `LecturaDrones` | Dron duplicado en tres módulos (`ope_drones`, `man_drones`, `flo_drones`; ver `auditoria-calidad.md` §2.4). El dron vigente es `flo_drones`; `flo_drones.ope_dron_id` y `Flota/Aplicacion/IncrementarCiclosBateriaDron` conservan el puente | Medio: toca claves foráneas de `ope_sesiones.dron_id` y `flo_drones.ope_dron_id` | `migración` (quitar `ope_dron_id`, borrar `ope_drones`) | Va con L12 de `auditoria-calidad.md`, después de B3 |
| B9 | `Mezclas/` completo (11 archivos: `Mezcla`, `MezclaDetalle`, `Producto`, escritor y lector) | Solo lo llaman `SincronizarLote` y `ArmarContenidoReporteTecnico`. La mezcla era trabajo del auxiliar | Bajo; `mez_mezclas`, `mez_mezcla_detalles`, `mez_productos` con datos de demo | `borrar` el código con B3; tablas en B14 | `Producto` (catálogo de productos) podría servir al formulario de solicitud; hoy el producto es texto libre en la solicitud: decidir antes de borrar `mez_productos` |
| B10 | `Mantenimiento/` completo (17 archivos: `Bateria`, `FichaDron`, `CrearBateria`, `CrearFichaDron`, `IncrementarCiclosBateria`, lecturas de equipamiento) | Los ciclos de batería y la ficha del dron nacieron para la sesión de vuelo; `CatalogoEquipamientoPanel` (Seguridad) y `ArmarContenidoReporteTecnico` son sus únicos consumidores. Sin vistas ni rutas | Bajo; `man_baterias` y `man_drones` con datos de demo | `borrar` código; tablas en B14 | Retira también `Flota/Aplicacion/IncrementarCiclosBateriaDron` |
| B11 | **REVISIÓN HUMANA** (identidad): `Personal/` completo (50 archivos: `Base`, `EquipoTrabajo`, `EquipoIntegrante`, `EquipoRecurso`, `EquipoAccesorio`, `Accesorio`, `ArmarCuadrilla`, `ValidadorSolapamientoVigencias`) salvo `PerPersona` | Cuadrillas, bases y accesorios son del modelo anterior; el panel no los muestra. `PerPersona` **no** es sobrante todavía: `sec_user.persona_id`, `IdentidadOperarioToken` (token de operario), `ResolverDestinatarios`, `CrearUsuarioRequest` y `ActualizarUsuarioRequest` (`Rule::exists('per_personas')`) la usan | Alto para `PerPersona` (identidad del token); bajo para el resto | `borrar` equipos, bases, accesorios y cuadrillas; `PerPersona` queda para el PR de unificación (B15) | `AsignarRecursoEquipo` también importa `Mantenimiento` y `Operaciones` |
| B12 | `Proveedores/Aplicacion/RegistrarAuxiliar`, `Eloquent/Auxiliar`, `Contratos/DatosAuxiliar`, `Http/Controllers/Api/AuxiliaresController` (rutas `GET/POST /api/proveedores/auxiliares` y `POST .../{id}/reenviar`), `Resources/AuxiliarResource`, `RegistrarAuxiliarRequest`, ramas de auxiliar en `ResolverSujeto`, `ResolverAprobacion`, `MaquinaEstadosAprobacion`, `AlcanceDelSolicitante`, `ProcesarVencimientos`, `DocumentosController` | La app no tiene el flavor ni llama las 3 rutas. **Decisión humana previa (D1)**: el ADR 0021 de la app deja al servidor decidir si el auxiliar sigue existiendo como dato del equipo | Medio. Datos: `prv_auxiliares` (demo; producción desconocido) | `borrar` si D1 = retirar; si D1 = conservar como dato, solo se retira la ruta de autoservicio | Tiene ramas en 30 archivos (sección 6) |
| B13 | `Flota`: `auxiliar_id` en `Aplicacion/CrearEquipo`, `ActualizarEquipo`, `AptitudDeEquipo`, `Eloquent/Equipo`, `EquipoResource`, `EquiposController` (API y web), `LecturaEquiposEloquent` | Equipo = piloto + dron; el auxiliar opcional ya no es parte del producto | Medio: `flo_equipos.auxiliar_id` es una columna | `migración` (quitar columna con respaldo) tras B12 | `AptitudDeEquipoTest` cambia |
| B14 | Tablas del modelo anterior | Ver sección 3 | — | `migración` | — |
| B15 | Duplicados de `auditoria-calidad.md` §2.4: equipo (`per_equipos_trabajo` frente a `flo_equipos`), persona (`per_personas` frente a `prv_pilotos`) | `ope_trabajos.equipo_trabajo_id` apunta al equipo anterior; el catálogo lee `per_personas` | Alto | `migración` (lote L12) | Después de B3 y B11 |
| B16 | Evidencias duplicadas: `Operaciones/Aplicacion/RegistrarEvidencia`, `Http/Controllers/Api/EvidenciaController`, `SubirEvidenciaRequest`, `Eloquent/Evidencia` y `EvidenciaEquipo` (`ope_evidencias`, `ope_evidencias_equipo`) frente a `Negociacion/.../SeguimientoController::subirEvidencia` y `neg_evidencias` | `POST /api/evidencias` lo llama `EvidenciaSyncEngine` de la app, pero la ejecución vigente sube por `POST /api/solicitudes/{id}/evidencias`. Dos colas con dos tablas para lo mismo | Medio. Datos: `ope_evidencias*` con archivos en disco privado (ADR 0026): borrar filas deja archivos huérfanos | `borrar` ruta con B3; tabla y archivos en B14 con inventario de archivos | `capturas_rc` (`tests/.../capturas_rc`, componente `captura-rc-card`) es del mismo flujo (sección 4) |
| B17 | `Negociacion/Aplicacion/Seguimiento` (326), `Negociar` (504) | **No es obsoleto**: es el flujo vigente. Se anota porque `auditoria-calidad.md` L7 los parte en una clase por acción | — | — | `Negociar::aceptar`, `Participantes` y `UbicacionDelPiloto` son **REVISIÓN HUMANA** (adjudicación atómica, datos del cliente, ubicación) |
| B18 | Rutas duplicadas por nombre: `Seguridad/.../Api/DispositivoController` (`GET /api/dispositivos`, `GET /api/dispositivos/{id}`, tabla `sec_token_dispositivo`) frente a `Notificaciones/.../DispositivosPushController` (`POST` y `DELETE`, tabla `ntf_dispositivos`) | Dos conceptos con el mismo prefijo: dispositivo con sesión y token de push. Los `GET` no tienen consumidor en la app | Bajo | `doc`: renombrar los `GET` a `/api/sesiones-de-dispositivo` o retirarlos | Decisión de contrato: afecta `openapi.yaml` |
| B19 | Portal web del cliente (`routes/web.php` guard `auth:cliente`: `portal.logout`, `portal.perfil.*`, `portal.preferencias.*`, `portal.restablecer.*`; `RestablecerContrasenaPortalController`, `SesionPortalController`, `PerfilPortalController`) | El cliente opera por la app; el portal solo edita perfil y restablece contraseña. `SecUser` conserva el comentario de `contrato_id` (portal) que la migración `2026_11_24_200100` quitó de `sec_user` | Medio: si hay clientes que entran por web, perderían el acceso | `doc` primero: decidir si el portal existe; si no, `borrar` | Revisión humana de producto |

Comandos artisan y trabajos programados: los cuatro vigentes son `negociacion:procesar`, `seguimiento:avisar-inactividad`, `solicitudes:evaluar-clientes` y `proveedores:vencimientos`; ninguno es del modelo anterior. `proveedores:asignar-titular-propio` (`AsignarTitularPropioCommand`) es de alta única de operación: conviene confirmar que ya se ejecutó en cada entorno. `ListarModelosCommand` es de apoyo. No hay jobs ni colas propias del modelo anterior. Políticas: la autorización se hace por permisos (`AutorizacionPanelWeb`), no por clases `Policy`.

## 3. Base de datos

Las tablas se crean en `database/migrations/` con prefijo por módulo (ADR 0011). «Creada por» es la migración original; todas están consolidadas en el estado del 2026_09_23 (ADR 0024).

| # | Tabla o columna | Creada por | Quién la usa hoy | Datos | Forma y riesgo |
|---|---|---|---|---|---|
| T1 | `ope_actas` | `2026_09_23_000020` | `ActaController` (B1) | Demo; producción desconocido | `migración` mover a esquema de respaldo; bajo |
| T2 | `ope_reportes_tecnicos` | `2026_09_23_000059` | `ReporteTecnicoController` (B2) | Ídem | Ídem |
| T3 | `ope_sesiones`, `ope_sesion_rechazos`, `ope_pausas`, `ope_condiciones`, `ope_recargas`, `ope_recepciones_caldo`, `ope_alertas` | `2026_09_23_000027`, `..060`, `..057`, `..049`, `..050`, `..058`, `..051` | Solo el sync (B3) | Demo; **producción posible**: si hubo operación real antes del pivote, son los datos de campo capturados sin señal | `migración` con respaldo y `down()`. Alto: nunca `DROP` directo. Trigger `ope_sesiones_inmutable_validada()` (migración `2026_12_06_100000`) debe retirarse antes: impide borrar sesiones validadas |
| T4 | `ope_trabajos` (incluye `solicitud_id`, `equipo_trabajo_id`) | `2026_09_23_000019`, `2026_11_24_200000` | Sync, `AvanceDeSolicitud` | Ídem | `migración`; alto (vista y triggers sobre ella). Antes comprobar que ningún `vw_*` la lee |
| T5 | `ope_evidencias`, `ope_evidencias_equipo`, `ope_incidencias`, `ope_categorias_insumo` | `2026_09_23_000014`, `..053`, `..054`, `..016` | Sync y `EvidenciaController` | Ídem; archivos en disco | `ope_incidencias` queda hasta decidir `POST /api/incidencias` (pendiente en `estado-api.md`): puede ser la base de las incidencias del cliente. El resto, `migración` |
| T6 | `ope_drones` | `2026_09_23_000026` | `LecturaDronesEloquent`, `flo_drones.ope_dron_id` | Ídem | `migración` (B8) |
| T7 | `mez_mezclas`, `mez_mezcla_detalles`, `mez_productos` | `2026_09_23_000046` a `..048` | Sync (B9) | Demo | `migración`; bajo |
| T8 | `man_baterias`, `man_drones` | `2026_09_23_000041`, `..042` | `Mantenimiento` (B10) | Demo | `migración`; bajo |
| T9 | `per_bases`, `per_equipos_trabajo`, `per_equipo_integrantes`, `per_equipo_recursos`, `per_equipo_accesorios`, `per_accesorios` | `2026_09_23_000012`, `..015`, `..062` a `..065` | `Personal` (B11) | Demo (cuadrillas) | `migración`; medio por las claves de `ope_trabajos` |
| T10 | `per_personas` y `sec_user.persona_id` | `2026_09_23_000013`, `..033` | Identidad del token de operario, formularios de usuario | Demo y posiblemente producción | **REVISIÓN HUMANA**: no se borra sin definir el reemplazo (`prv_pilotos`) |
| T11 | `prv_auxiliares`, `flo_equipos.auxiliar_id`, `prv_documentos.sujeto_tipo = 'auxiliar'`, `cal_calificaciones.sujeto_tipo = 'auxiliar'`, estados de `prv_historial_estados` del auxiliar | `2026_11_23_110400`, `..110800`, `..110500`, `2026_11_26_110000`, `..110600` | Alta de auxiliar (B12), calificación al auxiliar | Demo; producción desconocido | `migración` en tres pasos: dejar de escribir, respaldar, quitar. Los `CHECK` de `cal_calificaciones` y de `prv_documentos` listan `auxiliar` y se reescriben con el patrón de `2026_12_01_100200` (soltar y recrear la restricción). Medio |
| T12 | Funciones y triggers de `2026_12_06_100000_crea_funciones_y_triggers_de_integridad`: `ope_sesiones_inmutable_validada()` y los disparadores de `plt_registrar_bitacora()` sobre tablas `ope_*` | `2026_12_06_100000` | Solo protegen tablas de T3 | — | `migración`; se retiran antes que las tablas |
| T13 | Vistas `vw_metricas_*` (`2026_12_05_100000`) | — | La migración no referencia `ope_`, `man_` ni `mez_`: no bloquean el borrado | — | Ninguna acción |
| T14 | Índices de `2026_12_04_100000_agrega_indices_de_consulta` sobre `ope_sesiones.*`, `ope_alertas.sesion_id`, `ope_evidencias.subido_por`, `flo_equipos.auxiliar_id` | `2026_12_04_100000` | Caen con sus tablas | — | Incluir en las migraciones de T3 y T11 |
| T15 | Permisos y menú del modelo anterior en `sec_permission`, `sec_role_permission`, `sec_menu` | `SeguridadSeeder`, `SecMenuSeeder` | `operaciones.acta.*`, `operaciones.reporte.ver`, `proveedores.auxiliar.*`, `flota.equipo.*` y la lista «retirados» (más de 60 códigos de `finanzas`, `inventario`, `mantenimiento`, `operaciones`, `personal` que el sembrador da de baja lógica) | Producción posible | Ver sección 6 |

**Seeders y factories.** Los sembradores `Catalogo/` son vigentes salvo `OperacionesCategoriasInsumoSeeder` (T5) y la lista de retirados de `SeguridadSeeder`. `Demo/CuadrillasDemoSeeder`, `PersonalDemoSeeder`, `PersonasDeOperariosDemoSeeder` y las ramas de auxiliar de `ProveedoresDemoSeeder`, `FlotaDemoSeeder` y `NegociacionDemoSeeder` solo crean datos del modelo anterior: son datos de demo, no de producción (la demo es `docs/gestion/demo-local.md`). Las factories (`SecUserFactory`, `VersionApkFactory`) son vigentes. `Demo/RenovarVigenciaDemoSeeder` y `PlataformaDemoSeeder` son vigentes.

## 4. Panel web: vistas, componentes, CSS, menú

| # | Ítem | Evidencia | Riesgo | Forma |
|---|---|---|---|---|
| P1 | Rutas `panel.auxiliares.index`, `.show`, `.resolver`; `Proveedores/.../Web/AuxiliaresController`, vistas `pages/auxiliares/index.blade.php` y `show.blade.php`; ítem de menú `auxiliares` en `SecMenuSeeder` (línea 116); claves `proveedores.auxiliares.*` de `lang/es/proveedores.php`, `menu.php`, `aprobacion.php` | El auxiliar no tiene app. Si D1 = retirar, la pantalla queda sin función | Bajo | Hecho (sección 11) |
| P2 | `panel.equipos-flota.index` y `Flota/.../pages/equipos/index.blade.php` | Hecho: la pantalla de administración se retiró; el equipo (piloto + dron) sigue en la API y en la ficha del dron | Bajo | Hecho |
| P3 | Componente `captura-rc-card` (`resources/views/components/molecules/`, `resources/css/components/captura-rc-card.css`) y reglas de `resources/css/pages/multimedia.css` | Cero usos en vistas (`grep` del nombre fuera de su propio archivo); captura del control remoto de la sesión de vuelo (`ope_sesiones.captura_rc_id`) | Bajo | `borrar` |
| P4 | Componentes sin ningún `<x-...>` que los use: `tiered-progress-bar`, `distribution-bar` | Búsqueda de la etiqueta y del nombre en `app/`, `resources/`, `tests/`, `lang/`: 0 usos (`distribution-bar` aparece mencionada en `auditoria-calidad.md` como parte del test de tokens; confirmar que `TokensColorTest` no la requiere) | Bajo | `borrar` tras confirmar |
| P5 | Livewire: `composer.json` exige `livewire/livewire ^3` y `app.blade.php` y `panel-shell.blade.php` cargan `@livewireStyles` y `@livewireScripts`, pero `app/` y `resources/` no tienen ningún componente Livewire | La dependencia y las dos directivas cargan JavaScript sin usarlo | Bajo | `borrar` la dependencia y las directivas (la `docs/diseno` y el ADR 0002 mencionan Livewire: reescribirlos) |
| P6 | CSS de `resources/css/pages/`: `multimedia.css` (P3) | `tablero.css` sí es vigente (lo usan `dashboard.blade.php` y `_seguimiento.blade.php`); el resto de `pages/` corresponde a vistas existentes | Bajo | Solo `multimedia.css`, con P3 |
| P7 | Selector de persona (`per_personas`) en `usuarios/crear` y `usuarios/editar` (`CrearUsuarioRequest`, `ActualizarUsuarioRequest`, `usuarios-form.js`) | Vincula un usuario del panel con una persona de campo del modelo anterior | Medio | Con B15 |
| P8 | PDF: `acta.blade.php`, `reporte-tecnico.blade.php`, `PaletaPdf` | Ver B1 y B2 | Bajo | Con B1 y B2 |
| P9 | `lang/es/operaciones.php`, `personal.php`, `mantenimiento.php` (cientos de claves sin uso, `auditoria-calidad.md` §4) | Compuerta de claves sin uso (L14) | Bajo | `borrar` con los módulos |

## 5. Contrato (`docs/api/openapi.yaml`) frente a lo que consume la app

`routes/api.php` define unas 110 rutas. El generador agrupa varias operaciones bajo la misma ruta base; la tabla usa las rutas reales. Estado = **C** consumida por la app en `origin/develop`, **V** vigente pero sin consumidor todavía (pendiente de la app, no obsoleta), **O** obsoleta.

| Estado | Rutas | Nota |
|---|---|---|
| C | `POST /auth/token`, `GET /auth/sesion`, `DELETE /auth/token`, `GET /zonas`, `PUT/DELETE /perfil/foto`, `GET /personas/{usuario}/foto`, `POST /clientes/registro`, `GET /clientes/mi`, `POST /proveedores/registro`, `GET /proveedores/mi`, `GET/POST /proveedores/drones` y `.../fotos`, `GET/POST /terrenos` y `.../fotos`, `GET/POST /solicitudes` y `{id}`, `publicar`, `cancelar`, `precio`, `renovar`, `ofertas`, `avance`, `mensajes`, `mensajes/leidos`, `ubicacion-piloto`, `pago-confirmado` (POST), `calificaciones` (POST), `inicio` y `cierre` (vía `/solicitudes/{id}/{tipo}`), `evidencias`; `GET /solicitudes-abiertas`, `/{id}` y `/resumen-zonas`; `/ofertas` con `aceptar`, `contraofertar`, `rechazar`, `retirar`; `PUT /ubicacion`; `GET /reputacion`; `GET /referencias/precio`; `GET /calificaciones/recibidas`; `GET /notificaciones`, `/{id}/leida`, `/preferencias`; `POST/DELETE /dispositivos`; `GET /sync/catalogo` | Nada que retirar |
| V | `GET /solicitudes/{id}/estimacion`, `GET/POST /solicitudes/{id}/calificar-cliente`, `POST /calificaciones/{id}/reportar`, `POST /solicitudes/{id}/cancelar-proveedor`, `GET /solicitudes/{id}/disputa`, `POST /solicitudes/{id}/disputas` (la app llama la ruta en `problema_red`; ver nota), `GET /disputas`, `GET /disputas/{id}`, `POST /disputas/{id}/mensajes`, `/retirar`, `/apelar`, `GET /ofertas/{id}`, `GET /terrenos/{id}`, `PUT /terrenos/{id}`, `GET /solicitudes/{id}/pago-confirmado`, `GET /solicitudes/{id}/ofertas`, `GET /evidencias/{id}/archivo`, `GET /drones/fotos/{foto}/archivo`, `GET/POST/PUT /proveedores/pilotos`, `/proveedores/equipos`, `/proveedores/documentos`, `PUT /proveedores/drones/{id}`, `POST .../reenviar`, `POST .../documentos` | Son del producto (calificación del proveedor al cliente, disputas, alta de flota). La app los traerá; no se retiran |
| O | `POST /trabajos/{uuid}/acta`, `POST /actas/{uuid}/firmar`, `GET /actas/{uuid}/pdf`, `GET /reportes/lote/{trabajo}` | Sin consumidor ni función (B1, B2) |
| O (con consumidor legado) | `POST /sync`, `POST /evidencias` | La app los llama por código del modelo anterior (hallazgo 2) |
| O | `GET/POST /proveedores/auxiliares`, `POST /proveedores/auxiliares/{id}/reenviar` | Si D1 = retirar (B12) |
| Duplicada | `GET /dispositivos`, `GET /dispositivos/{id}` | B18 |
| Pendiente de implementar | `POST /incidencias`, `POST /emergencias` (§10 de la especificación) | Ver `estado-api.md`; no son limpieza, pero deciden si `ope_incidencias` se conserva |

Al retirar rutas: regenerar el contrato con `composer openapi`, quitar los ejemplos de `docs/api/ejemplos/` (`operaciones-evidencia.json`, `proveedores-auxiliares.json`, `sync-*.json`) y confirmar que `RutasDelContratoTest` y `EjemplosDeApiTest` quedan verdes. La app debe estar avisada: cada ruta retirada se anuncia en el repo `agrocom-field` antes de borrarla (el servidor ya no publica una versión mínima).

## 6. Roles y permisos del auxiliar

Estado: hecho, ver la sección 11 (el rol, los permisos `proveedores.auxiliar.*` y las referencias de código y lang se eliminaron; el seeder da de baja el rol).

| # | Ítem | Dónde | Forma |
|---|---|---|---|
| F1 | Rol `auxiliar` y `PERMISOS_AUXILIAR` | `SeguridadSeeder` (líneas 29 y 215; `asignar($roles['auxiliar'], ...)` en 481 y la mención en 485) | `migración` de datos: dar de baja lógica el rol y sus filas de `sec_user_role` y `sec_role_permission`; revocar tokens de `sec_token_dispositivo` con rol auxiliar. Medio: usuarios existentes pierden acceso |
| F2 | Permisos `proveedores.auxiliar.ver`, `.aprobar`, `.suspender`, `.dar_baja`, `.registrar` | `SeguridadSeeder` (84 a 99, 155, 191, 247 a 250, 286) y menú | Con P1 y B12 |
| F3 | Permisos `operaciones.acta.generar`, `operaciones.acta.firmar`, `operaciones.reporte.ver` | `SeguridadSeeder` (71 a 74, 205, 206) | Con B1 y B2 |
| F4 | `flota.equipo.ver` describe «piloto, dron y auxiliar» | línea 95 | Reescribir texto |
| F5 | `calificaciones.calificacion.calificar_proveedor`: «proveedor, piloto y auxiliar» | línea 125; `SujetoCalificacion`, `SujetosDelServicio`, `CalificacionesDelClienteController`, `config/calificaciones.php` | Decisión D1: el cliente deja de calificar al auxiliar |
| F6 | `Seguridad/Contratos/RegistroCuentas` documenta los roles `piloto` y `auxiliar` | docblock | Reescribir |
| F7 | `PermisosReservadosTest` incluye `auxiliar` entre los roles probados | `tests/Unit/PermisosReservadosTest.php` (línea 184) | Actualizar con la baja |
| F8 | `evidencias.autor_rol = 'auxiliar'` en `neg_evidencias` y `seg` | especificación §4.5; `Seguimiento` | Si D1 = retirar, el valor deja de ser válido |

## 7. Tests y documentación obsoletos

**Tests** (candidatos; cada uno se retira con su ítem):

| Test | Motivo | Con |
|---|---|---|
| `tests/Unit/MaquinaEstadosOperacionesTest.php`, `CondicionesLimitesTest.php`, `CierreTrabajoDadoDeBajaTest.php`, `SincronizacionDeTrabajoDeSolicitudTest.php` | Máquinas, límites y cierre de trabajo del modelo anterior | B5 a B7 |
| `tests/Unit/Personal/TransicionesEquipoTrabajoTest.php`, `ValidadorSolapamientoVigenciasTest.php` | Cuadrillas | B11 |
| `tests/Unit/PerfilPersonaTest.php` | `per_personas` | B15 |
| `tests/Unit/AptitudDeEquipoTest.php`, `Proveedores/VistaDelPilotoTest.php`, `Calificaciones/CalificacionesTest.php`, `tests/Soporte/ProveedoresFalsos.php` | Siguen vigentes; solo se les quitan las ramas de auxiliar | B12, B13 |
| `CatalogoServiciosAsignadosTest.php`, `NotificacionesReglasTest.php`, `NotificacionesEntregaTest.php`, `BitacoraAuditoriaTest.php`, `PulidoNavegacionPanelTest.php`, `TransicionesEstadoTest.php` | Mencionan tablas o clases del modelo anterior; revisar uno por uno | B3 a B7 |
| Replay del sync (parte del humo del selector) | **No se desactiva** sin cambiar CLAUDE.md: la invariante 9 de la app exige replay; mientras `POST /sync` exista, su prueba corre | B3 |

**Documentación y configuración de agentes** (presente, sin historial):

| Archivo | Qué sobra |
|---|---|
| `docs/especificacion/marketplace.md` | Auxiliar como rol, `prv_auxiliares`, calificación al auxiliar, `/proveedores/auxiliares`, menú `panel/auxiliares`, §10.3 «Sincronización del lote (piloto y auxiliar)», fila `auxiliar` de la tabla de apps; lo vigente es dos apps |
| `docs/especificacion/negociacion-contrato.md` | §4 «Compatibilidad» (tipos operativos que el servidor «sigue aceptando»); `Piloto y auxiliar del equipo pueden subir` |
| `docs/gestion/estado-api.md` | Habla de tres apps y de «Ejecución offline» por `POST /api/sync` y actas |
| `docs/gestion/demo-local.md` | Usuarios `diego.parada` y `esteban.choque` (auxiliares) y «equipo con auxiliar» |
| `docs/gestion/auditoria-calidad.md` | Lote L5 y la sección 2.1 pasan a «retirar el escritor» (B3); L12 enlaza con B8, B11 y B15 |
| `docs/glosario.md`, `docs/politicas/seguridad.md`, `docs/politicas/verificacion_proveedores.md`, ADR 0036 y 0032 | Auxiliar y sesión de vuelo |
| `docs/diseno/sistema_diseno_panel.md`, `guia_pantalla_panel.md`, `panel_homogeneo_pendientes.txt` | Catálogo con `captura-rc-card`, `tiered-progress-bar`, `distribution-bar` |
| `docs/api/ejemplos/` | `operaciones-evidencia`, `proveedores-auxiliares`, `sync-*` |
| `.claude/agents/*.md` (`producto-marketplace`, `negocio`, `modulos-roles`, `backend`) y `.claude/skills/marketplace-politicas`, `seguridad-roles`, `dominio-backend` | Texto con auxiliar y tres apps |
| `CLAUDE.md` | Invariantes 3 y 4 (sesión, validador ≠ piloto), 1 y 2 si se retira el lote; «apps de campo» |
| `docs/gestion/cola_tareas.md` | **No se toca**: es registro cronológico (`estado-api.md`) |

## 8. Cliente persona o empresa

El cliente puede ser una persona o una empresa. Una empresa tiene personas de contacto de referencia. Lo que existe hoy y lo que falta:

| Aspecto | Estado actual |
|---|---|
| Tabla | `sol_clientes` (`2026_11_23_110100`): `usuario_id` (único entre vivos), `tipo` (`persona` o `empresa`, `CHECK`), `razon_social`, `documento_fiscal` (único entre vivos, NIT), `telefono`, `estado` (`activo` o `suspendido`), `calificacion_promedio`, `calificaciones_conteo`, y las columnas de reputación y motivo de estado de `2026_11_26_100000` y `2026_12_02_100100` |
| Alta | `POST /api/clientes/registro` (`RegistrarClienteRequest`, `RegistrarCliente`): exige `tipo`, `nombre`, `username`, `password`, `telefono`; `razon_social` y `documento_fiscal` son obligatorios si `tipo = empresa`. Crea la cuenta con rol `cliente` y la ficha en una transacción |
| Contrato | `GET /api/clientes/mi` y el esquema `Cliente` devuelven `tipo`, `razon_social`, `documento_fiscal`, `telefono`, `estado`, calificación y avisos. **No devuelve contactos** |
| App | `cliente/acceso/data/acceso_red.dart` y `cliente/cuenta/data/cuenta_red.dart` leen `tipo` y muestran `razon_social` como empresa. No hay pantalla de contactos |
| Panel | `panel/clientes` (listado, ficha, suspender y reactivar) muestra `razon_social`; no hay sección de contactos ni alta |
| Contactos de referencia | **No existen hoy.** Estaban en `com_cliente_contactos` (`tipo`: dueño, agrónomo, encargado de propiedad, gerente general y otros; `nombre`, `telefono`, `email`, `observaciones`), junto con `com_clientes`. La migración `2026_11_24_200100_retira_contratos_campanias_y_ordenes` los movió a `respaldo_contratos_y_ordenes` y `2026_12_04_100100_elimina_esquemas_de_respaldo` eliminó ese esquema con `DROP SCHEMA ... CASCADE`: las filas no se recuperan. Esto **contradice** la intención de conservar la organización con contactos |
| Qué ve el piloto | Tras adjudicar, el catálogo entrega `contacto: { nombre: razon_social, telefono }` (`LecturaServiciosAsignadosEloquent`, línea 55): para una empresa el piloto recibe el nombre de la empresa y su teléfono, no una persona |

Lo que falta para que la solicitud de una empresa nombre su contacto de referencia:

1. Tabla nueva `sol_cliente_contactos` (`cliente_id`, `nombre`, `cargo` o `rol`, `telefono`, `email`, `es_principal`, `estado`), con índice único parcial del principal por cliente y las columnas de auditoría. No se restaura la tabla anterior: sus datos se perdieron.
2. Columna `contacto_id` (anulable) en `sol_solicitudes_servicio`, obligatoria cuando el cliente es empresa; el contacto se valida contra los del propio cliente (invariante 5).
3. Endpoints `GET/POST/PUT/DELETE /api/clientes/mi/contactos` y el campo `contacto_id` en `POST /api/solicitudes`; `Cliente` devuelve la lista de contactos y `GET /api/solicitudes/{id}` el contacto elegido.
4. El catálogo y el detalle para el piloto adjudicado entregan el nombre y el teléfono de la **persona** de contacto (regla §3.4: solo tras adjudicar).
5. Panel: sección «Contactos» en `panel/clientes/{id}` y alta (sección 9).
6. App `cliente`: pantalla de contactos y selector en el formulario de solicitud cuando `tipo = empresa`; `piloto`: mostrar el contacto.
7. Especificación: §4.1 y §10 del `marketplace.md` documentan los contactos; ADR nuevo.

Nada de esto se marca como obsoleto: `sol_clientes.tipo`, `razon_social`, `documento_fiscal`, las rutas `panel/clientes` y los permisos `solicitudes.cliente.*` son vigentes.

## 9. Refinamiento del panel

Pantallas actuales (rutas de `routes/web.php`, menú de `SecMenuSeeder`) y lo que aportaría cada mejora para operar la negociación.

| Pantalla | Estado | Qué falta | Valor |
|---|---|---|---|
| Solicitudes (`panel/solicitudes`, ficha con `_negociacion`, `_chat`, `_seguimiento`) | Listado, ficha con ofertas, chat, fotos y ubicación; cancelar con motivo; cerrar la negociación de un proveedor | Línea de tiempo de las **rondas** (número, autor, precio, vigencia, `es_definitiva`) en una sola vista; filtro por estado, zona y vencimiento; indicador de «vence pronto»; exportar | Alto |
| Ofertas y rondas | Dentro de la ficha de solicitud | Pantalla de ofertas global con las negociaciones abiertas, las que no tienen respuesta y las vencidas | Medio |
| Disputas (`panel/disputas`) | Listado, ficha, tomar, mensajes, resolver | Cola por antigüedad, plazos de apelación, monto ajustado visible antes de resolver | Medio |
| Calificaciones (`panel/calificaciones`) | Ocultar y restablecer | Cola de calificaciones reportadas primero | Bajo |
| Métricas (`panel/metricas`) | KPIs de mercado, calidad, oferta, ingreso y tabla de proveedores sobre vistas `vw_metricas_*` | Embudo solicitud, oferta, adjudicación, ejecución y cierre; tiempo hasta la primera oferta; tasa de contraoferta por ronda | Alto |
| Proveedores, pilotos, drones (`panel/proveedores`, `/pilotos`, `/drones-flota`, `/documentos`) | Listado, ficha, aprobar, rechazar, suspender y dar de baja con motivo; revisión de fotos y documentos | Cola única «pendientes de aprobación» con antigüedad y documentos por vencer; la revisión de fotos obligatorias para aprobar el dron (pendiente en `estado-api.md`) | Alto |
| Clientes (`panel/clientes`) | Listado, ficha, suspender y reactivar | Alta manual, contactos (sección 8), historial de solicitudes y calificaciones del cliente | Alto |
| Precios de referencia (`sol_precios_referencia`, `PrecioReferencia`) | Modelo y sembrador `PreciosReferenciaSeeder`; **sin pantalla** | CRUD por zona, macrorregión y tipo de aplicación | Medio |
| Parámetros de negociación (`config/negociacion.php`, `seguimiento.php`) | Solo por archivo de configuración: los grupos editables de `panel/configuracion` son `mapas`, `correo` e `integraciones` | Pantalla de vigencias, máximo de rondas y tolerancia de hectáreas | Medio |
| Zonas (`panel/zonas`) | CRUD completo | — | — |
| Avisos (`panel/avisos`), bitácora, permisos, usuarios | Vigentes | — | — |
| Versiones de las apps y Dispositivos | Retirados (las apps salen por las tiendas; el registro y la revocación de dispositivos del login siguen en la API) | — | — |

### Alta manual por el panel (clientes, pilotos, drones)

Estado actual:

- **No existe formulario de alta de cliente, proveedor, piloto ni dron en el panel.** Las rutas `panel/clientes`, `panel/proveedores`, `panel/pilotos` y `panel/drones-flota` son de lectura más acciones de estado (`resolver`, `suspender`, `reactivar`, `revisar`). No hay `crear` ni `store` en ellas.
- Lo más parecido es `panel/usuarios/crear` (`UsuariosController::create` y `store`, `CrearUsuarioRequest`, `AsignarRolesUsuario`): crea una cuenta `sec_user` y le asigna roles, pero **no crea la ficha de negocio** (`sol_clientes`, `prv_proveedores`, `prv_pilotos`, `flo_drones`), no pasa por la máquina de aprobación y exige o permite vincular `per_personas`. Un usuario creado así con el rol `cliente` o `piloto` no puede operar: la API busca la ficha por `usuario_id` (`ClienteDeLaCuenta`).
- El alta desde la app sí está completa: `RegistrarCliente` y `RegistrarProveedor` abren una transacción, piden la cuenta por el contrato `RegistroCuentas` (rol `cliente`, o `proveedor` más `piloto` si es independiente), crean la ficha, dejan el proveedor y el piloto en `pendiente` con `MaquinaEstadosAprobacion` y avisan al panel. Validan unicidad de `username` y `email` entre cuentas vivas, contraseña mínima de 8 con letras y números, teléfono con formato, NIT obligatorio para empresas y, para el proveedor, datos de la persona y del dron (§9 de `negociacion-contrato.md`). Son idempotentes por `uuid_cliente`.

Qué falta, para que quien llega a la oficina se registre con la misma aprobación:

1. Casos de uso reutilizables por el panel: `RegistrarCliente` y `RegistrarProveedor` ya reciben datos y no dependen de HTTP; el panel los llama con el `uuid_cliente` generado en el servidor y deja `origen = panel` en el historial (`prv_historial_estados` ya guarda `origen`). No se duplican reglas.
2. Controladores y vistas web: `Clientes/create`, `Proveedores/create` (con paso de piloto y dron) y `Pilotos/create` para sumar un piloto a un proveedor existente, con los mismos `FormRequest` o una variante que acepte «sin contraseña, enviar enlace de restablecimiento» (hoy la contraseña es obligatoria).
3. Permisos nuevos: `solicitudes.cliente.crear`, `proveedores.proveedor.crear`, `proveedores.piloto.crear`, `flota.dron.crear` (hoy solo existen `ver`, `aprobar`, `suspender` y `dar_baja`).
4. Aprobación: la misma máquina. El alta de oficina deja `pendiente`; **quien registra no aprueba el mismo registro** (separación de funciones, como la invariante 4), salvo decisión explícita del dueño.
5. Cliente empresa: formulario con razón social, NIT y contactos de referencia (sección 8, punto 1).
6. Documentos del proveedor y fotos del dron: `DocumentosController` y `FotosDeDronController` ya reciben archivos por la API; el panel necesita los mismos formularios de carga, con límite de 300 KB.
7. Bitácora: `plt_registrar_bitacora()` ya cubre las tablas del dominio; confirmar que el actor es el administrador y no la cuenta creada.
8. Pruebas: una por alta, para comprobar que el panel y la app dejan el mismo estado (cuenta, rol, ficha y estado `pendiente`).

## 10. Orden propuesto en PR pequeños

Cada PR es independiente y lleva la prueba que lo acota, no la suite completa.

| PR | Contenido | Ítems | Riesgo | Condición previa |
|---|---|---|---|---|
| 0 | Decisiones del dueño: D1 (auxiliar: retirar o conservar como dato), D2 (portal web del cliente), D3 (contactos de referencia: restaurar como `sol_cliente_contactos`), D4 (`ope_incidencias` para incidencias del cliente), D5 (`Producto` y `mez_productos`) | — | — | — |
| 1 | Docs y agentes en presente: especificación, `estado-api`, `demo-local`, glosario, `.claude/` | sección 7 | Bajo | — |
| 2 | Quitar de la demo auxiliares, cuadrillas y personas de operarios | seeders `Demo` | Bajo | — |
| 3 | Retirar rutas y código de actas y reportes de lote, con permisos y ejemplos | B1, B2, F3, P8 | Bajo | `composer openapi` |
| 4 | Retirar componentes y CSS sin uso y la dependencia Livewire | P3 a P6 | Bajo | — |
| 5 | Retirar API y panel del auxiliar, permisos y rol | B12, P1, F1, F2, F4 a F8 | Medio | D1 |
| 6 | Cliente-empresa con contactos (funcionalidad nueva, migración aditiva) | sección 8 | Medio | D3 |
| 7 | Alta manual de cliente y proveedor desde el panel | sección 9 | Medio | PR 6 |
| 8 | Retirar `POST /api/sync`, `POST /api/evidencias`, el escritor del sync y los módulos `Operaciones` (código), `Mezclas`, `Mantenimiento` | B3 a B10, B16 | **Alto, REVISIÓN HUMANA** | App con versión mínima sin `SyncEngine` |
| 9 | Retirar `Personal` (menos `PerPersona`), `config/operaciones.php` y `lang` | B11, P9 | Medio | PR 8 |
| 10 | Migración con respaldo y `down()`: tablas de T1 a T9, triggers de T12 y columnas de T11 | sección 3 | **Alto, REVISIÓN HUMANA** (esquema con `DROP`) | `SELECT count(*)` en cada entorno; respaldo `pg_dump` guardado |
| 11 | Unificar dron, equipo y persona | B8, B13, B15, T10 | **Alto** | PR 10 |
| 12 | Retirar `GET /dispositivos` duplicado, decidir portal del cliente | B18, B19 | Bajo | D2 |
| 13 | Refinamiento del panel: rondas, embudo de métricas, cola de aprobaciones, precios de referencia | sección 9 | Bajo | — |

Zonas de revisión humana de este plan: `Negociar::aceptar`, `Participantes`, `UbicacionDelPiloto` (no se tocan en la limpieza, solo se parten en L7), `EscrituraSincronizacionEloquent` (se retira en el PR 8), el esquema y toda migración con `DROP` (PR 10 y 11), y `PerPersona` con la identidad del token de operario (PR 11).

## 11. Estado de la ejecución

Decisiones del dueño del 26/11/2026: se elimina todo lo que el modelo de negociación no usa, también el código; el esquema no se toca aquí, se reconsolida al final con `bin/consolidar-migraciones` (beta solo tiene datos de prueba).

| Ítem | Estado | Notas |
|---|---|---|
| Distribución: dominio `Distribucion`, página «Versiones de las apps» (`panel.versiones-apk.*`), `GET /api/version`, permiso `distribucion.version.autorizar`, siembra demo, ejemplo y contrato | **Hecho** | La tabla `dis_versiones_apk` queda en el esquema sin uso. El servidor ya no puede forzar una actualización: la app deja de consultar la versión mínima (PR en `agrocom-field`). |
| Dispositivos del panel: `panel.dispositivos.*`, controlador, vista, CSS, lang, permisos `seguridad.dispositivo.*` y tarjeta de dispositivos en la ficha de usuario | **Hecho** | Siguen el token por dispositivo del login y el logout (`uuid_dispositivo`, `RevocarTokenDispositivo`) y `GET /api/dispositivos` (sin consumidor en la app; se decide con B18). |
| Equipos del panel: `panel.equipos-flota.*`, controlador, vista, lang y permiso `flota.equipo.ver` | **Hecho** | Siguen `flo_equipos`, `/api/proveedores/equipos` y el equipo de la solicitud y las ofertas. |
| Auxiliar: página `panel.auxiliares.*`, sección «Auxiliares» de la ficha del proveedor, `GET/POST /api/proveedores/auxiliares` (y `reenviar`), modelo `Auxiliar`, `RegistrarAuxiliar`, `TipoSujeto::Auxiliar`, documentos requeridos y su configuración, calificación del auxiliar (la reputación pondera proveedor y piloto; el POST de calificaciones admite 1 o 2), `auxiliar_id` de la API de equipos (`/api/proveedores/equipos`), `AptitudDeEquipo`, rol `auxiliar` y permisos `proveedores.auxiliar.*` (el seeder da de baja el rol y sus asignaciones), pendiente «Auxiliares por aprobar» del tablero, lang, ejemplos | **Hecho** | Queda a propósito, sin uso: la tabla `prv_auxiliares`, `flo_equipos.auxiliar_id`, los CHECK con `auxiliar` de `prv_documentos`, `prv_historial_estados`, `cal_calificaciones` y `ntf_notificaciones`, y el índice sobre `flo_equipos.auxiliar_id`: se reconsolidan al final. El reemplazo («integrantes del equipo del servicio») se define aparte. |
| Auxiliar en el modelo de campo anterior: `Operaciones` (`AperturaSesion.auxiliarId`, `ope_sesiones.auxiliar_id`), `Personal` (`RolEquipo::Auxiliar`, `RolOperativoPersona::Auxiliar`) y `Sincronizacion` (catálogo con personas de rol `auxiliar`; ejemplos `sync-catalogo-*`) | **Pendiente** | Se va con el retiro de esos módulos (filas 8 y 9 de la sección 10). |
