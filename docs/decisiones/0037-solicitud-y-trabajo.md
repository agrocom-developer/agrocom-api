# ADR 0037 — Solicitud de servicio y trabajo: retiro del modelo de contratos

**Estado:** aceptada · **Fecha:** 2026-11-24 · **Decide:** el dueño (autonomía delegada)

## Contexto

El ADR 0027 fija la solicitud de servicio como la unidad de negocio del marketplace y la especificación (`docs/especificacion/marketplace.md`, §4.2 y §5.1) define su tabla, su ciclo de estados y los endpoints del cliente. El ADR 0035 ya retiró la operación interna (finanzas, inventario, mantenimiento de flota) y el ADR 0036 creó `Solicitudes` con el cliente y las zonas. Falta que la ejecución del equipo cuelgue de una solicitud adjudicada y que desaparezca el modelo que lo ataba a contratos, campañas, órdenes de aplicación y órdenes de trabajo.

## Decisión

1. **`Solicitudes` aloja la solicitud de servicio** (`sol_solicitudes_servicio`), sus terrenos (`sol_terrenos`) y su historial inmutable (`sol_solicitud_historial`). Nace en `borrador` con el `uuid_cliente` que genera la app (idempotente: reintentar el mismo uuid devuelve la misma solicitud). Lleva el `precio_ha_propuesto` del cliente; el monto estimado se calcula con el precio acordado al adjudicar (ADR 0030, ADR 0034).
2. **Toda transición pasa por `MaquinaEstadosSolicitud`** (invariante 7): consulta `TransicionesSolicitud` (la tabla del §5.1 de la especificación: `borrador`, `abierta`, `con_ofertas`, `adjudicada`, `en_ejecucion`, `finalizada`, `calificada`, `disputada`, `expirada`, `cancelada`), escribe el estado y deja una fila de historial con autor, origen (`cliente`, `proveedor`, `administrador`, `sistema`) y motivo, todo en una transacción con la fila bloqueada. La negociación, el seguimiento y la disputa solo llaman a `transicionar()`.
3. **API del cliente** (`/api/terrenos`, `/api/solicitudes`): crear (idempotente), listar con `vista=activas|historial`, ver con su historial, publicar (queda `abierta`), cambiar el precio propuesto, renovar una `expirada` y cancelar con motivo. El alcance parte siempre del cliente de la cuenta (invariante 5): una solicitud o un terreno ajenos responden 404. Es la superficie online del cliente, sin outbox (ADR 0028). El permiso de la puerta es del rol del token (`solicitudes.terreno.gestionar`, `solicitudes.solicitud.gestionar`).
4. **La ejecución cuelga de una solicitud adjudicada.** Los registros del lote (`inicio`, `avance`, `cierre`, `mensaje`, `incidencia`) traen la `solicitud_id` del servicio adjudicado del catálogo y se rechazan si la solicitud no está `adjudicada` o `en_ejecucion` o si la cuenta no es del equipo. Reenviar el mismo `uuid_cliente` es `duplicado` aunque la solicitud ya haya avanzado (la comprobación va antes de mirar su estado; el `UNIQUE` parcial sigue siendo la garantía contra la carrera). El motor de sync y la idempotencia por `uuid_cliente` no cambian (ADR 0040).
5. **El seguimiento avanza la solicitud por un contrato** (`Solicitudes\Contratos\SolicitudParaSeguimiento`), con origen `sistema`: el registro `inicio` pasa la solicitud a `en_ejecucion`; el `cierre` con las evidencias mínimas subidas la pasa a `finalizada`, congela las hectáreas ejecutadas y el monto final y anuncia `SolicitudFinalizada`. Una solicitud que cambió de estado mientras el equipo trabajaba sin señal (por ejemplo, cancelada) no cambia y lo ya registrado se conserva.
6. **El catálogo de sync devuelve «servicios asignados».** `GET /api/sync/catalogo` entrega `personas`, `servicios_asignados` (solicitudes `adjudicada` y `en_ejecucion` de los equipos de flota en los que la cuenta es piloto o auxiliar vigente hoy, con sus lotes, el precio acordado y los datos de contacto), `servicios_retirados` (lo que el dispositivo ya no debe llevar: `cancelada`, `finalizada`, `abierta` por cancelación del proveedor) y `mensajes` recientes. El cursor tiene tres secciones (`personas`, `servicios_asignados`, `servicios_retirados`). Un servicio retirado se identifica por el `equipo_id` que conserva.
7. **Se retira el modelo de contratos**: contratos, campañas, siembra por etapas, facturas por contrato, órdenes de aplicación, órdenes de trabajo y estadías en hacienda, junto con la cartera comercial que los sostenía (clientes de cartera y sus contactos, propiedades, lotes, cultivos y geografía), el portal de avance, actas y reportes por contrato y las cuentas de portal atadas a un contrato (`sec_user.contrato_id`). Los módulos `Comercial`, `Campania` y `Portal` desaparecen; los permisos `comercial.*`, `campania.*`, `operaciones.orden.*`, `operaciones.estadia.*` y `seguridad.usuario.portal` se dan de baja en `SeguridadSeeder`, y los menús «Comercial» y «Financiero» salen de `SecMenuSeeder`. El cliente del marketplace se registra solo (ADR 0036) y su terreno es `sol_terrenos`.
8. **Panel del administrador**: `panel/solicitudes` (cola y seguimiento, las abiertas con ofertas primero) y la ficha con KPIs, datos del servicio y del terreno, negociaciones e historial; el rol con `solicitudes.solicitud.cancelar` cancela con motivo en un modal. `panel/trabajos` lista el avance por solicitud. El tablero resume las solicitudes por estado; el menú tiene «Solicitudes».
9. **Importes y hectáreas en decimal exacto** (invariante 6): los cálculos usan `Brick\Math\BigDecimal`; el precio máximo de sanidad, el precio de referencia por tipo de aplicación y la tolerancia de hectáreas ejecutadas son parámetros de `config/negociacion.php` y `config/seguimiento.php` (ADR 0016). La cancelación (ADR 0033) y el precio y pago (ADR 0034) no calculan penalidad ni comisión.

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
- **Marcar la solicitud `finalizada` sin el cierre del piloto**: una aplicación de varios días o con relevos quedaría cerrada con la mitad de la superficie; el cierre lo marca el piloto con las hectáreas ejecutadas.
- **Que la app de campo cree la solicitud al llegar al lote**: la solicitud nace del cliente y se adjudica por red; el lote solo ejecuta lo ya adjudicado (ADR 0028).

