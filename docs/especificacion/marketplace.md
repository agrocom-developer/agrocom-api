# Especificación del marketplace de fumigación con drones

**Agrocom SRL · Documento oficial vigente · fuente de verdad funcional y técnica**

Agrocom es un marketplace de servicios de fumigación con drones. Un cliente solicita un servicio sobre su terreno; un proveedor (piloto con su dron y, si hace falta, su auxiliar) lo ejecuta; el cliente califica y el proveedor ve su reputación. Los drones cumplen el papel del vehículo. Agrocom capta a los clientes, es el nexo entre las partes y además es proveedor con su propia flota y sus propios pilotos. La empresa también vende drones y entrega a cada comprador trabajo asegurado a través de la plataforma.

Este documento define el producto. Las decisiones que lo sostienen están en `docs/decisiones/` (ADR 0027 a 0038) y las reglas de operación en `docs/politicas/`. Cuando un valor numérico figura aquí como «parámetro», vive en la configuración del sistema (ADR 0016) y un administrador puede ajustarlo sin desplegar.

## 1. Principios

1. **Solicitud por solicitud.** Toda relación comercial es una solicitud de servicio.
2. **Propios primero.** La asignación ofrece el trabajo primero a los pilotos y drones propios de Agrocom y solo después a terceros (ADR 0030).
3. **Confianza verificable.** Ningún proveedor opera sin aprobación de un administrador; ningún dron vuela sin aprobación (ADR 0032).
4. **Offline en el lote, online en el resto.** El lote no tiene señal, así que lo que ocurre durante la ejecución (sesión de vuelo, mezcla, evidencias) se captura sin conexión y se sincroniza después. Todo lo demás (pedir, ofertar, aceptar, calificar) requiere red (ADR 0028).
5. **Un solo rol escritor por registro.** Cada registro lo escribe un solo actor; las correcciones son registros nuevos con motivo y autor.
6. **Decimales exactos.** Hectáreas, litros y montos se manejan como decimal exacto, nunca como punto flotante.
7. **Todo queda auditado.** Cada transición de estado deja una fila de historial con autor, motivo y momento (ADR 0007).

## 2. Actores y roles

| Actor | Descripción | Superficie |
|---|---|---|
| **Cliente** | Persona o empresa que solicita el servicio y califica. | App móvil `cliente` (online) y web |
| **Piloto** | Opera el dron. Puede ser empleado de Agrocom, independiente o piloto de un proveedor tercero. | App móvil `piloto` (offline en el lote) |
| **Auxiliar** | Prepara la mezcla y asiste en el lote. Lo registra el piloto o el proveedor; lo aprueba un administrador. | App móvil `auxiliar` (offline en el lote) |
| **Proveedor** | Entidad que presta el servicio: Agrocom (propio), un piloto independiente o una empresa/dueño de drones con sus pilotos (tercero). | Web y app |
| **Dueño de dron** | Quien posee el dron. Puede ser Agrocom, el piloto independiente o un tercero que lo pone a trabajar con pilotos propios o ajenos. | Web y app |
| **Administrador** | Aprueba proveedores, pilotos, auxiliares y drones; modera calificaciones; resuelve disputas; configura parámetros. | Web |
| **Soporte** | Atiende incidencias, emergencias y disputas. Es un rol del panel. | Web |

Un usuario puede tener varios roles (ADR 0004). Las acciones se autorizan por permiso del rol activo. Un administrador no valida ni modera lo que él mismo ejecutó o calificó.

## 3. Flujo de punta a punta

1. El cliente registra sus **terrenos** (lotes con geometría y hectáreas) una sola vez.
2. El cliente crea una **solicitud de servicio**: terreno, hectáreas, tipo de aplicación, producto o caldo indicado, ventana de fechas, observaciones. El sistema calcula una **tarifa estimada** (hectáreas por tarifa por hectárea, más recargos) y la muestra antes de confirmar.
3. La solicitud pasa a `publicada` y el motor de asignación genera **ofertas** (ADR 0030): primero a propios, luego a terceros por zona, radio y calificación.
4. Un proveedor **acepta** (requiere red). La solicitud queda `asignada` y se forma el **equipo** (piloto, dron, auxiliar opcional).
5. El equipo viaja al lote y ejecuta. En el lote trabaja sin señal: abre la **sesión de vuelo**, registra mezclas, condiciones y evidencias, y cierra la sesión. Todo se encola y se sincroniza al recuperar conexión.
6. Al sincronizar, el servidor valida las hectáreas aplicadas, las evidencias y las condiciones. El cliente recibe el **acta de conformidad** para firmarla en su app.
7. El cliente confirma o abre una **disputa** dentro del plazo. Si confirma, la solicitud pasa a `completada` y se liquida la comisión (ADR 0034).
8. El cliente califica al proveedor y el proveedor ve la calificación. El proveedor califica al cliente de forma privada para uso de moderación (ver `docs/politicas/calificacion.md`).

