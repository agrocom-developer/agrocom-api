# Auditoría de calidad: arquitectura, panel y base de datos

Informe de hallazgos para la siguiente etapa del proyecto: aplicar SOLID con archivos pequeños y contratos bien usados en las capas de dominio, y mover procesamiento de la API a la base de datos (funciones, procedimientos, triggers, vistas e índices). Es un informe: no cambia código. Cada lote de la sección 5 se ejecuta como un PR independiente.

Medición: `app/` tiene 804 archivos PHP y 44 pasan de 200 líneas. Los conteos de líneas son de `wc -l` sobre la rama `develop` del 24/11/2026. En los controladores de API, entre un cuarto y un tercio de las líneas son atributos OpenAPI (`#[OA\...]`), así que el conteo de lógica real es menor que el total.

Criterios de prioridad: **P1** afecta corrección, aislamiento o rendimiento y es barato de corregir; **P2** deuda estructural que frena cambios; **P3** limpieza.

## 1. Resumen y los cinco hallazgos principales

| # | Hallazgo | Prioridad | Lote |
|---|---|---|---|
| 1 | 89 claves foráneas sin índice en PostgreSQL (el motor no las indexa solo; 43 no son de autoría), incluidas las de las tablas calientes del marketplace | P1 | L1 |
| 2 | `Metricas` calcula en PHP, con `DB::table` sobre seis módulos ajenos, lo que una vista agregaría en la base | P1 | L2 |
| 3 | `EscrituraSincronizacionEloquent` (792 líneas) reúne la escritura de nueve tipos de registro del sync y atiende toda una interfaz gruesa | P1 | L5 |
| 4 | La base no tiene vistas, funciones ni triggers propios: invariantes 2, 3 y 7 y la bitácora dependen solo de PHP | P1 | L3, L4 |
| 5 | `UsuariosController` (607 líneas) mezcla presentación, consultas y reglas, y lee `per_personas` con `DB::table` desde otro módulo | P2 | L6 |

Lo que está bien y no necesita lote: los colores del panel cumplen el invariante 11 (la compuerta `TokensColorTest` no encuentra literales fuera de comentarios) y el texto de las vistas Blade está en `lang/es` (ver sección 4).

## 2. Violaciones SOLID y archivos grandes en `app/Dominios`

Archivos ordenados por líneas, con la responsabilidad que sobra. Se incluyen solo los que tienen más de una razón para cambiar.

### 2.1 Capa Infraestructura / escritura del sync

| Ruta | Líneas | Problema | Propuesta |
|---|---|---|---|
| `Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php` | 792 | SRP: abre trabajo y sesión, registra condiciones, incidencias, recepción de caldo, recargas, evidencias de equipo y cierra trabajo y sesión. Además traduce `QueryException` a resultado en tres métodos casi iguales (`resultadoDesdeExcepcion`, `resultadoIncidenciaDesdeExcepcion`, `resultadoEvidenciaEquipoDesdeExcepcion`) y recalcula hectáreas | Un escritor por tipo de registro (`EscritorTrabajo`, `EscritorSesion`, `EscritorCondiciones`...), una clase base `EscritorIdempotente` que concentre la traducción de la violación de `uuid_cliente` a `duplicado` y el cálculo de hectáreas en la base (ver L3) |
| `Operaciones/Contratos/EscrituraSincronizacion.php` | 90 | ISP: nueve métodos en un solo contrato; cada consumidor usa uno | Un contrato por registro (`EscribeTrabajo`, `EscribeSesion`...) y que `SincronizarLote` los reciba por un registro de manejadores |
| `Sincronizacion/Aplicacion/SincronizarLote.php` | 309 | OCP: un `match` por tipo y un método `aplicar*` por registro; agregar un tipo obliga a editar la clase | Mapa `tipo => ManejadorDeRegistro` inyectado; cada manejador valida y delega en su escritor |
| `Sincronizacion/Infraestructura/Http/Controllers/Api/SyncController.php` | 250 | 62 líneas son OpenAPI; el resto mezcla validación y armado de respuesta | `FormRequest` y recurso de respuesta |

