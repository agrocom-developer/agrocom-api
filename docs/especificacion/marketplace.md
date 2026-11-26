# Especificación del marketplace de fumigación con drones

**Agrocom SRL · Documento oficial vigente · fuente de verdad funcional y técnica**

Agrocom es un marketplace de servicios de fumigación con drones. Un cliente publica una solicitud sobre su propiedad con un precio por hectárea; los pilotos la ven en un mapa, aceptan el precio o contraofertan; el cliente compara las ofertas, elige una y negocia hasta acordar. Agrocom es el nexo entre las partes y el registro del estado y del resultado. Lo operativo en el lote (cómo se trabaja, cuándo se vuela, qué se mezcla) lo acuerdan en persona el cliente y el piloto. El mercado es de oferta y demanda: el precio nace de la negociación.

Este documento define el producto. Las decisiones que lo sostienen están en `docs/decisiones/` (ADR 0027 a 0042), las reglas de operación en `docs/politicas/` y la lista exacta de recursos, estados y reglas para implementar en `docs/especificacion/negociacion-contrato.md`. Cuando un valor numérico figura aquí como «parámetro», vive en la configuración del sistema (ADR 0016) y un administrador puede ajustarlo sin desplegar.

## 1. Principios

1. **Solicitud por solicitud.** Toda relación comercial es una solicitud de servicio con su precio acordado.
2. **El precio se negocia.** El cliente propone un precio por hectárea; cada piloto lo acepta o propone el suyo; el cliente elige. El cliente decide: ninguna tarifa se impone y ningún proveedor tiene prioridad sobre otro.
3. **Una oferta vigente por negociación.** En cada negociación hay a lo sumo una oferta vigente, y la última oferta aceptada por la contraparte es el acuerdo.
4. **Confianza verificable.** Cualquiera se registra y entra a la app, pero ningún cliente publica ni acepta ofertas, y ningún proveedor ofrece ni acepta, sin la aprobación de un administrador de su cuenta, que exige selfie y documento de identidad; ningún dron vuela sin aprobación y sin fotos (ADR 0032, ADR 0042, ADR 0046).
5. **Negociar y decidir requieren red; el lote es offline.** Publicar, ofertar, contraofertar, aceptar y calificar exigen conexión. El avance, las fotos y el cierre que el piloto registra en el lote se capturan sin conexión y se sincronizan después (ADR 0028).
6. **Un solo rol escritor por registro.** Cada registro lo escribe un solo actor; las correcciones son registros nuevos con motivo y autor.
7. **Decimales exactos.** Hectáreas y montos se manejan como decimal exacto, nunca como punto flotante.
8. **Todo queda auditado.** Cada transición de estado deja una fila de historial con autor, motivo y momento (ADR 0007).
9. **Sin cobro en la aplicación.** La app muestra el precio acordado y el precio final a pagar; el pago ocurre fuera de ella (ADR 0034).
10. **Datos de contacto solo tras adjudicar.** El cliente y el piloto se ven los datos personales cuando la oferta se adjudica (ADR 0040).

## 2. Actores y roles

| Actor | Descripción | Superficie |
|---|---|---|
| **Cliente** | Persona o empresa que publica la solicitud, compara ofertas, elige, sigue el avance y califica. | App móvil `cliente` (online) y web |
| **Piloto** | Opera el dron. Puede ser empleado de Agrocom, independiente o piloto de un proveedor tercero. Ve el mapa de solicitudes abiertas, ofrece, ejecuta y sube fotos de avance. | App móvil `piloto` (offline en el lote) |
| **Proveedor** | Entidad que presta el servicio: Agrocom (propio), un piloto independiente o una empresa o dueño de drones con sus pilotos (tercero). | Web y app |
| **Dueño de dron** | Quien posee el dron. | Web y app |
| **Administrador** | Personal de Agrocom que ve todo el panel: verifica y aprueba clientes, proveedores, pilotos y drones; atiende incidencias, emergencias y disputas; modera calificaciones; configura parámetros; administra usuarios y roles. | Web (panel) |
| **Operador** | Personal de Agrocom con acceso a partes específicas de algunos módulos del panel; el administrador define cuáles. | Web (panel) |

Hay tres tipos de cuenta (ADR 0046). La **cuenta de panel** es del personal de Agrocom, tiene un solo rol (Administrador u Operador) y las acciones se autorizan por los permisos de ese rol. La **cuenta de cliente** entra a la app `cliente` y la **cuenta de proveedor** a la app `piloto`: cada app admite un solo tipo, cada cuenta tiene su propio usuario y contraseña, su propia verificación de identidad y su propia aprobación, y no hay conversión de una en otra ni selector de rol. Un cliente que quiere ser proveedor se registra de nuevo en la app del piloto. Una persona real, identificada por su documento, tiene a lo sumo una cuenta de cliente y una de proveedor (§4.10). Una persona no ofrece sobre una solicitud propia, ni con su otra cuenta. Un administrador no modera lo que él mismo calificó.

## 3. Flujo de punta a punta

1. El cliente registra su **propiedad** y sus **lotes** en el mapa (geometría y hectáreas) una sola vez, con fotos del estado del terreno si quiere.
2. El cliente aprobado crea una **solicitud**: lotes a fumigar, hectáreas, cultivo, tipo de aplicación, producto, fecha deseada, accesos, observaciones y **precio por hectárea** (el sistema muestra un precio de referencia no vinculante). Al publicarla queda `abierta`.
3. Los pilotos aprobados ven la solicitud en el **mapa de solicitudes abiertas**, con filtros por zona (valles, Chiquitania y otras), hectáreas y precio. Quien tiene activadas las notificaciones recibe un aviso si la solicitud cae en sus zonas (ADR 0041).
4. Antes de ofertar, el piloto abre el detalle: lotes dibujados, hectáreas, cultivo, accesos, observaciones y fotos del terreno. No ve datos de contacto del cliente.
5. El piloto **acepta el precio propuesto** o **contraoferta** su precio por hectárea, indicando el dron con el que trabajará. La solicitud pasa a `con_ofertas`.
6. El cliente compara las ofertas (precio por hectárea y total, calificación, dron, distancia) y **acepta una**, **contraoferta** o **rechaza**. La negociación avanza por rondas: en cada una solo una parte tiene la oferta vigente (ADR 0030).
7. Cuando una parte acepta la oferta vigente de la otra, la oferta se **adjudica**: la solicitud queda `adjudicada` con su precio acordado, las demás negociaciones se cierran y se abren los datos de contacto y el chat entre las dos partes.
8. Las partes acuerdan en persona cómo trabajan. El piloto marca **en ejecución**, sube fotos y marca su avance (porcentaje u hectáreas). El cliente ve las fotos, el avance, la última ubicación de la app del piloto y conversa con él por mensajes (ADR 0040).
9. El piloto **finaliza** con las hectáreas ejecutadas. El sistema calcula el **precio final** (hectáreas ejecutadas por precio acordado) y se muestra a ambos como el monto a pagar. La solicitud queda `finalizada`. El cliente, que es quien paga, tiene la última palabra: confirma el precio final o abre una disputa.
10. El cliente califica al proveedor y la solicitud queda `calificada`; el proveedor califica al cliente en privado. Cualquiera puede abrir una disputa dentro de su plazo.