## 4. Modelo de datos

Convenciones: dominio en español, prefijo de tabla por módulo (ADR 0011), borrado lógico (ADR 0007), importes y hectáreas en `DECIMAL`. Cada módulo escribe solo sus tablas y se comunica con los demás por contratos y eventos (ADR 0003). Prefijos nuevos: `sol_` (solicitudes), `prv_` (proveedores), `flo_` (flota), `asg_` (asignación), `cal_` (calificaciones), `dsp_` (disputas), `ope_` (ejecución en el lote).

### 4.1 Clientes y terrenos

- **`sol_clientes`**: `id`, `usuario_id`, `tipo` (`persona` | `empresa`), `razon_social`, `documento_fiscal`, `telefono`, `estado` (`activo` | `suspendido`), `calificacion_promedio` (decimal 3,2), `calificaciones_conteo`.
- **`sol_terrenos`**: `id`, `cliente_id`, `nombre`, `geometria` (polígono GeoJSON), `hectareas` (decimal 12,4), `cultivo`, `referencias_acceso`, `colindantes_sensibles` (texto: apiarios, cultivos orgánicos, viviendas), `zona_id`.
- **`sol_zonas`**: `id`, `nombre`, `centroide`, `radio_km`. Alimentan el escalado de asignación.

### 4.2 Solicitud de servicio

**`sol_solicitudes_servicio`**

| Columna | Tipo | Nota |
|---|---|---|
| `id`, `uuid_cliente` | PK, uuid único | El `uuid_cliente` nace en el dispositivo y hace idempotente la creación |
| `cliente_id`, `terreno_id` | FK | |
| `tipo_aplicacion` | enum | `herbicida`, `insecticida`, `fungicida`, `fertilizante_foliar`, `desecante`, `otro` |
| `hectareas_solicitadas` | decimal 12,4 | |
| `producto_indicado` | texto | Producto o caldo que el cliente o su agrónomo indican |
| `aporta_producto` | enum | `cliente` | `proveedor` |
| `ventana_desde`, `ventana_hasta` | fecha | |
| `urgente` | bool | Activa recargo y oferta simultánea |
| `tarifa_hectarea` | decimal 14,4 | Congelada al publicar |
| `monto_estimado`, `monto_final` | decimal 14,2 | |
| `comision_porcentaje`, `comision_monto` | decimal | Se congelan al completar |
| `estado` | enum | Ver §5.1 |
| `proveedor_id`, `equipo_id` | FK nulas | Se fijan al aceptar |
| `motivo_cancelacion`, `cancelada_por` | nulos | |

**`sol_solicitud_historial`**: `solicitud_id`, `estado_anterior`, `estado_nuevo`, `actor_usuario_id`, `motivo`, `creado_en`. Solo se inserta.

### 4.3 Proveedores y personas

- **`prv_proveedores`**: `id`, `usuario_titular_id`, `tipo` (`propio` | `independiente` | `tercero`), `razon_social`, `documento_fiscal`, `zona_base_id`, `radio_operacion_km`, `estado` (§5.2), `calificacion_promedio`, `calificaciones_conteo`, `tasa_aceptacion`, `tasa_cancelacion`, `aprobado_por`, `aprobado_en`. Agrocom existe como un proveedor de tipo `propio`.
- **`prv_pilotos`**: `id`, `proveedor_id`, `usuario_id`, `licencia_numero`, `licencia_vence`, `categoria_habilitacion`, `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`).
- **`prv_auxiliares`**: `id`, `proveedor_id`, `usuario_id`, `registrado_por_piloto_id`, `estado` (igual que el piloto). Un piloto o el proveedor registra a su auxiliar y un administrador lo aprueba.
- **`prv_documentos`**: `id`, `sujeto_tipo` (`proveedor` | `piloto` | `auxiliar` | `dron`), `sujeto_id`, `tipo` (`identidad`, `licencia_piloto`, `habilitacion_dron`, `seguro_responsabilidad`, `registro_dron`, `certificado_fumigacion`, `antecedentes`, `otro`), `archivo_ref`, `vence_en`, `estado` (`en_revision` | `aprobado` | `rechazado` | `vencido`), `revisado_por`, `motivo_rechazo`. Ruta de archivo según ADR 0026.