## Consecuencias

- El contrato de sincronización con la app de campo: los registros del lote traen `solicitud_id`; el catálogo trae `personas`, `servicios_asignados`, `servicios_retirados` y `mensajes`. El OpenAPI (`docs/api/openapi.yaml`) es el contrato y `agrocom-field` lo consume.
- La adjudicación fija `proveedor_id` y `equipo_id` con `MaquinaEstadosSolicitud::transicionar()` y conserva `equipo_id` al reabrir la solicitud por cancelación del proveedor, para que el catálogo pueda retirarla del dispositivo.
- Los límites de condiciones de vuelo son los del sistema (viento 17 km/h, temperatura 30 °C, humedad 90 %); fijarlos por tipo de aplicación es parámetro de una tarea posterior.
- Quedan sin pantalla los editores de mapa de lote y propiedad (`resources/js/organisms/lote-mapa-editor.js`, `propiedad-mapa-editor.js`) y el componente del mapa operativo del tablero; su retiro queda para una limpieza de recursos del panel.
- La revisión humana posterior a la integración cubre `MaquinaEstadosSolicitud`, `AvanceDeSolicitud`, el cambio en `EscrituraSincronizacionEloquent::abrirTrabajo()` y `cerrarTrabajo()`, el catálogo (`ObtenerCatalogoDesdeCursor`, `LecturaServiciosAsignadosEloquent`) y las dos migraciones.