Zona de revisión línea por línea (CLAUDE.md): este lote toca el motor de sync, por eso va con su propio PR y revisión manual posterior.

### 2.2 Capa Aplicacion

| Ruta | Líneas | Problema | Propuesta |
|---|---|---|---|
| `Negociacion/Aplicacion/Negociar.php` | 504 | SRP y regla del módulo («una clase = una acción»): seis métodos públicos (`ofertar`, `contraofertar`, `aceptar`, `rechazar`, `retirar`, `reabrirSiNoQuedan`) y 20 privados de cálculo de precio, distancia y destinatarios | Una clase por acción; mover precio y vencimiento a `Dominio/` (`PrecioDeOferta`, `VencimientoDeOferta`) y la distancia a un contrato de lectura |
| `Negociacion/Aplicacion/Seguimiento.php` | 326 | Seis acciones públicas (iniciar, avanzar, cerrar, subir evidencia, consultar, archivo) en una clase | Una clase por acción y un colaborador común `RegistrarAvance` |
| `Negociacion/Aplicacion/MercadoDeSolicitudes.php` | 276 | Mezcla listado, resumen por zona, detalle y filtrado por `bbox` en PHP después de cargar todas las abiertas | Tres casos de uso; filtro geográfico a la consulta (ver L3) |
| `Metricas/Aplicacion/CalcularMetricas.php` | 279 | Seis bloques de métricas (mercado, calidad, oferta, ingreso, por proveedor, reembolsos) en una clase, con `DB::table` sobre seis módulos | Reemplazar por vistas (L2); mientras tanto, una clase por bloque |
| `Seguridad/Aplicacion/AsignarPermisosRol.php` | 292 | Cuatro verificaciones de política (plataforma, anti-escalada, llave propia, huérfanos) más la escritura | Una política por regla en `Dominio/Politicas/` y el caso de uso solo orquesta |
| `Seguridad/Aplicacion/AsignarRolesUsuario.php` | 291 | Alta/edición de usuario, validación de unicidad, asignación de roles y autorización | Separar `CrearUsuario`, `ActualizarUsuario` y `AsignarRoles` |
| `Seguridad/Aplicacion/CatalogoDePermisos.php` | 281 | Consultas de permisos por rol más el armado del árbol de concesiones para la pantalla | Separar las consultas (`LecturaPermisos`) del armado del árbol (`ArbolDeConcesiones`) |
| `Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php` | 281 | Arma el PDF, calcula litros por hectárea, superficie no aplicada y productos de mezcla | Los cálculos pasan a `Dominio/` (puros y con prueba unitaria); la clase solo compone el contenido |

### 2.3 Capa Infraestructura / HTTP y modelos

| Ruta | Líneas | Problema | Propuesta |
|---|---|---|---|
| `Seguridad/Infraestructura/Http/Controllers/Web/UsuariosController.php` | 607 | SRP: 21 métodos privados que arman tarjetas, resuelven roles y personas, consultan con `DB::table` y deciden permisos del rol dueño. Lee `per_personas` de otro módulo | Constructores de vista (`TarjetaRoles`, `TarjetaPersona`...), las consultas a casos de uso y `LecturaPersonas` del contrato de `Personal` |
| `Negociacion/Infraestructura/Http/Controllers/Api/SeguimientoController.php` | 395 | Valida con `$request->validate()` dentro de cada acción y envuelve las respuestas con un `registro(callable)` propio | `FormRequest` por acción y un manejador de excepciones de dominio común |
| `Solicitudes/.../Api/SolicitudesController.php`, `.../TerrenosController.php`, `Negociacion/.../OfertasController.php`, `Disputas/.../DisputasController.php`, `Flota/.../Api/DronesController.php` | 363, 271, 326, 316, 269 | Controladores gruesos; 16 controladores validan inline (`validate`/`Validator::make`) en vez de `FormRequest` | `FormRequest` por acción; extraer el armado de respuesta a `Resource` |
| `Seguridad/Infraestructura/Eloquent/SecUser.php` | 330 | El modelo resuelve permisos efectivos (`tienePermiso`, `tienePermisoEnRol`, `idsDeRolesActivos`) | Un servicio `PermisosEfectivos` en `Aplicacion/` y el modelo solo expone relaciones |
| `Operaciones/Infraestructura/Eloquent/Trabajo.php` | 273 | El modelo calcula `cuadreCaldo()` y `estadoTablero()`, y define el scope de tablero | Regla pura en `Dominio/` y vista SQL para el estado agregado |
| `Seguridad/Infraestructura/SeguridadServiceProvider.php` | 232 | Registra bindings, rutas, composers y middleware de varias capas | Un proveedor por responsabilidad (rutas, bindings, vistas) |