## 4. Modelo de datos

Convenciones: dominio en español, prefijo de tabla por módulo (ADR 0011), borrado lógico (ADR 0007), importes y hectáreas en `DECIMAL`. Cada módulo escribe solo sus tablas y se comunica con los demás por contratos y eventos (ADR 0003). Prefijos: `sol_` (clientes, propiedades y solicitudes), `prv_` (proveedores), `flo_` (flota), `neg_` (negociación), `seg_` (seguimiento: avance, mensajes y ubicación), `cal_` (calificaciones), `dsp_` (disputas).

### 4.1 Clientes, propiedades y zonas

- **`sol_clientes`**: `id`, `usuario_id`, `tipo` (`persona` | `empresa`), `razon_social`, `documento_fiscal`, `telefono`, `nombre_comercial`, `logo_ref` (ambos opcionales; `razon_social` y `documento_fiscal`, el NIT, son opcionales cuando el cliente es una persona), `estado` (el ciclo de aprobación de §5.2: nace `pendiente`), `motivo_estado`, `calificacion_promedio` (decimal 3,2), `calificaciones_conteo`.
- **`sol_cliente_contactos`**: personas de referencia del cliente. `id`, `cliente_id`, `nombre`, `telefono`, `cargo` (nulo), `es_principal`, baja lógica. Una empresa tiene al menos un contacto vigente; hay un solo principal vigente (el primero que se crea; si se da de baja, lo releva el más antiguo). La solicitud guarda `contacto_id` (`sol_solicitudes_servicio`): el elegido al crearla o, si no elige, el principal de la empresa. Tras adjudicar, el proveedor recibe el nombre, el teléfono y el cargo de esa persona, y la razón social de la empresa; antes de adjudicar no ve ninguno (ADR 0043).
- **`sol_terrenos`**: la propiedad o lote del cliente. `id`, `cliente_id`, `nombre`, `geometria` (polígono GeoJSON), `hectareas` (decimal 12,4), `cultivo`, `referencias_acceso`, `colindantes_sensibles` (texto: apiarios, cultivos orgánicos, viviendas), `zona_id`, `estado_terreno` (texto breve: maleza, humedad, obstáculos, líneas eléctricas).
- **`sol_terreno_fotos`**: `id`, `terreno_id`, `archivo`, `hash`, `bytes`, `descripcion`. Imagen comprimida a menos de 300 KB, hasta 8 por terreno (`solicitudes.fotos_terreno.maximo_por_terreno`), en el disco privado. Las sube el cliente dueño y las ve el piloto en el detalle de la solicitud antes de ofertar, con una URL firmada de corta vida (15 minutos).
- **`sol_precios_referencia`**: `id`, `tipo_aplicacion`, `zona_id` (nulo), `macrorregion` (nulo), `precio_ha` (decimal 14,4). Guía de precio por hectárea con tres niveles de detalle: zona, macrorregión o general por tipo. Datos sembrados que el administrador ajusta; el sembrado no los pisa. Si no hay ninguno, vale el respaldo de `config/negociacion.php`.
- **`sol_zonas`**: `id`, `nombre`, `macrorregion` (`valles` | `chiquitania` | `otras`; catálogo ampliable por parámetro), `centroide`. Alimentan los filtros del mapa y las notificaciones.

### 4.2 Solicitud de servicio

**`sol_solicitudes_servicio`**

| Columna | Tipo | Nota |
|---|---|---|
| `id`, `uuid_cliente` | PK, uuid único | El `uuid_cliente` nace en el dispositivo y hace idempotente la creación |
| `cliente_id` | FK | |
| `terrenos` | relación | Uno o varios terrenos o lotes del mismo cliente (`sol_solicitud_terrenos`) |
| `tipo_aplicacion` | enum | `herbicida`, `insecticida`, `fungicida`, `fertilizante_foliar`, `desecante`, `otro` |
| `hectareas_solicitadas` | decimal 12,4 | No supera la suma de las hectáreas de los terrenos elegidos |
| `producto_indicado` | texto | Producto o caldo que el cliente o su agrónomo indican |
| `aporta_producto` | enum | `cliente` o `proveedor` |
| `ventana_desde`, `ventana_hasta` | fecha | Fecha deseada |
| `observaciones` | texto | Accesos, horarios, restricciones |
| `precio_ha_propuesto` | decimal 14,4 | Moneda del sistema (parámetro, bolivianos) |
| `precio_ha_acordado`, `monto_estimado` | decimal | Se fijan al adjudicar; el estimado es hectáreas solicitadas por precio acordado |
| `hectareas_ejecutadas`, `porcentaje_avance`, `monto_final` | decimal | El avance lo marca el piloto; el monto final se congela al finalizar |
| `estado` | enum | Ver §5.1 |
| `vence_en` | timestamp | Fin de la vigencia mientras está `abierta` o `con_ofertas` |
| `oferta_adjudicada_id`, `proveedor_id`, `equipo_id` | FK nulas | Se fijan al adjudicar |
| `motivo_cancelacion`, `cancelada_por` | nulos | |

**`sol_solicitud_historial`**: `solicitud_id`, `estado_anterior`, `estado_nuevo`, `actor_usuario_id`, `origen`, `motivo`, `creado_en`. Solo se inserta.

### 4.3 Proveedores y personas

- **`prv_proveedores`**: `id`, `usuario_titular_id`, `tipo` (`propio` | `independiente` | `tercero`), `razon_social`, `documento_fiscal` (NIT), `nombre_comercial` y `logo_ref` (opcionales), `zona_base_id`, `estado` (§5.2), `calificacion_promedio`, `calificaciones_conteo`, `tasa_cancelacion`, `aprobado_por`, `aprobado_en`. Agrocom existe como un proveedor de tipo `propio`; compite en igualdad con los demás.
- **`prv_pilotos`**: `id`, `proveedor_id`, `usuario_id` (los nombres, el documento y el celular son los de su `sec_personas`), `licencia_numero`, `licencia_vence`, `categoria_habilitacion`, `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`).
- **`prv_documentos`**: `id`, `sujeto_tipo` (`proveedor` | `piloto` | `dron`), `sujeto_id`, `tipo` (`identidad`, `licencia_piloto`, `habilitacion_dron`, `seguro_responsabilidad`, `registro_dron`, `certificado_fumigacion`, `antecedentes`, `otro`), `archivo_ref`, `vence_en`, `estado` (`en_revision` | `aprobado` | `rechazado` | `vencido`), `revisado_por`, `motivo_rechazo`. Ruta de archivo según ADR 0026.

