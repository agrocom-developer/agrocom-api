# ADR 0037 — Solicitud de servicio y trabajo: retiro del modelo de contratos

**Estado:** aceptada · **Fecha:** 2026-11-24 · **Decide:** el dueño (autonomía delegada)

## Contexto

El ADR 0027 fija la solicitud de servicio como la unidad de negocio del marketplace y la especificación (`docs/especificacion/marketplace.md`, §4.2 y §5.1) define su tabla, su ciclo de estados y los endpoints del cliente. El ADR 0035 ya retiró la operación interna (finanzas, inventario, mantenimiento de flota) y el ADR 0036 creó `Solicitudes` con el cliente y las zonas. Falta que el trabajo que ejecuta el equipo cuelgue de una solicitud aceptada y que desaparezca el modelo que lo ataba a contratos, campañas, órdenes de aplicación y órdenes de trabajo.

## Decisión

1. **`Solicitudes` aloja la solicitud de servicio** (`sol_solicitudes_servicio`), sus terrenos (`sol_terrenos`) y su historial inmutable (`sol_solicitud_historial`). Nace en `borrador` con el `uuid_cliente` que genera la app (idempotente: reintentar el mismo uuid devuelve la misma solicitud). La tarifa por hectárea y el monto estimado se congelan al publicar; el cliente los ve desglosados antes (`GET /solicitudes/{id}/estimacion`).
2. **Toda transición pasa por `MaquinaEstadosSolicitud`** (invariante 7): consulta `TransicionesSolicitud` (la tabla del §5.1), escribe el estado y deja una fila de historial con autor, origen (`cliente`, `proveedor`, `administrador`, `sistema`) y motivo, todo en una transacción con la fila bloqueada. Los pasos que la asignación y la disputa completarán (`en_asignacion`, `asignada`, `sin_proveedor`, `en_disputa`, `completada`) ya están en la tabla; esas tareas solo llaman a `transicionar()`.
3. **API del cliente** (`/api/terrenos`, `/api/solicitudes`): crear (idempotente), listar con `vista=activas|historial`, ver con su historial, estimar, publicar (también republica una `sin_proveedor`, con ventana ampliada y tarifa congelada), cancelar con motivo. El alcance parte siempre del cliente de la cuenta (invariante 5): una solicitud o un terreno ajenos responden 404. Es la superficie online del cliente, sin outbox (ADR 0028). El permiso de la puerta es del rol del token (`solicitudes.terreno.gestionar`, `solicitudes.solicitud.gestionar`).
4. **El trabajo nace de una solicitud aceptada.** `ope_trabajos.solicitud_id` ata el trabajo a su solicitud; el trabajo no lleva orden, lote ni número de aplicación. El registro `trabajo` del lote de sync trae la `solicitud_id` del servicio asignado del catálogo y se rechaza si la solicitud no tiene equipo (`asignada`, `en_camino`, `en_ejecucion`, `suspendida_por_clima`). Reenviar el mismo `uuid_cliente` es `duplicado` aunque la solicitud ya haya avanzado (la comprobación va antes de mirar su estado; el `UNIQUE` parcial sigue siendo la garantía contra la carrera). La sesión de vuelo, las pausas, las condiciones, las recargas, las incidencias, las evidencias, el acta y el reporte técnico siguen colgando del trabajo; el motor de sync, la idempotencia por `uuid_cliente` y la validación de sesiones no cambian.
5. **La ejecución avanza la solicitud por un contrato** (`Solicitudes\Contratos\SolicitudParaTrabajo`), con origen `sistema`: abrir el primer trabajo pasa la solicitud a `en_ejecucion` (recorriendo `asignada → en_camino` si el equipo no alcanzó a avisar que salió, y `suspendida_por_clima → asignada` al reprogramarse); cerrar un trabajo informa las hectáreas declaradas por los trabajos cerrados y, si cubren al menos `solicitudes.cobertura_minima_pct` (95 %) de lo pedido, la solicitud pasa a `ejecutada` y anuncia `SolicitudEjecutada`. Una cobertura menor la deja en ejecución a la espera de más trabajo. Una solicitud que cambió de estado mientras el equipo volaba sin señal (por ejemplo, cancelada) no cambia y lo ya volado se registra igual.
6. **El catálogo de sync devuelve «servicios asignados».** `GET /api/sync/catalogo` entrega `personas`, `servicios_asignados` (solicitudes en ejecución de los equipos de flota en los que la cuenta es piloto o auxiliar vigente hoy, con su terreno), `servicios_retirados` (lo que el dispositivo ya no debe llevar: `cancelada`, `reasignada`, `ejecutada`) y `limites` (los límites de condiciones de vuelo con los que el servidor valida `condiciones`). El cursor tiene tres secciones (`personas`, `servicios_asignados`, `servicios_retirados`). Un servicio retirado se identifica por el `equipo_id` que conserva: la asignación no debe vaciarlo al devolver la solicitud a `en_asignacion`.
7. **Se retira el modelo de contratos**: contratos, campañas, siembra por etapas, facturas por contrato, órdenes de aplicación, órdenes de trabajo y estadías en hacienda, junto con la cartera comercial que los sostenía (clientes de cartera y sus contactos, propiedades, lotes, cultivos y geografía), el portal de avance, actas y reportes por contrato y las cuentas de portal atadas a un contrato (`sec_user.contrato_id`). Los módulos `Comercial`, `Campania` y `Portal` desaparecen; los permisos `comercial.*`, `campania.*`, `operaciones.orden.*`, `operaciones.estadia.*` y `seguridad.usuario.portal` se dan de baja en `SeguridadSeeder`, y los menús «Comercial» y «Financiero» salen de `SecMenuSeeder`. El cliente del marketplace se registra solo (ADR 0036) y su terreno es `sol_terrenos`.
8. **Panel del administrador**: `panel/solicitudes` (cola y seguimiento, las que esperan proveedor primero) y la ficha con KPIs, datos del servicio y del terreno e historial; el rol con `solicitudes.solicitud.cancelar` cancela con motivo en un modal. `panel/trabajos` pasa a listar los trabajos por solicitud (estado de tablero, hectáreas, sesiones). El tablero cambia su resumen por solicitudes por estado; el menú gana «Solicitudes». Los trabajos nacen en la app: el panel ya no los crea ni los edita, solo los consulta y los da de baja mientras no estén validados.
9. **Importes y hectáreas en decimal exacto** (invariante 6): la estimación usa `Brick\Math\BigDecimal`; la tarifa por tipo de aplicación, los recargos (urgencia y acceso difícil en porcentaje, producto del proveedor por hectárea) y el porcentaje mínimo de cobertura son parámetros de `config/solicitudes.php` (ADR 0016). La penalidad de cancelación (ADR 0033) y la comisión (ADR 0034) las resuelven sus propias tareas.