### 4.4 Flota

- **`flo_drones`**: `id`, `dueno_proveedor_id` (el dueño), `operador_proveedor_id` (quien lo pone a trabajar, por defecto el mismo), `marca`, `modelo`, `numero_serie` (único), `capacidad_tanque_l` (decimal), `ancho_pasada_m` (decimal), `ciclos_bateria` (entero; único dato de mantenimiento que se conserva), `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`), `origen` (`flota_agrocom` | `vendido_por_agrocom` | `externo`).
- **`flo_equipos`**: `id`, `proveedor_id`, `piloto_id`, `dron_id`, `auxiliar_id` (nulo), `vigente_desde`, `vigente_hasta`. Un equipo es la combinación operativa que ejecuta una solicitud. Un piloto y un dron no pueden estar en dos equipos vigentes a la vez.
- **`flo_disponibilidad`**: `equipo_id`, `en_linea` (bool), `ubicacion_ultima`, `actualizado_en`. El proveedor se pone «en línea» desde la app para recibir ofertas.

### 4.5 Asignación

- **`asg_ofertas`**: `id`, `solicitud_id`, `proveedor_id`, `ronda` (entero), `grupo` (`propio` | `tercero`), `estado` (§5.3), `ofrecida_en`, `vence_en`, `respondida_en`, `motivo_rechazo`, `pago_estimado_proveedor` (decimal), `distancia_km`.
- **`asg_rondas`**: `solicitud_id`, `ronda`, `grupo`, `radio_km`, `proveedores_ofrecidos`, `iniciada_en`, `cerrada_en`.

### 4.6 Ejecución en el lote (offline)

La ejecución en el lote es la única parte offline; sus tablas llevan `uuid_cliente`, `secuencia` local y `dispositivo_id`.

- **`ope_sesiones_vuelo`**: `id`, `uuid_cliente`, `solicitud_id`, `equipo_id`, `piloto_id`, `dron_id`, `estado` (§5.4), `inicio_en`, `fin_en`, `hectareas_aplicadas` (decimal 12,4), `litros_aplicados` (decimal 12,3), `ciclos_bateria_consumidos`, `condiciones` (viento m/s, temperatura °C, humedad %, probabilidad de lluvia), `condiciones_forzadas` (bool con motivo), `datos_rc` (parámetros del control remoto: altura, velocidad, ancho de pasada, caudal), `secuencia`.
- **`ope_mezclas`**: `id`, `uuid_cliente`, `sesion_id`, `auxiliar_id`, `receta` (lista ordenada de productos con dosis), `volumen_l` (decimal), `hectareas_cubiertas` (decimal), `secuencia`.
- **`ope_incidencias`**: `id`, `uuid_cliente`, `solicitud_id`, `sesion_id` (nulo), `reportada_por_usuario_id`, `tipo` (§9.3), `severidad` (`informativa` | `grave` | `emergencia`), `descripcion`, `ubicacion`, `estado` (`abierta` | `en_atencion` | `cerrada`).
- **`ope_evidencias`**: `id`, `uuid_cliente`, `sesion_id` o `incidencia_id` o `disputa_id`, `tipo` (`foto_antes`, `foto_despues`, `foto_mezcla`, `foto_condiciones`, `foto_incidencia`, `registro_vuelo`, `firma_acta`), `archivo_ref`, `bytes`, `subida_en`. Imagen comprimida a menos de 300 KB antes de subir; cola de subida separada de los registros livianos.
- **`ope_actas`**: `id`, `solicitud_id`, `hectareas_confirmadas`, `texto_resumen`, `firmada_por_cliente_en`, `estado` (`pendiente` | `firmada` | `observada`).

### 4.7 Calificaciones