### 4.4 Flota

- **`flo_drones`**: `id`, `dueno_proveedor_id`, `operador_proveedor_id`, `marca`, `modelo`, `anio`, `numero_serie` (único), `capacidad_tanque_l` (decimal), `ancho_pasada_m` (decimal), `ciclos_bateria` (entero; único dato de mantenimiento que se conserva), `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`), `origen` (`flota_agrocom` | `vendido_por_agrocom` | `externo`).
- **`flo_dron_fotos`**: `id`, `dron_id`, `tipo` (`frontal`, `lateral`, `placa_serie`, `con_tanque`, `otra`), `archivo_ref`, `bytes`, `estado` (`en_revision` | `aprobada` | `rechazada`), `motivo_rechazo`. Un dron exige al menos las fotos obligatorias del parámetro (por defecto `frontal`, `lateral` y `placa_serie`) para aprobarse (ADR 0042). El cliente ve las aprobadas.
- **`flo_equipos`**: `id`, `proveedor_id`, `piloto_id`, `dron_id`, `vigente_desde`, `vigente_hasta`. Un equipo es la combinación operativa que ejecuta una solicitud. Un piloto y un dron no pueden estar en dos equipos vigentes a la vez.

### 4.5 Negociación

- **`neg_ofertas`**: una fila por oferta de una negociación. `id`, `uuid_cliente`, `solicitud_id`, `proveedor_id`, `equipo_id`, `numero` (1, 2, 3… dentro de la negociación), `autor_rol` (`proveedor` | `cliente`), `autor_usuario_id`, `tipo` (`acepta_propuesta` | `contraoferta`), `precio_ha` (decimal 14,4), `mensaje` (hasta 280 caracteres), `es_definitiva` (bool), `estado` (§5.3), `vence_en`, `respondida_en`, `motivo`. La negociación es el conjunto de ofertas de un `(solicitud_id, proveedor_id)`; un índice único parcial garantiza una sola oferta `vigente` por negociación y otro una sola `aceptada` por solicitud.
- **`neg_cancelaciones`**: registro informativo de cancelaciones tras adjudicar: `solicitud_id`, `parte` (`cliente` | `proveedor`), `motivo`, `creado_en`. Alimenta la tasa de cancelación visible; no genera sanción automática (ADR 0033).

### 4.6 Seguimiento del servicio

Las tablas de ejecución que el piloto escribe en el lote llevan `uuid_cliente`, `secuencia` local y `dispositivo_id`.

- **`seg_avances`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `tipo` (`inicio` | `avance` | `cierre`), `hectareas_ejecutadas` (decimal 12,4, acumuladas), `porcentaje` (decimal 5,2), `nota` (hasta 280 caracteres), `motivo_correccion` (obligatorio si el valor baja), `secuencia`, `registrado_en`. La escribe el piloto del equipo adjudicado.
- **`seg_evidencias`**: `id`, `uuid_cliente`, `solicitud_id`, `avance_id` (nulo), `autor_usuario_id`, `autor_rol` (`piloto`), `tipo` (`foto_avance`, `foto_final`, `foto_incidencia`), `archivo_ref`, `bytes`, `subida_en`. Imagen comprimida a menos de 300 KB antes de subir; cola de subida separada de los registros livianos.
- **`seg_mensajes`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `autor_rol` (`cliente` | `piloto`), `texto` (hasta 1000 caracteres), `creado_en`, `leido_en` (nulo hasta que lo lee la contraparte).
- **`seg_ubicaciones`**: una fila por solicitud, la última: `solicitud_id` (único), `latitud`, `longitud`, `precision_m`, `capturado_en`, `recibido_en`. Se elimina al finalizar o cancelar (ADR 0040).
- **`seg_incidencias`**: `id`, `uuid_cliente`, `solicitud_id`, `reportada_por_usuario_id`, `tipo` (§9.3), `severidad` (`informativa` | `grave` | `emergencia`), `descripcion`, `ubicacion`, `estado` (`abierta` | `en_atencion` | `cerrada`).
- **`neg_pagos_confirmados`**: `uuid_cliente`, `solicitud_id` (único), `confirmado_por_usuario_id`, `monto` (decimal 14,2, copia del monto final al confirmar), `metodo` (`efectivo` | `transferencia` | `qr` | `otro`), `nota`, `confirmado_en`. Registro informativo del proveedor de que recibió el pago; lo escribe solo el titular del proveedor adjudicado, no se reescribe y no cambia el estado.

### 4.7 Calificaciones

**`cal_calificaciones`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `autor_rol` (`cliente` | `proveedor`), `sujeto_tipo` (`proveedor` | `piloto` | `cliente`), `sujeto_id`, `estrellas` (entero 1 a 5), `etiquetas` (lista de un catálogo cerrado), `comentario`, `estado` (`visible` | `en_moderacion` | `oculta`), `moderada_por`, `motivo_moderacion`. Única por `(solicitud_id, autor_rol, sujeto_tipo, sujeto_id)`. La escribe un solo actor y nunca se edita: moderar cambia `estado`, no el contenido.

### 4.8 Disputas

- **`dsp_disputas`**: `id`, `solicitud_id`, `abierta_por_rol` (`cliente` | `proveedor`), `motivo` (§8.3), `detalle`, `monto_en_disputa`, `estado` (§5.5), `resolucion` (`a_favor_cliente` | `a_favor_proveedor` | `parcial` | `sin_lugar`), `monto_ajustado` (nuevo precio final tras la resolución), `resuelta_por`, `resuelta_en`.
- **`dsp_mensajes`**: `disputa_id`, `autor_usuario_id`, `texto`, `creado_en`.

### 4.9 Transversales