### 2.4 Entidades duplicadas entre módulos

Hay tres representaciones de la misma cosa que conviene unificar. No se eliminaron porque el motor de sync escribe sobre ellas.

| Duplicación | Dónde | Propuesta |
|---|---|---|
| Dron: `ope_drones`, `man_drones` (ficha), `flo_drones` (con `ope_dron_id`) | `Operaciones`, `Mantenimiento`, `Flota` | Una sola tabla `flo_drones`; el sync referencia `flo_drones.id` y los ciclos de batería viven en `flo_baterias` |
| Equipo: `per_equipos_trabajo` (y sus integrantes, recursos y accesorios) frente a `flo_equipos` | `Personal`, `Flota` | `ope_trabajos.equipo_trabajo_id` pasa a `flo_equipos.id`; retirar `per_equipo_*` |
| Persona: `per_personas` frente a `prv_pilotos` y `prv_auxiliares` | `Personal`, `Proveedores` | Un único origen de la persona de campo; el catálogo de sync deja de leer `per_personas` |

Código sin entrada que se conservó a propósito (sin ruta ni controlador, pero parte de invariantes o usado por pruebas del motor de sync): `ValidarSesion`, `RechazarSesion`, `PoliticaValidacionSesion` y sus excepciones (invariantes 3 y 4), `AtenderAlerta` con `MaquinaEstadosAlerta`, y `EliminarTrabajo` con `TrabajoValidadoNoEliminable` (lo ejercita `CierreTrabajoDadoDeBajaTest`). Hay que decidir si se les da una pantalla del panel o se retiran junto con la unificación anterior.

### 2.5 Fronteras entre módulos

El invariante de módulos dice que se viaja por contratos. Lecturas directas de tablas ajenas encontradas con `DB::table`/`join`:

| Origen | Tabla ajena | Archivo |
|---|---|---|
| `Seguridad` | `per_` | `Seguridad/Infraestructura/Http/Controllers/Web/UsuariosController.php` |
| `Solicitudes` | `prv_` | `Solicitudes/Aplicacion/GestionarZonas.php`, `Solicitudes/Infraestructura/Http/Controllers/Web/ZonasController.php` |
| `Negociacion` | `sol_` | `Negociacion/Aplicacion/ProcesarNegociacion.php` |
| `Compartido` | `sec_` | `Compartido/Infraestructura/Eloquent/BitacoraObserver.php` |
| `Metricas` | `sol_`, `neg_`, `prv_`, `cal_`, `dsp_`, `flo_` | `Metricas/Aplicacion/CalcularMetricas.php`, `ArmarTableroDeMercado.php` |

Propuesta: contratos de lectura (`Lectura*`) para los cuatro primeros; para `Metricas`, vistas SQL de solo lectura (ADR 0012), que son la forma sancionada de cruzar módulos en lecturas.

## 3. Procesamiento que debe moverse a la base de datos

Estado actual: el esquema no tiene ninguna vista (`vw_*`), ningún trigger y ninguna función propia (solo las de extensiones). Candidatos concretos, con impacto:

| # | Candidato | Hoy | Propuesta | Impacto |
|---|---|---|---|---|
| D1 | Métricas del mercado y por proveedor | `CalcularMetricas::mercado()` trae filas a PHP y calcula con `groupBy`, `max` y medianas; hasta `LIMITE_FILAS` filas por consulta | Vistas `vw_metricas_mercado`, `vw_metricas_proveedor` con `COUNT ... FILTER`, `percentile_cont` y `date_trunc`; el caso de uso solo filtra por período | Alto: menos transferencia y una sola fuente de la cifra |
| D2 | Vencimiento de ofertas y solicitudes | `ProcesarNegociacion::ejecutar()` recorre las ofertas vencidas una por una (`each` con `DB::transaction` por oferta) | Función `neg_vencer_ofertas(ahora)` con `UPDATE ... RETURNING` por lote, que devuelve los ids para anunciar los eventos | Alto: de N transacciones a una |
| D3 | Filtro geográfico del mercado | `MercadoDeSolicitudes::filtrar()` carga todas las abiertas y descarta por `bbox` en PHP; la distancia se calcula en `Negociacion/Dominio/Distancia.php` | Columnas `centro_lat`/`centro_lon` en el terreno con índice compuesto, o función SQL `vw_solicitudes_abiertas` con la distancia al proveedor (función inmutable haversine); `bbox` como `WHERE` | Alto: hoy el costo crece con las abiertas totales |
| D4 | Idempotencia del sync | Cada escritor hace `INSERT` y captura `QueryException` para responder `duplicado` | Funciones `syn_registrar_<tipo>(...)` con `INSERT ... ON CONFLICT (uuid_cliente) DO NOTHING RETURNING` y resultado `creado`/`duplicado` | Alto, pero toca el motor de sync: revisión línea por línea |
| D5 | Hectáreas acumuladas del trabajo | `recalcularHectareasTrabajo()` y `hectareasCerradasDeLaSolicitud()` suman en PHP tras cada cierre | Vista `vw_hectareas_por_trabajo` o trigger que mantiene el acumulado; el `DECIMAL` queda calculado en la base y cuadra por construcción (invariante 6) | Medio |
| D6 | Bitácora de auditoría | `BitacoraObserver` (PHP) calcula antes/después en cada evento Eloquent; no registra los cambios hechos por SQL directo, y lee `sec_` para el actor | Trigger genérico `plt_registrar_bitacora()` con `to_jsonb(OLD)`/`to_jsonb(NEW)` por tabla de dominio; el actor llega con `SET LOCAL app.user_id` | Alto para el invariante 9: hoy un `DB::table()->update()` no deja rastro |
| D7 | Inmutabilidad de lo validado y transiciones | Invariantes 2 y 7 solo viven en PHP (`MaquinaEstados*`) | Trigger `BEFORE UPDATE` que rechaza cambios a una fila validada, y una tabla de transiciones permitidas consultada por un trigger de estado (defensa en profundidad; la máquina de PHP sigue siendo la fuente) | Alto: cierra la puerta a un `UPDATE` suelto |
| D8 | Búsqueda global con `unaccent(col) ILIKE` | `BusquedaTexto`/`BusquedaEloquent` aplican la función sobre la columna y sin índice, así que cada búsqueda recorre la tabla | Extensión `pg_trgm` y un índice GIN sobre `unaccent(lower(col))` (función `IMMUTABLE` envoltorio) en las columnas buscadas | Medio |
| D9 | Detalle de solicitud y bandejas | Varias consultas por solicitud (ofertas vigentes, negociaciones, lotes) | Vista `vw_solicitud_resumen` con las cuentas ya agregadas | Medio |

### 3.1 Tablas sin índice adecuado

PostgreSQL no crea índice sobre una clave foránea. Consulta sobre el esquema migrado a una base vacía: 89 FK sin índice que las cubra como prefijo, 46 de ellas `created_by`/`updated_by` (no se consultan por ahí y se dejan fuera). Las 43 operativas:

| Tabla.columna | Se usa en |
|---|---|
| `sol_solicitudes_servicio.proveedor_id`, `.terreno_id` | bandeja del proveedor, ficha y mercado |
| `sol_terrenos.zona_id`, `sol_precios_referencia.zona_id`, `prv_proveedores.zona_base_id` | mercado por zona y precio de referencia |
| `neg_ofertas.equipo_id`, `.oferta_anterior_id` | cadena de contraofertas |
| `neg_cancelaciones.solicitud_id`, `neg_avances.usuario_id`, `neg_evidencias.avance_id`, `.usuario_id`, `neg_mensajes.autor_usuario_id` | seguimiento y mensajes |
| `neg_pagos_confirmados.confirmado_por_usuario_id`, `sol_solicitud_historial.actor_usuario_id` | auditoría de actor |
| `ope_sesiones.dron_id`, `.auxiliar_id`, `.captura_rc_id`, `.validado_por`, `ope_alertas.sesion_id`, `ope_evidencias.subido_por` | sync y reporte técnico |
| `flo_equipos.auxiliar_id`, `flo_drones.aprobado_por`, `prv_documentos.subido_por`, `.revisado_por`, `prv_*.aprobado_por` | flota y aprobación |
| `cal_calificaciones.autor_usuario_id`, `dsp_disputas.abierta_por_usuario_id`, `dsp_mensajes.autor_usuario_id` | calificaciones y disputas |
| `sec_token_dispositivo.role_id`, `sec_user_preferencia.rol_preferido_id`, `.ultimo_rol_id`, `sec_vistas_como.rol_id` | sesión y vista como |

Faltan además índices de consulta:

- `plt_bitacoras`: solo tiene `(tabla, registro_id)` y `user_id`; el listado de bitácora filtra por fecha y acción. Proponer `(tabla, created_at)` y `(user_id, created_at)`.
- `ntf_notificaciones` y `neg_mensajes` ya tienen índices compuestos adecuados; no requieren cambio.
- Los índices parciales por `deleted_at IS NULL` existen en las claves únicas, pero no en los índices de consulta: con soft delete en todas las tablas, convienen índices parciales `WHERE deleted_at IS NULL` en las bandejas (`sol_solicitudes_servicio (estado, vence_en)`, `neg_ofertas (estado, vence_en)`).

## 4. Panel: colores y textos

- **Colores**: sin hallazgos. `tests/Unit/TokensColorTest.php` recorre CSS, JS y Blade de todos los módulos y no encuentra literales fuera de comentarios. Los dos usos de `style="..."` con color pasan una variable CSS (`--ag-color-swatch-fill`) o un token resuelto en el componente (`distribution-bar`).
- **Excepción real — PDF**: las plantillas `Operaciones/Infraestructura/Http/Views/pdf/acta.blade.php` y `reporte-tecnico.blade.php` usan colores con nombre (`black`, `dimgray`, `whitesmoke`) que la expresión de la compuerta no detecta, porque solo busca hex y funciones `rgb()`/`hsl()`. Un PDF no puede leer los tokens del tema (se renderiza fuera del navegador), así que lo correcto es una hoja de estilo propia del PDF con variables Sass/PHP de una sola fuente y extender la compuerta a nombres de color. Lote L8.
- **Textos**: los Blade del panel pasan sus textos por `__()` y las pruebas `RedaccionNeutraTest` y `EscapePropsBladeTest` lo vigilan. Quedan literales en español en los comandos de consola (`Proveedores/Infraestructura/Console/AsignarTitularPropioCommand.php`: dos mensajes) y las plantillas PDF, que se llenan de `lang`. Impacto bajo.
- **`lang/es`**: en `operaciones`, `personal` y `mantenimiento` quedan grupos que ningún código alcanza (por ejemplo `personal.equipos_trabajo`, que solo lo lee `TransicionesEquipoTrabajoTest`, y cientos de claves de `operaciones.trabajos`, `.pausas`, `.alertas` y `.sesiones_validacion`). Conviene una compuerta que falle si una clave no tiene uso, con lista de excepciones para las dinámicas.

## 5. Lotes ejecutables como PRs independientes

Orden recomendado: de menor riesgo y mayor retorno a mayor riesgo. Cada lote nombra qué prueba la acota, de acuerdo con la regla de verificar solo lo afectado.