**`cal_calificaciones`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `autor_rol` (`cliente` | `proveedor`), `sujeto_tipo` (`proveedor` | `piloto` | `auxiliar` | `cliente`), `sujeto_id`, `estrellas` (entero 1 a 5), `etiquetas` (lista de un catálogo cerrado), `comentario`, `estado` (`visible` | `en_moderacion` | `oculta`), `moderada_por`, `motivo_moderacion`. Única por `(solicitud_id, autor_usuario_id, sujeto_tipo, sujeto_id)`. La escribe un solo actor y nunca se edita: moderar cambia `estado`, no el contenido.

### 4.8 Disputas

- **`dsp_disputas`**: `id`, `solicitud_id`, `abierta_por_rol` (`cliente` | `proveedor`), `motivo` (§8.4), `detalle`, `monto_en_disputa`, `estado` (§5.5), `resolucion` (`a_favor_cliente` | `a_favor_proveedor` | `parcial` | `sin_lugar`), `monto_reembolso`, `resuelta_por`, `resuelta_en`.
- **`dsp_mensajes`**: `disputa_id`, `autor_usuario_id`, `texto`, `creado_en`.

### 4.9 Transversales

- **`ntf_notificaciones`** (ADR 0025): un aviso por cuenta destinataria.
- **`cfg_parametros`** (ADR 0016): parámetros de §11.
- **`sec_*`**: usuarios, roles y permisos (ADR 0004).
- **`aud_bitacora`**: auditoría de cambios (ADR 0007).

## 5. Máquinas de estado

### 5.1 Solicitud de servicio

`borrador` → `publicada` → `en_asignacion` → `asignada` → `en_camino` → `en_ejecucion` → `ejecutada` → `completada`.

| Estado | Significado | Transiciones |
|---|---|---|
| `borrador` | El cliente la arma. | `publicada`, `cancelada` |
| `publicada` | Con tarifa congelada; el motor la toma. | `en_asignacion`, `cancelada` |
| `en_asignacion` | Hay ofertas vivas. | `asignada`, `sin_proveedor`, `cancelada` |
| `sin_proveedor` | Se agotaron las rondas. El cliente puede ampliar la ventana, subir la tarifa o cancelar. | `publicada`, `cancelada` |
| `asignada` | Un proveedor aceptó y fijó el equipo. | `en_camino`, `en_asignacion` (el proveedor cancela), `cancelada` |
| `en_camino` | El equipo salió hacia el lote. | `en_ejecucion`, `en_asignacion`, `cancelada` |
| `en_ejecucion` | Hay una sesión de vuelo abierta. | `ejecutada`, `suspendida_por_clima` |
| `suspendida_por_clima` | Condiciones fuera de límite; se reprograma sin penalidad. | `asignada`, `cancelada` |
| `ejecutada` | Sesión cerrada y sincronizada; falta la conformidad. | `completada`, `en_disputa` |
| `en_disputa` | Hay una disputa abierta. | `completada`, `cancelada` (con reembolso total) |
| `completada` | Conforme (explícita o tácita), liquidada y calificable. Terminal. | — |
| `cancelada` | Terminal; guarda quién y por qué. | — |

`ejecutada` pasa a `completada` por firma del acta o por vencimiento del plazo de conformidad (parámetro, 72 horas por defecto) si no hay observación.

### 5.2 Proveedor, piloto, auxiliar y dron

`pendiente` → `aprobado` ⇄ `suspendido`; `pendiente` → `rechazado` (el solicitante puede reenviar); cualquiera → `baja`. Solo un administrador aprueba, suspende o da de baja. Un proveedor `aprobado` no recibe ofertas si no tiene al menos un equipo con piloto y dron aprobados y documentos vigentes. Un documento vencido suspende de forma automática al sujeto hasta que se cargue uno vigente.

### 5.3 Oferta

`ofrecida` → `aceptada` | `rechazada` | `vencida` | `retirada`. `retirada` ocurre cuando otro proveedor aceptó antes o el cliente canceló. Solo una oferta por solicitud llega a `aceptada`; la aceptación es atómica sobre la solicitud (bloqueo de fila) y el segundo intento recibe conflicto.

### 5.4 Sesión de vuelo

`abierta` → `en_pausa` ⇄ `abierta` → `cerrada` → `confirmada` (el servidor validó hectáreas, evidencias y condiciones) | `observada` (el servidor encontró inconsistencias). La sesión la escribe el piloto; la mezcla, el auxiliar. La sesión puede quedar `confirmada` con evidencias todavía subiendo, pero `ejecutada` en la solicitud exige las evidencias obligatorias ya subidas.