## Migración y respaldo

Dos migraciones, ambas con respaldo dentro de la propia base y ninguna con `DROP`:

- `2026_11_24_200000_vincula_trabajos_a_solicitudes`: agrega `ope_trabajos.solicitud_id` (nullable: los trabajos previos no tienen solicitud) y quita `orden_id`, `lote_id`, `orden_trabajo_id` y `nro_aplicacion`. Antes copia esos cuatro valores a `respaldo_contratos_y_ordenes.ope_trabajos_vinculos`. `down()` los restaura con sus claves foráneas e índices.
- `2026_11_24_200100_retira_contratos_campanias_y_ordenes`: **mueve** 17 tablas al esquema `respaldo_contratos_y_ordenes` (`ALTER TABLE … SET SCHEMA`; conserva filas, índices, restricciones y secuencias) y las quita de `public`: `com_facturas`, `ope_estadias_hacienda`, `ope_orden_lotes`, `ope_ordenes_trabajo`, `ope_ordenes_aplicacion`, `com_lote_campania`, `com_contrato_lotes`, `com_contratos`, `cpn_campanias`, `com_cultivos`, `com_cliente_contactos`, `com_lotes`, `com_propiedades`, `com_clientes`, `com_municipios`, `com_provincias` y `com_departamentos`. Quita `sec_user.contrato_id` dejando los pares en `respaldo_contratos_y_ordenes.sec_user_contratos` y da de baja lógica los avisos de los tipos `contrato_creado` y `orden_trabajo_creada` (ids en `ntf_notificaciones_retiradas`). `down()` devuelve las tablas a `public`, restaura la columna y los avisos.
- No toca `plt_bitacoras`: el historial de auditoría de lo retirado sigue legible. Solo corre en PostgreSQL.