| Lote | Prioridad | Contenido | Archivos / tablas | Riesgo | Migración |
|---|---|---|---|---|---|
| **L1** Índices | P1 | Un `CREATE INDEX` por cada FK operativa de la sección 3.1 y los dos índices de `plt_bitacoras`; índices parciales en las bandejas. Una migración `agrega_indices_de_consulta` con `down()` | `database/migrations/` | Bajo | Sí |
| **L2** Vistas de métricas (HECHO) | P1 | Once vistas `vw_metricas_*` (una fila por hecho: solicitudes, aceptaciones, negociaciones, finalizaciones, ofertas, calificaciones, disputas, resoluciones); `CalcularMetricas` queda delgado y reúne cinco bloques que leen por cuatro contratos `LecturaMetricas*` (implementados en `Infraestructura/Lectura`). Promedios y mediana de minutos siguen en `Razones` (PHP) sobre la columna `minutos` de la vista | `Metricas/`, nueva migración de vistas | Medio | Sí |
| **L3** Funciones de lote | P1 | `neg_vencer_ofertas()` (D2), vista de solicitudes abiertas con distancia y filtro `bbox` (D3), `vw_hectareas_por_trabajo` (D5) | `Negociacion/`, `Solicitudes/`, `Operaciones/` | Medio | Sí |
| **L4** Triggers de integridad | P1 | Bitácora genérica por trigger (D6) e inmutabilidad de lo validado (D7); luego retirar `BitacoraObserver` | `Compartido/`, migración | Alto: toca la auditoría. Hace falta prueba contra PostgreSQL con script transaccional | Sí |
| **L5** Partir el escritor del sync | P1 | Sección 2.1: un escritor por registro, contratos más pequeños, mapa de manejadores. Después, D4 (funciones `ON CONFLICT`) como PR aparte | `Operaciones/Infraestructura`, `Sincronizacion/Aplicacion` | Alto: motor de sync; revisión línea por línea y comportamiento idéntico (`uuid_cliente`, `duplicado`) | No (D4: sí) |
| **L6** `UsuariosController` | P2 | Constructores de tarjetas, consultas a casos de uso, uso de `LecturaPersonas` en lugar de `DB::table('per_personas')` | `Seguridad/Infraestructura/Http/Controllers/Web/UsuariosController.php` | Bajo | No |
| **L7** Una acción por clase en Negociación | P2 | `Negociar` y `Seguimiento` partidos en clases por acción; precio, vencimiento y distancia en `Dominio/` con pruebas unitarias | `Negociacion/Aplicacion`, `Negociacion/Dominio` | Medio | No |
| **L8** PDF y compuerta de color | P3 | Hoja de estilo única para los PDF; extender `TokensColorTest` a colores con nombre | `Operaciones/.../pdf/`, `tests/Unit/TokensColorTest.php` | Bajo | No |
| **L9** `FormRequest` y recursos | P2 | Los 16 controladores con validación inline pasan a `FormRequest`; respuestas a `Resource` | Controladores de `Api/` | Bajo | No |
| **L10** Políticas de seguridad | P2 | `AsignarPermisosRol`, `AsignarRolesUsuario`, `CatalogoDePermisos` y `SecUser::tienePermiso` partidos en políticas y `PermisosEfectivos` | `Seguridad/Aplicacion`, `Seguridad/Infraestructura/Eloquent/SecUser.php` | Medio: zona de permisos | No |
| **L11** Contratos entre módulos | P2 | Contratos `Lectura*` para las cuatro lecturas cruzadas de la sección 2.5 | `Solicitudes/`, `Negociacion/`, `Compartido/` | Bajo | No |
| **L12** Unificar dron, equipo y persona | P2 | Sección 2.4: `ope_drones`/`man_drones`/`flo_drones`, `per_equipos_trabajo`/`flo_equipos`, `per_personas`/`prv_*`; retirar `Personal` y `Mantenimiento` | `Operaciones`, `Personal`, `Mantenimiento`, `Flota`, `Proveedores` | Alto: toca el sync y varias tablas; tras L5 | Sí |
| **L13** Búsqueda con índice trigram | P3 | `pg_trgm` y GIN sobre `unaccent(lower(col))` (D8) | `Compartido/Infraestructura/Busqueda`, migración | Bajo | Sí |
| **L14** Compuerta de claves de `lang` sin uso | P3 | Prueba unitaria que falla si una clave no tiene uso | `tests/Unit/` | Bajo | No |

Dependencias: L12 después de L5; L4 antes de retirar `BitacoraObserver`; L2 y L3 son independientes entre sí y de L1.