### 5.5 Disputa

`abierta` → `en_revision` → `resuelta`; `abierta` → `retirada` (por quien la abrió). Una sola disputa abierta por solicitud.

### 5.6 Incidencia

`abierta` → `en_atencion` → `cerrada`. Una incidencia de severidad `emergencia` notifica a soporte en el acto.

## 6. Reglas de asignación (propios, luego terceros)

Detalle de la decisión en ADR 0030. Resumen:

1. **Elegibilidad**: proveedor `aprobado`, equipo en línea o con disponibilidad declarada para la ventana, dron y piloto aprobados y con documentos vigentes, capacidad del dron compatible con el trabajo, zona dentro del radio de operación y sin conflicto de agenda.
2. **Ronda 1, propios**: se ofrece a los proveedores propios elegibles, del más cercano al más lejano, con vencimiento de oferta de 10 minutos para una solicitud urgente y de 2 horas para una programada (parámetros).
3. **Ronda 2, terceros**: si ningún propio acepta, está disponible o su oferta vence, se ofrece a terceros e independientes elegibles en el radio inicial (por defecto 50 km), ordenados por una puntuación que combina cercanía, calificación, tasa de aceptación y tasa de cancelación.
4. **Escalado**: sin aceptación, el radio crece (50, 100 y 150 km por defecto) en rondas sucesivas con la misma ventana de oferta. Se ofrece a un máximo de proveedores por ronda (parámetro, 5 por defecto) para evitar saturar.
5. **Cierre**: agotadas las rondas, la solicitud pasa a `sin_proveedor` y se notifica al cliente con opciones.
6. **Puntuación de terceros**: `0.40 × cercanía normalizada + 0.35 × calificación normalizada + 0.15 × tasa de aceptación + 0.10 × (1 − tasa de cancelación)`. Los pesos son parámetros.
7. **Exclusiones**: un proveedor suspendido, con calificación bajo el umbral o con una incidencia grave abierta en el último periodo no recibe ofertas.
8. La oferta muestra al proveedor el terreno aproximado, las hectáreas, el pago estimado y la distancia, pero no los datos de contacto del cliente hasta aceptar.
9. Un proveedor puede rechazar con motivo; los rechazos no penalizan hasta superar el umbral de tasa de aceptación definido en la política de calificación.

## 7. Eventos de dominio

`SolicitudPublicada`, `OfertaCreada`, `OfertaAceptada`, `OfertaVencida`, `SolicitudSinProveedor`, `SolicitudAsignada`, `EquipoEnCamino`, `SesionAbierta`, `SesionCerrada`, `SesionConfirmada`, `SolicitudEjecutada`, `ActaFirmada`, `SolicitudCompletada`, `SolicitudCancelada`, `ProveedorAprobado`, `ProveedorSuspendido`, `DocumentoPorVencer`, `DocumentoVencido`, `CalificacionRegistrada`, `CalificacionModerada`, `DisputaAbierta`, `DisputaResuelta`, `IncidenciaReportada`, `EmergenciaActivada`. Cada evento lo emite el módulo dueño del hecho; los demás reaccionan por oyentes (notificaciones, métricas, reputación, comisión).

## 8. Políticas de servicio (resumen)

El texto completo vive en `docs/politicas/`.

### 8.1 Cancelación

Gratuita hasta la aceptación. Después de la aceptación, el cliente paga una penalidad escalonada según la cercanía a la fecha y si el equipo ya salió. Un proveedor que cancela después de aceptar recibe un registro en su tasa de cancelación y, de reincidir, restricciones. La cancelación por clima, emergencia o causa del otro lado no penaliza. Ver `cancelacion.md`.

### 8.2 Tarifa

Tarifa por hectárea, no tarifa dinámica por demanda. La tarifa depende del tipo de aplicación y de la zona, con recargos explícitos y visibles por urgencia, terreno de difícil acceso y producto aportado por el proveedor. El cliente ve el total antes de confirmar y nunca cambia después de publicar salvo acuerdo expreso. Ver `comision.md`.

### 8.3 Calificación

El cliente califica al proveedor, al piloto y al auxiliar de 1 a 5 estrellas con etiquetas; el proveedor califica al cliente y el administrador modera. Ver `calificacion.md`.

### 8.4 Disputas y reembolsos