- **`ntf_notificaciones`** (ADR 0025): un aviso por cuenta destinataria.
- **`ntf_preferencias`** y **`ntf_dispositivos`** (ADR 0041): preferencias por categoría, zonas de interés y token de push por dispositivo. Un token está activo en una sola cuenta a la vez; el envío al sistema operativo va detrás de la interfaz `EnviadorDePush`, sin proveedor configurado todavía.
- **`sec_user.foto_perfil_ref`**: foto de perfil de la cuenta (cliente, titular o piloto), menos de 300 KB, en el disco privado. No es la selfie de verificación (§4.10).
- **`cfg_parametros`** (ADR 0016): parámetros de §11.
- **`sec_*`**: usuarios, roles y permisos (ADR 0004, ADR 0046), personas y verificaciones (§4.10).
- **`aud_bitacora`**: auditoría de cambios (ADR 0007).

### 4.10 Cuentas, personas y verificación (ADR 0046)

- **`sec_user`**: la cuenta (credenciales, foto de perfil, estado). `telefono` (E.164, obligatorio en las cuentas de app; referencia principal de autenticación) con `telefono_verificado_en` (nulo mientras no haya prueba de posesión), y `email` (correo de respaldo, obligatorio en las cuentas de app) con `email_verificado_en`. Índices únicos parciales `(telefono, tipo_cuenta)` y `(email, tipo_cuenta)` entre cuentas no eliminadas: no hay dos cuentas de cliente con el mismo teléfono ni dos de proveedor, pero el mismo teléfono sirve a una de cada tipo. `tipo_cuenta` vale `panel`, `cliente` o `proveedor`. `persona_id` apunta a `sec_personas` y es obligatorio en las cuentas de app. Las cuentas `panel` tienen un solo rol (`administrador` u `operador`) en `sec_user_role`; las cuentas de app no tienen roles. Índice único parcial `(persona_id, tipo_cuenta)`: una persona tiene a lo sumo una cuenta de cliente y una de proveedor.
- **`sec_personas`**: la persona real, con `documento_tipo` (`ci` | `pasaporte` | `licencia_conducir`) y `documento_numero` (normalizado) **únicos**, `nombres`, `apellidos` y `fecha_nacimiento` (nulo; la pide el registro del proveedor); el teléfono es de la cuenta, no de la persona. Los mismos datos sirven a sus dos cuentas sin duplicarse. Registrarse con un teléfono o un documento que ya tiene una cuenta de ese tipo responde `409 cuenta_ya_registrada` sin crear nada; el registro en el otro tipo enlaza la misma persona sin sobrescribir sus datos y sin reutilizar la verificación.
- **`sec_verificaciones`**: una por cuenta (`usuario_id`). `estado` (`en_revision` | `aprobada` | `rechazada` | `reemplazada`), `selfie_ref`, `selfie_capturada_en`, `selfie_origen` (`camara` | `administrador`), `documento_ref`, `documento_origen` (`cuenta` | `administrador`), `es_marcador` (el administrador no adjuntó documento de la persona), `motivo_alta_administrador`, `revisado_por`, `revisado_en`, `motivo_rechazo`. El tipo y el número del documento son los de la persona. Una vigente por cuenta; reenviar crea otra fila. Los archivos van al disco privado (ADR 0026) y solo los sirve el panel con `seguridad.verificacion.ver`; cada acceso queda en la bitácora. Se conservan mientras exista la cuenta y 12 meses después de su baja (las rechazadas o reemplazadas, 90 días); luego una tarea diaria elimina los archivos y deja la fila sin referencias (ADR 0046, punto 33).
- **`sec_codigos_verificacion`**: código de 6 dígitos (hash) para `recuperar_acceso` y `cambiar_telefono` por el canal `correo`, con `vence_en` (10 minutos), `intentos` y `usado_en`; `sms` y `whatsapp` reservados para cuando exista un proveedor. No se escribe en logs ni en la bitácora.
- **`sec_aceptaciones`**: la aceptación de la versión vigente de los términos y de la privacidad por cuenta (`documento`, `version`, `aceptado_en`, `app`, `uuid_dispositivo`); solo se inserta.
- **`prv_vinculos_piloto`**: el vínculo de un piloto con una empresa (`estado`: `invitado` | `solicitado` | `activo` | `rechazado` | `liberado`), con quién lo inició y quién lo liberó; el piloto tiene su cuenta propia y el encargado de la empresa (su titular) gestiona el vínculo (ADR 0046, puntos 34 a 38).
- **`flo_equipo_integrantes`**: `equipo_id`, `nombre`, `celular` (nulo), `orden`. Datos informativos de las personas que forman el equipo del servicio, sin cuenta ni página propia; el cliente los ve solo tras adjudicar, junto al piloto (marcado como tal).
- **Perfil de la cuenta.** La cuenta `cliente` es la fila de `sol_clientes`; la cuenta `proveedor` es la fila de `prv_proveedores` (titular) o de `prv_pilotos` (piloto con cuenta propia de un tercero). El estado de la cuenta es el de esa fila.

## 5. Máquinas de estado

### 5.1 Solicitud de servicio

`borrador` → `abierta` → `con_ofertas` → `adjudicada` → `en_ejecucion` → `finalizada` → `calificada`. Más `cancelada`, `expirada` y `disputada`.

| Estado | Significado | Transiciones (actor) |
|---|---|---|
| `borrador` | El cliente la arma. | `abierta` (cliente, al publicar), `cancelada` (cliente) |
| `abierta` | Publicada, visible en el mapa de los pilotos, sin ofertas vigentes. | `con_ofertas` (sistema, al llegar la primera oferta), `adjudicada` (cliente o piloto al aceptar), `expirada` (sistema), `cancelada` (cliente) |
| `con_ofertas` | Hay al menos una oferta vigente. | `abierta` (sistema, cuando no queda ninguna vigente), `adjudicada`, `expirada` (sistema), `cancelada` (cliente) |
| `adjudicada` | Hay precio acordado y un equipo. Se abren contacto y chat. | `en_ejecucion` (piloto), `abierta` (proveedor cancela), `cancelada` (cliente) |
| `en_ejecucion` | El piloto marcó el inicio. Hay fotos y avance. | `finalizada` (piloto, al cerrar con evidencia subida) |
| `finalizada` | Terminada; muestra el precio final a pagar. Se puede calificar y disputar. | `calificada` (sistema, al calificar el cliente), `disputada` (cliente o proveedor) |
| `calificada` | El cliente calificó al proveedor. | `disputada` (dentro del plazo) |
| `disputada` | Hay una disputa abierta. | `finalizada` o `calificada` (la que tenía), `cancelada` (reembolso total) |
| `expirada` | Venció sin adjudicar. | `abierta` (cliente, al renovar dentro de su plazo) |
| `cancelada` | Terminal; guarda quién y por qué. | — |

El detalle de reglas y guardas de cada transición está en `negociacion-contrato.md`.

### 5.2 Cliente, proveedor, piloto y dron