Verificada contra PostgreSQL 16 con datos de cada tabla retirada: `up`, `down` y `up` de nuevo, con el respaldo y la restauración comprobados.

### Retiro definitivo del respaldo

Mientras exista el esquema, las claves foráneas de las tablas archivadas hacia `per_*`, `sec_user` y `ope_*` impiden borrar físicamente esas filas. Cuando el dueño confirme que no necesita los datos:

1. Volcar el respaldo: `pg_dump --schema=respaldo_contratos_y_ordenes --format=custom --file=respaldo_contratos_y_ordenes.dump <base>`, y guardar el archivo fuera del servidor.
2. Comprobar el volcado: `pg_restore --list respaldo_contratos_y_ordenes.dump`.
3. Eliminar el esquema en una migración propia: `DROP SCHEMA respaldo_contratos_y_ordenes CASCADE`.

Antes de aplicar la migración en un entorno con datos reales conviene hacer el mismo volcado, aunque la migración sea reversible.

## Alternativas descartadas

- **`DROP TABLE` directo**: pierde los contratos, facturas y cartera sin vuelta atrás; el dueño puede necesitarlos para cierres ya presentados.
- **Conservar la cartera comercial (clientes, propiedades, lotes, cultivos) y retirar solo los contratos**: el cliente y el terreno del marketplace son `sol_clientes` y `sol_terrenos`; la cartera sin contratos no cumple función y obliga a mantener pantallas con filtros de campaña y de contrato que ya no existen.
- **Reusar el nombre `orden_id` para apuntar a la solicitud**: confunde dos conceptos con el mismo nombre; `solicitud_id` dice lo que es.
- **Marcar la solicitud `ejecutada` al primer trabajo cerrado**: una aplicación de varios días o con relevos quedaría cerrada con la mitad de la superficie; el umbral de cobertura la deja abierta hasta que alcance lo pedido.
- **Que la app de campo cree la solicitud al llegar al lote**: la solicitud nace del cliente y se asigna por red; el lote solo ejecuta lo ya aceptado (ADR 0028).

## Consecuencias

- El contrato de sincronización con la app de campo: `trabajo` trae `solicitud_id`; el catálogo trae `personas`, `servicios_asignados`, `servicios_retirados` y `limites`; los registros `estadia_entrada` y `estadia_salida` se rechazan como tipo desconocido. El OpenAPI (`docs/api/openapi.yaml`) es el contrato y `agrocom-field` lo consume.
- La tarea de asignación fija `proveedor_id` y `equipo_id` con `MaquinaEstadosSolicitud::transicionar()` y conserva `equipo_id` al devolver la solicitud a `en_asignacion`, para que el catálogo pueda retirarla del dispositivo.
- Los límites de condiciones de vuelo son los del sistema (viento 17 km/h, temperatura 30 °C, humedad 90 %); fijarlos por tipo de aplicación es parámetro de una tarea posterior.
- Quedan sin pantalla los editores de mapa de lote y propiedad (`resources/js/organisms/lote-mapa-editor.js`, `propiedad-mapa-editor.js`) y el componente del mapa operativo del tablero; su retiro queda para una limpieza de recursos del panel.
- La revisión humana posterior a la integración cubre `MaquinaEstadosSolicitud`, `AvanceDeSolicitud`, el cambio en `EscrituraSincronizacionEloquent::abrirTrabajo()` y `cerrarTrabajo()`, el catálogo (`ObtenerCatalogoDesdeCursor`, `LecturaServiciosAsignadosEloquent`) y las dos migraciones.