Motivos: cobertura incompleta, mala aplicación, daño a cultivos o terceros, producto distinto al pactado, no se presentó el equipo, evidencia faltante, cobro indebido. Plazos, evidencias y reembolsos en `disputas.md`.

## 9. Seguridad y confianza

### 9.1 Verificación

Documentos obligatorios por sujeto, revisión manual y vencimientos con aviso previo. Ver `verificacion_proveedores.md`.

### 9.2 Condiciones de vuelo

El sistema registra viento, temperatura, humedad y lluvia. Los límites por tipo de aplicación son parámetros. Superarlos exige suspender o registrar la condición forzada con motivo, que queda a la vista de soporte y del cliente. Una aplicación con condiciones forzadas puede ser objeto de disputa con evidencia reforzada.

### 9.3 Incidencias y emergencias

Tipos: `deriva_de_producto`, `dron_caido`, `lesion`, `fuga_o_derrame`, `intrusion_de_personas_o_animales`, `conflicto_con_cliente`, `conflicto_con_proveedor`, `falla_de_equipo`, `otro`. El botón de emergencia (SOS) está en las apps del piloto, del auxiliar y del cliente: avisa a soporte con la ubicación, abre una incidencia de severidad `emergencia` y muestra el número de emergencias locales. Las emergencias se atienden también por teléfono; el SOS no sustituye llamar a los servicios de emergencia. Ver `seguridad.md`.

### 9.4 Desactivación

Calificación promedio bajo el umbral tras un mínimo de servicios, tasa de cancelación alta, incidencia grave probada o documentos vencidos suspenden al proveedor, con aviso previo, motivo y vía de apelación. Ver `calificacion.md` y `verificacion_proveedores.md`.

### 9.5 Soporte

Canal por la app y por la web, con cola de casos por severidad. Una incidencia `emergencia` se atiende de inmediato; una disputa recibe primera respuesta en 24 horas hábiles (parámetro).

## 10. Endpoints

Prefijo `/api/v1`. Autenticación por token. El contrato publicado es `docs/api/openapi.yaml`, generado desde el código (ADR 0014). Los verbos de abajo definen la superficie.

### 10.1 Cliente

| Método y ruta | Función |
|---|---|
| `POST /clientes/registro` | Alta de cliente |
| `GET/POST/PUT /terrenos` | Terrenos del cliente |
| `POST /solicitudes` | Crea solicitud (idempotente por `uuid_cliente`) |
| `GET /solicitudes`, `GET /solicitudes/{id}` | Lista y detalle |
| `POST /solicitudes/{id}/publicar` | Congela tarifa y la deja `publicada` |
| `POST /solicitudes/{id}/cancelar` | Cancela con motivo |
| `GET /solicitudes/{id}/estimacion` | Tarifa estimada |
| `POST /solicitudes/{id}/acta/firmar` | Conformidad |
| `POST /solicitudes/{id}/calificaciones` | Califica al proveedor, piloto o auxiliar |
| `POST /solicitudes/{id}/disputas` | Abre disputa |
| `POST /incidencias`, `POST /emergencias` | Reporte y SOS |

### 10.2 Proveedor

| Método y ruta | Función |
|---|---|
| `POST /proveedores/registro` | Alta de proveedor independiente o tercero (queda `pendiente`) |
| `POST /proveedores/documentos` | Carga documentos |
| `GET/POST/PUT /proveedores/pilotos`, `/auxiliares`, `/drones`, `/equipos` | Gestión de personal y flota (el piloto registra a su auxiliar) |
| `PUT /disponibilidad` | En línea o fuera de línea |
| `GET /ofertas` | Ofertas vivas |
| `POST /ofertas/{id}/aceptar`, `POST /ofertas/{id}/rechazar` | Respuesta (requiere red) |
| `POST /solicitudes/{id}/en-camino` | El equipo salió |
| `POST /solicitudes/{id}/cancelar-proveedor` | Cancelación del proveedor con motivo |
| `GET /reputacion` | Calificaciones y tasas propias |

### 10.3 Sincronización del lote (piloto y auxiliar)