La cuenta de cliente y la de proveedor tienen cada una su estado; el cliente nace `pendiente`, como el proveedor. Una cuenta `aprobada` exige su verificación de identidad `aprobada` (selfie con cámara y documento: CI, licencia de conducir o pasaporte; ADR 0046). Con la cuenta `pendiente`, `rechazada`, `suspendida` o en `baja` la persona entra y ve las pantallas de su app, pero el servidor rechaza con `403` (`cuenta_no_aprobada`) publicar, aceptar y contraofertar en el cliente, y ofertar, contraofertar y aceptar en el proveedor. El alta que hace un administrador desde el panel la puede aprobar el mismo administrador; nadie aprueba su propia cuenta. El proveedor que aún no está aprobado no ve el mapa, los lotes, los clientes ni ninguna ubicación: ve solo un contador de solicitudes abiertas, filtrable por departamento, para saber cuántos trabajos pierde (ADR 0046, punto 12).

`pendiente` → `aprobado` ⇄ `suspendido`; `pendiente` → `rechazado` (el solicitante puede reenviar); cualquiera → `baja`. Solo un administrador aprueba, suspende o da de baja. Un proveedor `aprobado` no ve el mapa de solicitudes abiertas ni ofrece si no tiene al menos un equipo con piloto y dron aprobados y documentos vigentes. Un documento vencido suspende de forma automática al sujeto hasta que se cargue uno vigente. Un dron se aprueba solo con las fotos obligatorias aprobadas (ADR 0042).

### 5.3 Oferta

`vigente` → `aceptada` | `reemplazada` | `rechazada` | `retirada` | `vencida` | `cerrada`.

- `aceptada`: la contraparte la aceptó; es el acuerdo y adjudica la solicitud.
- `reemplazada`: la contraparte respondió con una contraoferta.
- `rechazada`: la contraparte la rechazó; cierra la negociación.
- `retirada`: su autor la retiró.
- `vencida`: pasó su `vence_en` sin respuesta.
- `cerrada`: la solicitud se adjudicó a otra negociación, se canceló o expiró.

Solo una oferta por solicitud llega a `aceptada`; la aceptación es atómica sobre la solicitud (bloqueo de fila) y el segundo intento recibe conflicto.

### 5.4 Seguimiento

El avance es un registro de `seg_avances`: `inicio` (pasa la solicitud a `en_ejecucion`), `avance` (hectáreas acumuladas o porcentaje) y `cierre` (hectáreas ejecutadas finales). El servidor valida cada registro por `uuid_cliente`, en orden de `secuencia`. El cierre deja la solicitud en `en_ejecucion` con la marca `cierre_solicitado_en` hasta que al menos `seguimiento.evidencias_minimas_cierre` fotos de avance estén subidas; entonces pasa a `finalizada` y calcula el precio final.

### 5.5 Disputa

`abierta` → `en_revision` → `resuelta`; `abierta` → `retirada` (por quien la abrió). Una sola disputa abierta por solicitud.

### 5.6 Incidencia

`abierta` → `en_atencion` → `cerrada`. Una incidencia de severidad `emergencia` notifica a soporte en el acto.

## 6. Reglas de negociación

Detalle de la decisión en ADR 0030 y reglas exactas en `negociacion-contrato.md`. Resumen:

1. **Quién ofrece.** Un proveedor aprobado con un equipo apto puede ofrecer sobre cualquier solicitud `abierta` o `con_ofertas` de su alcance. Ofrece aceptando el precio propuesto (`acepta_propuesta`) o con otro precio (`contraoferta`), y elige el equipo (dron) que usará.
2. **Una negociación por proveedor y solicitud.** Cada proveedor tiene su propia negociación con el cliente; las ofertas de otros proveedores no se ven entre sí (el piloto solo ve cuántas ofertas vigentes tiene la solicitud).
3. **Rondas.** Las ofertas de una negociación se numeran 1, 2, 3… y alternan autor: la primera la hace el proveedor, la siguiente el cliente, y así. Quien recibe una oferta vigente puede aceptarla, contraofertar o rechazarla. Contraofertar reemplaza la oferta vigente. La negociación admite hasta `negociacion.max_ofertas_por_negociacion` ofertas (parámetro, 5 por defecto); la última se marca `definitiva` y solo se acepta o se rechaza.
4. **Adjudicación.** Cuando una parte acepta la oferta vigente de la otra, la oferta queda `aceptada`, la solicitud pasa a `adjudicada` con `precio_ha_acordado`, `proveedor_id` y `equipo_id`, y las demás ofertas vigentes de la solicitud pasan a `cerrada`.
5. **Vigencia.** Cada oferta vence a las `negociacion.vigencia_oferta_horas` (24 por defecto) y nunca después de la solicitud. Una solicitud sin adjudicar expira a los `negociacion.vigencia_solicitud_dias` (7 por defecto) o al fin de la ventana deseada, lo que ocurra primero; el cliente puede renovarla.
6. **Precio.** El precio por hectárea es un decimal positivo con un máximo de sanidad (parámetro). El precio de referencia por tipo de aplicación y zona es una guía visible al proponer y al ofertar, no un límite.
7. **Sin penalidad automática.** Rechazar, retirar o dejar vencer una oferta no penaliza. Cancelar tras adjudicar queda registrado y visible en la tasa de cancelación, y un administrador decide si actúa (ADR 0033).
8. **Visibilidad previa a ofertar.** El piloto ve lotes, hectáreas, cultivo, accesos, observaciones y fotos del terreno. No ve nombre, teléfono ni documento del cliente hasta la adjudicación.
9. **Elección del cliente.** El comparador ordena por precio, calificación o distancia y muestra, por oferta: precio por hectárea, total estimado, calificación y servicios del proveedor, modelo y fotos del dron, distancia y vigencia.

## 7. Eventos de dominio

`SolicitudAbierta`, `OfertaRecibida`, `ContraofertaRecibida`, `OfertaRechazada`, `OfertaRetirada`, `OfertaVencida`, `SolicitudAdjudicada`, `OfertaCerrada`, `SolicitudExpirada`, `SolicitudRenovada`, `EjecucionIniciada`, `AvanceRegistrado`, `EvidenciaSubida`, `SolicitudFinalizada`, `SolicitudCancelada`, `MensajeEnviado`, `ProveedorAprobado`, `ProveedorSuspendido`, `DocumentoPorVencer`, `DocumentoVencido`, `CalificacionRegistrada`, `CalificacionModerada`, `DisputaAbierta`, `DisputaResuelta`, `IncidenciaReportada`, `EmergenciaActivada`. Cada evento lo emite el módulo dueño del hecho; los demás reaccionan por oyentes (notificaciones, métricas, reputación).

## 8. Políticas de servicio (resumen)

El texto completo vive en `docs/politicas/`.

### 8.1 Cancelación

Gratuita y sin registro antes de adjudicar. Tras adjudicar, el cliente o el proveedor pueden cancelar con motivo; la cancelación queda registrada y visible en la tasa de cancelación de quien la hace, sin penalidad en dinero ni sanción automática. Una cancelación del proveedor reabre la solicitud. Ver `cancelacion.md`.

### 8.2 Precio y pago

El precio por hectárea se negocia y queda congelado al adjudicar. El precio final es hectáreas ejecutadas por precio acordado. La app no procesa cobros ni calcula comisión; el pago se hace fuera de ella. Ver `precio_y_pago.md`.

### 8.3 Calificación y disputas

El cliente califica al proveedor y al piloto de 1 a 5 estrellas con etiquetas; el proveedor califica al cliente en privado y el administrador modera. Motivos de disputa: cobertura incompleta, mala aplicación, daño a cultivos o terceros, producto distinto al pactado, no se presentó el equipo, hectáreas ejecutadas discutidas, precio final discutido, cobro pendiente, acceso negado. Ver `calificacion.md` y `disputas.md`.

## 9. Seguridad y confianza

### 9.1 Verificación

Verificación de identidad de cada cuenta (selfie con cámara y documento de identidad, que se piden de nuevo en cada cuenta y se conservan 12 meses después de la baja), documentos obligatorios por sujeto, fotos del dron, revisión manual y vencimientos con aviso previo. Ver `verificacion_proveedores.md`.

### 9.2 Privacidad y visibilidad

Quién ve qué en cada estado está en `docs/politicas/seguimiento_y_privacidad.md`. La ubicación del piloto se comparte solo mientras la solicitud está `en_ejecucion`, se guarda solo la última posición y se elimina al finalizar.

### 9.3 Incidencias y emergencias

Tipos: `deriva_de_producto`, `dron_caido`, `lesion`, `fuga_o_derrame`, `intrusion_de_personas_o_animales`, `conflicto_con_cliente`, `conflicto_con_proveedor`, `falla_de_equipo`, `otro`. El botón de emergencia (SOS) está en las apps del piloto y del cliente: avisa a soporte con la ubicación, abre una incidencia de severidad `emergencia` y muestra el número de emergencias locales. El SOS no sustituye llamar a los servicios de emergencia. Ver `seguridad.md`.

### 9.4 Desactivación

Documentos vencidos, fraude o incidencia grave probada suspenden al proveedor, con aviso previo, motivo y vía de apelación. Una calificación o una tasa de cancelación bajo el umbral genera una alerta al administrador, que decide. Ver `calificacion.md` y `verificacion_proveedores.md`.

### 9.5 Soporte

Canal por la app y por la web, con cola de casos por severidad. Una incidencia `emergencia` se atiende de inmediato; una disputa recibe primera respuesta en 24 horas hábiles (parámetro).

## 10. Endpoints

Prefijo `/api`. Autenticación por token de dispositivo (`POST /auth/token`). El contrato publicado es `docs/api/openapi.yaml`, generado desde el código (ADR 0014). La lista completa con payloads, errores y reglas está en `negociacion-contrato.md`; los verbos de abajo definen la superficie.

### 10.0 Cuenta y verificación

Cada flavor usa estos endpoints con su tipo de cuenta (ADR 0046).

| Método y ruta | Función |
|---|---|
| `POST /auth/token`, `GET /auth/sesion` | Login por dispositivo, sin rol, con la app de origen (`app`); el usuario puede ser el nombre de usuario o el teléfono. Devuelven `cuenta` (tipo, estado, teléfono y lo que falta para aprobarla), `persona`, `verificacion` y `legal_pendiente`. `403 tipo_de_cuenta_incorrecto` si la cuenta es de la otra app |
| `POST /clientes/registro`, `POST /proveedores/registro` | Alta pública de la cuenta con teléfono, correo de respaldo, los datos de la persona y la versión de términos y privacidad aceptada; `409 cuenta_ya_registrada` si ese teléfono o ese documento ya tiene una cuenta de ese tipo; `422 version_legal_desactualizada` |
| `POST /auth/recuperar`, `POST /auth/recuperar/confirmar` | Recuperación desde la app: pide un código al correo de respaldo (respuesta siempre igual) y, con el código, fija la contraseña nueva y devuelve el usuario |
| `POST /cuenta/telefono`, `POST /cuenta/telefono/confirmar` | Cambio de teléfono con un código al correo de respaldo; el teléfono nuevo cumple la unicidad por tipo |
| `GET /legal/vigentes`, `POST /cuenta/aceptaciones` | Versiones y direcciones vigentes de términos y privacidad (público); registro de la aceptación |
| `GET /solicitudes-abiertas/contador` | Solo el proveedor no aprobado: cuántas solicitudes abiertas no puede ver todavía, con filtro opcional `departamento`; ningún otro dato |
| `GET /proveedores/mi/pilotos`, `POST /proveedores/mi/pilotos/invitaciones`, `POST /proveedores/mi/pilotos/{id}/liberar` | El encargado de la empresa gestiona el vínculo de sus pilotos |
| `GET /vinculos`, `POST /vinculos/solicitud`, `POST /vinculos/{id}/aceptar`, `POST /vinculos/{id}/rechazar` | El piloto ve sus invitaciones, pide unirse con el código de la empresa y acepta o rechaza |
| `GET /verificacion` | Estado de la verificación de la cuenta, motivo si fue rechazada y lo que falta |
| `PUT /verificacion/selfie`, `PUT /verificacion/documento` | Sube la selfie (con la hora de captura de la cámara) y la imagen del documento declarado; la verificación queda `en_revision` |

### 10.1 Cliente