| Método y ruta | Función |
|---|---|
| `POST /sync/lote` | Recibe un lote ordenado por `secuencia` con sesiones, mezclas, incidencias y metadatos de evidencias; cada registro se aplica una sola vez por `uuid_cliente`; devuelve el resultado por registro |
| `POST /sync/evidencias` | Sube evidencias comprimidas, en cola separada |
| `GET /sync/catalogos` | Catálogos desde un cursor (productos, límites de condiciones, parámetros) |
| `GET /sync/solicitudes-asignadas` | Solicitudes del equipo para llevar al lote |

La sincronización es idempotente: reaplicar un lote dos, tres o diez veces, en orden o con desorden parcial, deja el mismo estado final.

### 10.4 Administración y soporte

`GET/POST /admin/proveedores/{id}/aprobar|rechazar|suspender`, `/admin/pilotos`, `/admin/auxiliares`, `/admin/drones` (mismas acciones), `/admin/documentos/{id}/revisar`, `/admin/calificaciones/{id}/moderar`, `/admin/disputas/{id}/resolver`, `/admin/solicitudes` (consulta y reasignación manual), `/admin/parametros`, `/admin/metricas`.

## 11. Parámetros configurables

Ventana de oferta (urgente y programada), máximo de proveedores por ronda, radios de escalado, pesos de la puntuación, plazo de conformidad tácita, escala de penalidades de cancelación, umbral de calificación mínima y mínimo de servicios para evaluarla, umbral de tasa de aceptación y de cancelación, límites de viento, temperatura, humedad y lluvia por tipo de aplicación, porcentaje de comisión, anticipo de vencimiento de documentos.

## 12. Notificaciones

Mecanismo del ADR 0025: un aviso por cuenta destinataria al emitir el evento. Canal in-app y notificación push del sistema operativo; correo para eventos de cuenta.

| Evento | Destinatario |
|---|---|
| `OfertaCreada` | Proveedor (push con sonido y cuenta regresiva) |
| `SolicitudAsignada` | Cliente |
| `EquipoEnCamino` | Cliente |
| `SesionConfirmada`, `ActaFirmada` | Cliente y proveedor |
| `SolicitudSinProveedor` | Cliente |
| `SolicitudCancelada` | La contraparte y soporte |
| `ProveedorAprobado`, `ProveedorSuspendido`, `DocumentoPorVencer` | Proveedor |
| `CalificacionRegistrada` | Proveedor |
| `DisputaAbierta`, `DisputaResuelta` | Partes y soporte |
| `EmergenciaActivada` | Soporte (prioridad máxima) y contacto de emergencia del equipo |

## 13. Superficies móviles y offline

| Flavor | Conexión | Qué guarda local |
|---|---|---|
| `cliente` | Online primero, sin outbox | Solo caché de lectura |
| `piloto` | Online para ofertas y aceptación; offline en el lote | Sesión de vuelo, incidencias y evidencias; outbox con `uuid_cliente` |
| `auxiliar` | Online para ver la solicitud; offline en el lote | Mezclas y evidencias; outbox con `uuid_cliente` |

En el lote: todo registro nace con su `uuid_cliente` en el dispositivo, escribir es insertar local y encolar en una sola transacción, el orden causal lo da la `secuencia` local y no el reloj, y un registro ya confirmado por el servidor no se reescribe: se corrige con un registro nuevo con motivo y autor. Aceptar o rechazar una oferta es la única acción de negocio que exige red; se documenta como excepción en ADR 0028.

## 14. Métricas

- **Mercado**: solicitudes por día, tasa de asignación, tiempo hasta la aceptación, porcentaje asignado a propios y a terceros, hectáreas servidas, solicitudes sin proveedor.
- **Calidad**: calificación promedio, tasa de disputas, tasa de reembolso, incidencias por 1.000 hectáreas, sesiones observadas.
- **Oferta**: proveedores activos, drones aprobados, tasa de aceptación, tasa de cancelación del proveedor, tiempo de aprobación de un proveedor.
- **Ingreso**: volumen bruto de servicios, comisión devengada, ingreso por hectárea.
- **Sincronización**: antigüedad de la cola del outbox, tasa de reintentos, evidencias pendientes.

## 15. Alcance de la primera versión

Incluye: registro y aprobación de proveedores, flota con dueño, solicitud, asignación con prioridad a propios, ejecución offline en el lote, acta de conformidad, calificación, disputas, incidencias y SOS, métricas básicas. No incluye: cobro dentro de la app (la comisión se liquida fuera de la app, ADR 0034), cobertura de seguro propia, subastas de precio entre proveedores ni facturación electrónica.