| Método y ruta | Función |
|---|---|
| `POST /clientes/registro` | Alta de cliente; una empresa envía `contactos` o queda con quien registra como contacto principal |
| `GET/POST /clientes/mi/contactos`, `PUT/DELETE /clientes/mi/contactos/{id}` | Contactos de referencia del cliente |
| `GET/POST/PUT /terrenos`, `POST /terrenos/{id}/fotos`, `DELETE /terrenos/{id}/fotos/{foto}` | Propiedad, lotes y fotos de su estado |
| `GET /referencias/precio` | Precio por hectárea de referencia para proponer (también lo ve el piloto al ofertar) |
| `GET /solicitudes/{id}/pago-confirmado` | Si el proveedor informó que recibió el pago |
| `PUT/DELETE /perfil/foto` | Foto de perfil de la cuenta |
| `POST /solicitudes`, `PUT /solicitudes/{id}` | Crea (idempotente por `uuid_cliente`, con `contacto_id` opcional) y edita el borrador |
| `GET /solicitudes`, `GET /solicitudes/{id}` | Lista y detalle con avance y precio |
| `POST /solicitudes/{id}/publicar`, `/cancelar`, `/renovar` | Abre la solicitud, la cancela o la renueva |
| `POST /solicitudes/{id}/precio` | Cambia el precio propuesto mientras no está adjudicada |
| `GET /solicitudes/{id}/ofertas` | Comparador de ofertas |
| `POST /ofertas/{id}/aceptar`, `/contraofertar`, `/rechazar` | Respuesta del cliente a una oferta |
| `GET /solicitudes/{id}/avance`, `GET /solicitudes/{id}/ubicacion-piloto` | Fotos, avance y última ubicación |
| `GET/POST /solicitudes/{id}/mensajes` | Chat con el piloto adjudicado |
| `GET /solicitudes/{id}/precio-final`, `POST /solicitudes/{id}/precio-final/confirmar` | Ver y confirmar el precio final del servicio cerrado |
| `GET/POST /solicitudes/{id}/calificaciones` | A quién calificar y calificar al proveedor o al piloto (7 días) |
| `POST /solicitudes/{id}/disputas`, `GET /solicitudes/{id}/disputa`, `GET /disputas`, `GET /disputas/{id}`, `POST /disputas/{id}/mensajes`, `/retirar`, `/apelar` | Disputa |
| `POST /incidencias`, `POST /emergencias` | Reporte y SOS |

### 10.2 Proveedor

| Método y ruta | Función |
|---|---|
| `POST /proveedores/registro` | Alta con datos de la persona y del dron (queda `pendiente`) |
| `POST /proveedores/documentos` | Carga documentos |
| `GET/POST/PUT /proveedores/pilotos`, `/drones`, `/equipos`; `POST/GET/DELETE /proveedores/drones/{id}/fotos` | Personal y flota con fotos del dron |
| `GET /solicitudes-abiertas`, `/solicitudes-abiertas/resumen-zonas`, `/solicitudes-abiertas/{id}` | Mapa, resumen por zona y detalle previo a ofertar |
| `POST /solicitudes/{id}/ofertas` | Primera oferta de una negociación (acepta el precio o contraoferta) |
| `GET /ofertas`, `GET /ofertas/{id}` | Mis negociaciones y su historial |
| `POST /ofertas/{id}/aceptar`, `/contraofertar`, `/rechazar`, `/retirar` | Respuesta del proveedor |
| `PUT /ubicacion` | Última ubicación durante la ejecución |
| `GET/POST /solicitudes/{id}/mensajes` | Chat con el cliente adjudicado |
| `POST /solicitudes/{id}/pago-confirmado`, `GET /solicitudes/{id}/pago-confirmado` | El titular informa que recibió el pago; ambas partes lo consultan |
| `PUT/DELETE /perfil/foto` | Foto de perfil de la cuenta |
| `GET /proveedores/mi`, `GET /proveedores/drones` | El titular gestiona su proveedor y sus drones; el piloto los ve en solo lectura (el proveedor al que pertenece y los drones de sus equipos) |
| `GET/PUT /notificaciones/preferencias`, `POST/DELETE /dispositivos` | Preferencias de aviso y token de push |
| `GET /reputacion`, `GET /calificaciones/recibidas`, `POST /calificaciones/{id}/reportar` | Reputación y calificaciones recibidas |
| `GET/POST /solicitudes/{id}/calificar-cliente` | Califica al cliente en privado (7 días) |
| Rutas de disputa e incidencias | Las mismas del cliente; el lado lo decide el tipo de la cuenta |

### 10.3 Sincronización del lote (piloto)

| Método y ruta | Función |
|---|---|
| `POST /sync` | Recibe un lote de registros (`inicio`, `avance`, `cierre`, `mensaje`, `incidencia`); cada registro se aplica una sola vez por `uuid_cliente`, en orden causal fijo sin importar el orden del arreglo, y se devuelve el resultado por registro (`aplicado`, `duplicado` o `rechazado`) |
| `POST /solicitudes/{id}/evidencias` | Sube fotos de avance, finales o de incidencia comprimidas, en cola separada |
| `GET /sync/catalogo?desde={cursor}` | Catálogo desde un cursor opaco: servicios adjudicados y en ejecución del equipo con los datos que abre la adjudicación, servicios retirados, personas y mensajes recientes |

La sincronización es idempotente: reaplicar un lote dos, tres o diez veces, en orden o con desorden parcial, deja el mismo estado final.

### 10.4 Administración y soporte

Son pantallas del panel (ADR 0008), no endpoints de la API de las apps. El menú tiene cinco módulos en el riel (Mercado, Proveedores, Soporte, Seguridad y Sistema; el logo lleva al tablero) y cada pantalla se muestra solo si el rol activo tiene su permiso de listado (`.ver`); las acciones de cada una exigen su permiso propio.

| Módulo | Pantalla | Qué hace |
|---|---|---|
| Mercado | `panel/dashboard` | Tablero: solicitudes por estado y por región (macrorregión de la zona del terreno), hectáreas abiertas a ofertas y la cola de lo que espera una decisión |
| Mercado | `panel/solicitudes` | Lista con filtros por estado, región y zona; la ficha muestra cada negociación por proveedor con sus rondas y, con `solicitudes.solicitud.ver_seguimiento`, el chat, el avance, las fotos y la última ubicación del piloto (datos de las partes). Cancela con motivo (`solicitudes.solicitud.cancelar`) o cierra la negociación de un proveedor con motivo (`solicitudes.solicitud.intervenir`) mientras la solicitud sigue abierta a ofertas |
| Mercado | `panel/clientes` | Ficha del cliente con su reputación, sus contactos y sus solicitudes; suspende y reactiva con motivo (`solicitudes.cliente.suspender`); alta a mano de persona o empresa con contactos (`solicitudes.cliente.crear`) |
| Proveedores | `panel/proveedores/crear`, `panel/pilotos/crear`, `panel/drones-flota/crear` | Alta a mano de proveedor (independiente con su piloto, o tercero), de un piloto de un tercero y del dron de un proveedor, con `proveedores.proveedor.crear`, `proveedores.piloto.crear` y `flota.dron.crear`; quedan `pendientes` y quien los cargó no los aprueba (ADR 0044) |
| Mercado | `panel/calificaciones` | Oculta o deja visible una calificación con causa y motivo |
| Soporte | `panel/disputas` | Reclamos sobre un servicio ya prestado: toma, media y resuelve. Va en su propio módulo porque atender a quien usa la plataforma no es operar el mercado |
| Mercado | `panel/metricas` | Adjudicación, ofertas por solicitud, rondas por negociación, tiempos, calidad y volumen |
| Sistema | `panel/avisos` | Auditoría: lo que el sistema avisó a cada cuenta y si lo leyó; solo lectura (`notificaciones.aviso.ver`). Los avisos de la propia cuenta están en la campana |
| Proveedores | `panel/proveedores`, `panel/pilotos` | Aprobar, rechazar, suspender y dar de baja, con motivo |
| Proveedores | `panel/documentos` | Revisión de documentos y descarga restringida |
| Proveedores | `panel/drones-flota` | Aprobación del dron y revisión de sus fotos (`flota.dron.revisar_foto`); no se aprueba sin las fotos de `flota.fotos_obligatorias` aprobadas |
| Sistema | `panel/zonas` | Zonas de Santa Cruz con su región y su centro |
| Sistema | organización, configuración y versiones de las apps | Ajustes de la plataforma que se tocan poco |
| Seguridad | usuarios, roles, dispositivos y bitácora | Roles del panel: `dueno`, `encargado_operaciones` (administrador del marketplace), `soporte` y `admin_plataforma` |

Roles del panel (ADR 0046): el panel lo usa solo el personal de Agrocom y tiene dos roles, Administrador (ve todo) y Operador (ve las pantallas y acciones cuyos permisos le asigna el administrador en la matriz de roles). Este párrafo manda sobre la fila «Seguridad» de la tabla anterior y sobre «quien los cargó no los aprueba» de la fila de alta a mano: el administrador puede aprobar lo que registró, y «Ver como» solo sobre cuentas de panel. Las altas a mano pueden crear cuentas de app (cliente o proveedor, con la misma regla de una cuenta de cada tipo por persona) y aprobarlas, con la verificación marcada «cargado por administrador» (ADR 0046). La ficha del cliente y del proveedor muestra su verificación (selfie y documento, con `seguridad.verificacion.ver`), con aprobar y rechazar con motivo.

El administrador lee el chat y la ubicación solo para atender una disputa o una intervención; no escribe en ellos. Cerrar una negociación no es una sanción: no restringe ninguna cuenta (ADR 0033).

## 11. Parámetros configurables

Vigencia de la solicitud y de la oferta, máximo de ofertas por negociación, precio máximo de sanidad por hectárea, precios de referencia por tipo de aplicación y zona, moneda, tolerancia de hectáreas ejecutadas sobre las solicitadas, evidencias mínimas de cierre, fotos obligatorias del dron, intervalo y caducidad de la ubicación, plazos de disputa y de calificación, umbral de calificación y de tasa de cancelación para alertar, anticipo de vencimiento de documentos, catálogo de macrorregiones.

## 12. Notificaciones

Mecanismo del ADR 0025 con preferencias del ADR 0041: un aviso por cuenta destinataria al emitir el evento, in-app y push del sistema operativo según las preferencias de la cuenta.

| Evento | Destinatario | Se puede desactivar |
|---|---|---|
| `SolicitudAbierta` en las zonas de interés | Pilotos aprobados con el aviso activo | Sí (`nuevas_solicitudes`) |
| `OfertaRecibida`, `ContraofertaRecibida` | La contraparte | Sí (`ofertas`) |
| `SolicitudAdjudicada`, `OfertaCerrada` | Las partes de cada negociación | No |
| `EjecucionIniciada`, `AvanceRegistrado`, `SolicitudFinalizada` | Cliente | Sí (`avance`) |
| `MensajeEnviado` | La contraparte | Sí (`mensajes`) |
| `SolicitudExpirada`, `SolicitudCancelada` | Las partes involucradas | No |
| `ProveedorAprobado`, `ProveedorSuspendido`, `DocumentoPorVencer` | Proveedor | No |
| `EstadoDeAprobacionCambiado` de un cliente (aprobado, rechazado, suspendido) | Cliente | No |
| `VerificacionEnviada` (selfie y documento en revisión) | Administradores | No |
| `CalificacionRegistrada` | Proveedor | Sí (`calificaciones`) |
| `DisputaAbierta`, `DisputaResuelta` | Partes y soporte | No |
| `EmergenciaActivada` | Soporte (prioridad máxima) | No |

## 13. Superficies móviles y offline

| Flavor | Conexión | Qué guarda local |
|---|---|---|
| `cliente` | Online primero, sin outbox | Solo caché de lectura |
| `piloto` | Online para el mapa, las ofertas y el chat de lectura; offline en el lote | Inicio, avance, cierre, fotos y mensajes; outbox con `uuid_cliente` |

En el lote: todo registro nace con su `uuid_cliente` en el dispositivo, escribir es insertar local y encolar en una sola transacción, el orden causal lo da la `secuencia` local y no el reloj, y un registro ya confirmado por el servidor no se reescribe: se corrige con un registro nuevo con motivo y autor. Publicar, ofertar, contraofertar, aceptar, rechazar y retirar exigen red; se documenta como excepción en ADR 0028.

## 14. Métricas

- **Mercado**: solicitudes abiertas por día, tasa de adjudicación, tiempo hasta la primera oferta, tiempo hasta la adjudicación, ofertas por solicitud, diferencia entre precio propuesto y acordado, hectáreas servidas, solicitudes expiradas, demanda abierta por zona.
- **Calidad**: calificación promedio, tasa de disputas, incidencias por 1.000 hectáreas.
- **Oferta**: proveedores activos, drones aprobados, tasa de cancelación del proveedor, tiempo de aprobación de un proveedor.
- **Volumen**: monto final acumulado de los servicios finalizados e ingreso por hectárea acordado.
- **Sincronización**: antigüedad de la cola del outbox, tasa de reintentos, evidencias pendientes.

## 15. Alcance de la primera versión

Incluye: registro de cuentas de cliente y de proveedor (una persona, una cuenta de cada tipo), verificación de identidad y aprobación de clientes y proveedores con datos de la persona y del dron con fotos, propiedad y lotes en mapa, solicitud con precio por hectárea, mapa de solicitudes abiertas, negociación por rondas, adjudicación, ejecución con fotos y avance, ubicación del piloto, chat, precio final, calificación, disputas, incidencias y SOS, notificaciones con preferencias y métricas básicas. No incluye: cobro dentro de la app, comisión calculada por la plataforma, cobertura de seguro propia, ruta ni tiempo estimado de llegada, ni facturación electrónica.
