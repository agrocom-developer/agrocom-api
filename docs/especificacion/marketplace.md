# Especificación del marketplace de fumigación con drones

**Agrocom SRL · Documento oficial vigente · fuente de verdad funcional y técnica**

Agrocom es un marketplace de servicios de fumigación con drones. Un cliente publica una solicitud sobre su propiedad con un precio por hectárea; los pilotos la ven en un mapa, aceptan el precio o contraofertan; el cliente compara las ofertas, elige una y negocia hasta acordar. Agrocom es el nexo entre las partes y el registro del estado y del resultado. Lo operativo en el lote (cómo se trabaja, cuándo se vuela, qué se mezcla) lo acuerdan en persona el cliente y el piloto. El mercado es de oferta y demanda: el precio nace de la negociación.

Este documento define el producto. Las decisiones que lo sostienen están en `docs/decisiones/` (ADR 0027 a 0047), las reglas de operación en `docs/politicas/` y la lista exacta de recursos, estados y reglas para implementar en `docs/especificacion/negociacion-contrato.md`. Cuando un valor numérico figura aquí como «parámetro», vive en la configuración del sistema (ADR 0016) y un administrador puede ajustarlo sin desplegar.

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
| **Administrador** | Personal de Agrocom que ve todo el panel: verifica y aprueba clientes, proveedores, pilotos y drones; atiende incidencias, emergencias y reclamos; modera calificaciones; configura parámetros; administra usuarios y roles. | Web (panel) |
| **Operador** | Personal de Agrocom con acceso a partes específicas de algunos módulos del panel; el administrador define cuáles. | Web (panel) |

Hay tres tipos de cuenta (ADR 0046). La **cuenta de panel** es del personal de Agrocom, tiene un solo rol (Administrador u Operador) y las acciones se autorizan por los permisos de ese rol. La **cuenta de cliente** entra a la app `cliente` y la **cuenta de proveedor** a la app `piloto`: cada app admite un solo tipo, cada cuenta tiene su propio usuario y contraseña, su propia verificación de identidad y su propia aprobación, y no hay conversión de una en otra ni selector de rol. Un cliente que quiere ser proveedor se registra de nuevo en la app del piloto. Una persona real, identificada por su documento, tiene a lo sumo una cuenta de cliente y una de proveedor (§4.10). Una persona no ofrece sobre una solicitud propia, ni con su otra cuenta. Un administrador no modera lo que él mismo calificó.

## 3. Flujo de punta a punta

1. El cliente registra su **propiedad** y sus **lotes** en el mapa (geometría y hectáreas) una sola vez, con fotos del estado del terreno si quiere.
2. El cliente aprobado crea una **solicitud**: lotes a fumigar, hectáreas, cultivo, tipo de aplicación, producto, fecha deseada (que puede estar a semanas o meses), accesos, observaciones, requisitos opcionales (como la capacidad mínima del dron) y **precio por hectárea**, que pone él. Si pisa una ventana agronómica que se pierde (siembra, cosecha, plaga), puede declarar la solicitud **urgente** con una fecha límite (§6.16). La ubicación es el departamento y la provincia del terreno, que elige de un catálogo, más el municipio y la comunidad como texto. Por defecto la solicitud se ofrece a los proveedores que trabajan en ese departamento; si ninguno le convence, el cliente puede elegir «Buscar también en otros departamentos», al publicar o después (§6.17). Al publicarla queda `abierta`.
3. Los pilotos aprobados ven la solicitud en el **mapa de solicitudes abiertas**, con filtros por departamento, hectáreas, precio, fecha y cultivo. No hay zonas: el único filtro geográfico es el departamento. El piloto ve las solicitudes de los departamentos donde dice trabajar y, si el cliente las amplió, también las de otros departamentos, marcadas «En otro departamento»; decide si quiere ir y el viaje se acuerda en la negociación. Quien tiene activados los avisos los recibe por **oleadas** (§6.10): primero los proveedores propios de Agrocom y los mejor calificados que cumplen todos los filtros, luego, anillo por anillo, a más, y al final a todos los elegibles del alcance autorizado (la súper ola), sin avisar dos veces al mismo en un ciclo (ADR 0045). La negociación es **asíncrona**: los pilotos responden cuando pueden y no hay un chat en vivo. Un proveedor aún no aprobado no ve el mapa: ve solo cuántas solicitudes abiertas hay (§6.14).
4. Antes de ofertar, el piloto abre el detalle: lotes dibujados, hectáreas, cultivo, accesos, observaciones y fotos del terreno. No ve datos de contacto del cliente.
5. El piloto **acepta el precio propuesto** o **contraoferta** su precio por hectárea, indicando el dron con el que trabajará. Esa es su **aceptación** y llega a la **bandeja de entrada** del cliente (§6.11); ofertar no oculta la solicitud a los demás. Si nadie acepta, queda «Esperando piloto» y el cliente la relanza o la cancela (§6.10).
6. El cliente compara las ofertas (precio por hectárea y total, calificación, dron), ordenadas por defecto con los proveedores propios primero y luego por calificación, y **acepta una**, **contraoferta** o **rechaza**. La negociación avanza por rondas: en cada una solo una parte tiene la oferta vigente (ADR 0030). El cliente conversa con hasta 3 pilotos a la vez; las demás aceptaciones esperan en una cola que el cliente no ve y pasan a su bandeja cuando libera un lugar (§6.11). Cuando la conversación llega a un punto sólido, el cliente marca la solicitud **«En acuerdo»** y ya no se avisa a más pilotos.
7. Cuando una parte acepta la oferta vigente de la otra, la oferta se **adjudica**: la solicitud queda `adjudicada` con su precio acordado, las demás negociaciones se cierran y se abren los datos de contacto y el chat entre las dos partes.
8. Las partes acuerdan en persona cómo trabajan. El piloto marca **en ejecución**, sube fotos y marca su avance (porcentaje u hectáreas). El cliente ve las fotos, el avance, la última ubicación de la app del piloto y conversa con él por mensajes (ADR 0040).
9. El piloto **finaliza** con las hectáreas ejecutadas. El sistema calcula el **precio final** (hectáreas ejecutadas por precio acordado) y se muestra a ambos como el monto a pagar. La solicitud queda `finalizada`. El cliente, que es quien paga, tiene la última palabra: confirma el precio final o abre un reclamo.
10. El cliente califica al proveedor y la solicitud queda `calificada`; el proveedor califica al cliente en privado. Cualquiera puede abrir un reclamo dentro de su plazo.

## 4. Modelo de datos

Convenciones: dominio en español, prefijo de tabla por módulo (ADR 0011), borrado lógico (ADR 0007), importes y hectáreas en `DECIMAL`. Cada módulo escribe solo sus tablas y se comunica con los demás por contratos y eventos (ADR 0003). Prefijos: `sol_` (clientes, propiedades y solicitudes), `prv_` (proveedores), `flo_` (flota), `neg_` (negociación), `seg_` (seguimiento: avance, mensajes y ubicación), `cal_` (calificaciones), `dsp_` (reclamos).

### 4.1 Clientes y propiedades

- **`sol_clientes`**: `id`, `usuario_id`, `tipo` (`persona` | `empresa`), `razon_social`, `documento_fiscal`, `telefono`, `nombre_comercial`, `logo_ref` (ambos opcionales; `razon_social` y `documento_fiscal`, el NIT, son opcionales cuando el cliente es una persona), `estado` (el ciclo de aprobación de §5.2: nace `pendiente`), `motivo_estado`, `calificacion_promedio` (decimal 3,2), `calificaciones_conteo`.
- **`sol_cliente_contactos`**: personas de referencia del cliente. `id`, `cliente_id`, `nombre`, `telefono`, `cargo` (nulo), `es_principal`, baja lógica. Una empresa tiene al menos un contacto vigente; hay un solo principal vigente (el primero que se crea; si se da de baja, lo releva el más antiguo). La solicitud guarda `contacto_id` (`sol_solicitudes_servicio`): el elegido al crearla o, si no elige, el principal de la empresa. Tras adjudicar, el proveedor recibe el nombre, el teléfono y el cargo de esa persona, y la razón social de la empresa; antes de adjudicar no ve ninguno (ADR 0043).
- **`sol_terrenos`**: la propiedad o lote del cliente. `id`, `cliente_id`, `nombre`, `geometria` (polígono GeoJSON), `hectareas` (decimal 12,4), `cultivo`, `referencias_acceso`, `colindantes_sensibles` (texto: apiarios, cultivos orgánicos, viviendas), `departamento_codigo` y `provincia_codigo` (del catálogo; obligatorios para publicar), `municipio` y `comunidad` (texto libre), `estado_terreno` (texto breve: maleza, humedad, obstáculos, líneas eléctricas).
- **`sol_terreno_fotos`**: `id`, `terreno_id`, `archivo`, `hash`, `bytes`, `descripcion`. Imagen comprimida a menos de 300 KB, hasta 8 por terreno (`solicitudes.fotos_terreno.maximo_por_terreno`), en el disco privado. Las sube el cliente dueño y las ve el piloto en el detalle de la solicitud antes de ofertar, con una URL firmada de corta vida (15 minutos).
- **`sol_departamentos`** y **`sol_provincias`**: catálogo oficial de Bolivia (9 departamentos y 112 provincias, con el código del INE), de solo lectura y sin geometría. No es una zona administrable. Se publica en `GET /catalogos/ubicaciones`. El departamento es el único filtro geográfico (decide a quién llega la solicitud, §6.17); la provincia, el municipio y la comunidad informan al piloto.
- No hay precio de referencia: los precios los ponen el cliente y el proveedor (ADR 0045).

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
| `ventana_desde`, `ventana_hasta` | fecha | Fecha deseada, opcional; puede estar hasta `negociacion.anticipacion_maxima_dias` en el futuro. No vence la solicitud: esta sigue hasta que se adjudica o el cliente la cancela |
| `departamento_codigo`, `provincia_codigo`, `municipio`, `comunidad` | texto | Se copian del terreno al publicar (con varios terrenos, deben compartir departamento) y no cambian si el terreno se corrige después; nulos en `borrador`. Departamento y provincia son códigos del INE y la provincia debe ser del departamento |
| `alcance`, `alcance_ampliado_en` | texto, fecha y hora | `departamento` (por defecto: se ofrece a los proveedores que trabajan en su departamento) o `ampliado` (el cliente autorizó ofrecerla también en otros departamentos; `alcance_ampliado_en` es nulo mientras no está ampliada). Lo cambia solo el cliente, mientras no esté adjudicada (§6.17) |
| `requisitos` | JSON | Requisitos del cliente por clave de criterio, p. ej. `dron_capacidad_min_l`; nulos si no pide ninguno |
| `urgente`, `fecha_limite`, `motivo_urgencia` | booleano, fecha, texto | La urgencia la declara el cliente; el sistema no la deduce. `fecha_limite` (día de Bolivia) es obligatoria si `urgente`, dentro de `negociacion.urgencia.dias_maximos` (14) desde hoy, y distinta de la fecha deseada: esta no puede ser posterior. `motivo_urgencia` es opcional (`plaga`, `siembra`, `cosecha`, `clima`, `otro`). Sin `urgente`, los otros dos son nulos (CHECK). Ver §6.16 |
| `observaciones` | texto | Accesos, horarios, restricciones |
| `precio_ha_propuesto` | decimal 14,4 | Moneda del sistema (parámetro, bolivianos) |
| `justificacion_precio` | texto (500), nulo | Por qué el cliente propone ese precio; lo escribe solo el cliente y lo ve el proveedor (ADR 0049) |
| `precio_ha_acordado`, `monto_estimado` | decimal | El estimado es hectáreas solicitadas por el precio vigente (propuesto o, tras adjudicar, acordado); lo calcula el servidor y es de solo lectura |
| `hectareas_ejecutadas`, `porcentaje_avance`, `monto_final` | decimal | El avance lo marca el piloto; el monto final se congela al finalizar |
| `estado` | enum | Ver §5.1 |
| `oferta_adjudicada_id`, `proveedor_id`, `equipo_id` | FK nulas | Se fijan al adjudicar |
| `motivo_cancelacion`, `cancelada_por` | nulos | |

**`sol_solicitud_historial`**: `solicitud_id`, `estado_anterior`, `estado_nuevo`, `actor_usuario_id`, `origen`, `motivo`, `creado_en`. Solo se inserta.

### 4.3 Proveedores y personas

- **`prv_proveedores`**: `id`, `usuario_titular_id`, `tipo` (`propio` | `independiente` | `tercero`), `razon_social`, `documento_fiscal` (NIT), `nombre_comercial` y `logo_ref` (opcionales), `disponibilidad` (`disponible` | `ocupado` | `en_trabajo`, la fija el titular a mano; los dos últimos no reciben avisos de nuevas solicitudes), `disponibilidad_cambiada_en`, `disponibilidad_cambiada_por`, `estado` (§5.2), `calificacion_promedio`, `calificaciones_conteo`, `tasa_cancelacion`, `aprobado_por`, `aprobado_en`. Agrocom existe como un proveedor de tipo `propio`; compite en igualdad con los demás.
- **`prv_proveedor_departamentos`**: `proveedor_id`, `departamento_codigo` (`UNIQUE` por par). Los «departamentos donde trabaja» del proveedor, al menos uno, del catálogo `sol_departamentos`; los define el titular (el encargado, en una empresa) y rigen para todos sus pilotos (§6.17). Sin clave foránea entre módulos: se validan con el contrato `LecturaUbicaciones`.
- **`prv_pilotos`**: `id`, `proveedor_id`, `usuario_id` (los nombres, el documento y el teléfono son los de su `sec_personas` y su cuenta), `licencia_numero`, `licencia_vence`, `categoria_habilitacion`, `disponibilidad` (el interruptor propio del piloto de un proveedor tercero, con su fecha y autor del cambio; en un independiente rige el del proveedor), `puede_ofertar` (delegación de la oferta que da el encargado; apagada por defecto, con fecha y autor del cambio), `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`).
- **`prv_documentos`**: `id`, `sujeto_tipo` (`proveedor` | `piloto` | `dron`), `sujeto_id`, `tipo` (`identidad`, `licencia_piloto`, `habilitacion_dron`, `seguro_responsabilidad`, `registro_dron`, `certificado_fumigacion`, `antecedentes`, `otro`), `archivo_ref`, `vence_en`, `estado` (`en_revision` | `aprobado` | `rechazado` | `vencido`), `revisado_por`, `motivo_rechazo`. Ruta de archivo según ADR 0026.

### 4.4 Flota

- **`flo_drones`**: `id`, `dueno_proveedor_id`, `operador_proveedor_id`, `marca`, `modelo`, `anio`, `numero_serie` (único), `capacidad_tanque_l` (decimal), `ancho_pasada_m` (decimal), `ciclos_bateria` (entero; único dato de mantenimiento que se conserva), `estado` (`pendiente` | `aprobado` | `suspendido` | `rechazado` | `baja`), `origen` (`flota_agrocom` | `vendido_por_agrocom` | `externo`).
- **`flo_dron_fotos`**: `id`, `dron_id`, `tipo` (`frontal`, `lateral`, `placa_serie`, `con_tanque`, `otra`), `archivo_ref`, `bytes`, `estado` (`en_revision` | `aprobada` | `rechazada`), `motivo_rechazo`. Un dron exige al menos las fotos obligatorias del parámetro (por defecto `frontal`, `lateral` y `placa_serie`) para aprobarse (ADR 0042). El cliente ve las aprobadas.
- **`flo_equipos`**: `id`, `proveedor_id`, `piloto_id`, `dron_id`, `vigente_desde`, `vigente_hasta`. Un equipo es la combinación operativa que ejecuta una solicitud. Un piloto y un dron no pueden estar en dos equipos vigentes a la vez.

### 4.5 Negociación

- **`neg_ofertas`**: una fila por oferta de una negociación. `id`, `uuid_cliente`, `solicitud_id`, `proveedor_id`, `equipo_id`, `numero` (1, 2, 3… dentro de la negociación), `autor_rol` (`proveedor` | `cliente`), `autor_usuario_id`, `tipo` (`acepta_propuesta` | `contraoferta`), `precio_ha` (decimal 14,4), `mensaje` (hasta 280 caracteres; en la API, `nota`), `justificacion_precio` (hasta 500, nulo; la escribe solo el autor de la oferta, ADR 0049), `monto_estimado` (total que calcula el servidor), `es_definitiva` (bool), `estado` (§5.3), `vence_en`, `respondida_en`, `motivo`. La negociación es el conjunto de ofertas de un `(solicitud_id, proveedor_id)`; un índice único parcial garantiza una sola oferta `vigente` por negociación y otro una sola `aceptada` por solicitud.
- **`neg_negociaciones`**: una fila por negociación de un proveedor en una solicitud. `id`, `solicitud_id`, `proveedor_id`, `estado` (`en_cola` | `en_bandeja` | `en_conversacion` | `adjudicada` | `rechazada` | `retirada` | `vencida` | `cerrada`; §5.3), `casilla` (1 a `cupo_negociaciones` mientras ocupa un lugar de la bandeja; nula en cola y al cerrarse), `aceptada_en` (llegada, base del orden de la cola), `liberada_en`, `vista_en`, `aviso_entregado_en`, `cerrada_en`. Índice único parcial `(solicitud_id, proveedor_id)` entre las negociaciones activas (`en_cola`, `en_bandeja`, `en_conversacion`) y `UNIQUE (solicitud_id, casilla) WHERE casilla IS NOT NULL`: el cupo del cliente lo garantiza la base de datos. `neg_ofertas.negociacion_id` la referencia y `neg_ofertas.vence_en` es nulo mientras la oferta espera `en_cola`.
- **`neg_ofertas.incumple`**: lista de claves de requisito del cliente que el equipo de la oferta no cumple (p. ej. `dron_capacidad`); vacía si cumple todo. No bloquea la oferta: el cliente la ve marcada.
- **`neg_oleadas`**: una fila por solicitud con el estado del aviso por oleadas: `estado` (`activa` | `detenida` | `esperando` | `terminada`; `detenida` es la solicitud «En acuerdo» y `esperando` es «Esperando piloto»), `ciclo`, `paso`, `proxima_en`, `abierta_a_todos_en` (cuando salió la súper ola; «todos» son los elegibles del alcance autorizado, no otros departamentos si el cliente no amplió), `detenida_en`, `perfil` (`normal` | `urgente`, fijado por ciclo; §6.16) y la programación del relanzamiento que elige el cliente: `relanzamiento_dias`, `relanzamiento_hora`, `proximo_relanzamiento_en`, `ultimo_relanzamiento_en`.
- **`neg_oleada_pasos`**: lo que ocurrió en cada paso de cada ciclo: `solicitud_id`, `ciclo`, `paso`, `ejecutado_en`, `cumplen`, `nuevos_avisados`, `origen` (`reloj`, `relanzamiento`, `cliente`, `ampliacion` o `admin`) y `parametros` (lo que usó el paso: perfil, intervalo, criterios relajados). Solo se inserta.
- **`neg_oleada_avisos`**: a qué proveedor se avisó, en qué ciclo y paso: `solicitud_id`, `proveedor_id`, `ciclo`, `paso`, `avisado_en`, `entregado_en` (nulo hasta que el aviso sale). `UNIQUE (solicitud_id, proveedor_id, ciclo)`: nunca se avisa dos veces al mismo proveedor en un ciclo. Solo se inserta.
- **`neg_cancelaciones`**: registro informativo de cancelaciones tras adjudicar: `solicitud_id`, `parte` (`cliente` | `proveedor`), `motivo`, `creado_en`. Alimenta la tasa de cancelación visible; no genera sanción automática (ADR 0033).

### 4.6 Seguimiento del servicio

Las tablas de ejecución que el piloto escribe en el lote llevan `uuid_cliente`, `secuencia` local y `dispositivo_id`.

- **`seg_avances`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `tipo` (`inicio` | `avance` | `cierre`), `hectareas_ejecutadas` (decimal 12,4, acumuladas), `porcentaje` (decimal 5,2), `nota` (hasta 280 caracteres), `motivo_correccion` (obligatorio si el valor baja), `secuencia`, `registrado_en`. La escribe el piloto del equipo adjudicado.
- **`seg_evidencias`**: `id`, `uuid_cliente`, `solicitud_id`, `avance_id` (nulo), `autor_usuario_id`, `autor_rol` (`piloto`), `tipo` (`foto_avance`, `foto_final`, `foto_incidencia`), `archivo_ref`, `bytes`, `subida_en`. Imagen comprimida a menos de 300 KB antes de subir; cola de subida separada de los registros livianos.
- **`seg_mensajes`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `autor_rol` (`cliente` | `piloto`), `texto` (hasta 1000 caracteres), `creado_en`, `leido_en` (nulo hasta que lo lee la contraparte), `oculto_por` (nulo; un administrador oculta un mensaje reportado, con motivo). Se conservan hasta `retencion.servicio_meses` (12) después de que cierra el plazo de reclamo y de calificación; al eliminar su cuenta el autor, el texto se borra y el autor pasa a «Cuenta eliminada» (ADR 0047).
- **`seg_ubicaciones`**: una fila por solicitud, la última: `solicitud_id` (único), `latitud`, `longitud`, `precision_m`, `capturado_en`, `recibido_en`. Se elimina al finalizar o cancelar (ADR 0040).
- **`seg_bloqueos`** (ADR 0047): `id`, `bloqueador_usuario_id`, `bloqueado_usuario_id`, `bloqueado_proveedor_id` (nulo; la empresa o el independiente cuando bloquea el cliente), `solicitud_id` (donde ocurrió), `creado_en`, `deshecho_en`. Un bloqueo vigente por par. Cierra el chat de esa solicitud y la negociación en curso, y saca a la pareja del mapa, de los avisos y de las ofertas (criterio `sin_bloqueo`); no cancela un servicio adjudicado.
- **`seg_incidencias`**: `id`, `uuid_cliente`, `solicitud_id`, `reportada_por_usuario_id`, `tipo` (§9.3), `severidad` (`informativa` | `grave` | `emergencia`), `descripcion`, `ubicacion` (latitud, longitud, precisión y hora; solo si la persona pulsa el SOS o reporta, y se elimina 90 días después de cerrar la incidencia o su reclamo, ADR 0047), `ubicacion_purgada_en`, `estado` (`abierta` | `en_atencion` | `cerrada`).
- **`neg_pagos_confirmados`**: `uuid_cliente`, `solicitud_id` (único), `confirmado_por_usuario_id`, `monto` (decimal 14,2, copia del monto final al confirmar), `metodo` (`efectivo` | `transferencia` | `qr` | `otro`), `nota`, `confirmado_en`. Registro informativo del proveedor de que recibió el pago; lo escribe solo el titular del proveedor adjudicado, no se reescribe y no cambia el estado.

### 4.7 Calificaciones

**`cal_calificaciones`**: `id`, `uuid_cliente`, `solicitud_id`, `autor_usuario_id`, `autor_rol` (`cliente` | `proveedor`), `sujeto_tipo` (`proveedor` | `piloto` | `cliente`), `sujeto_id`, `estrellas` (entero 1 a 5), `etiquetas` (lista de un catálogo cerrado), `comentario`, `estado` (`visible` | `en_moderacion` | `oculta`), `moderada_por`, `motivo_moderacion`. Única por `(solicitud_id, autor_rol, sujeto_tipo, sujeto_id)`. La escribe un solo actor y nunca se edita: moderar cambia `estado`, no el contenido.

### 4.8 Reclamos

- **`dsp_disputas`**: `id`, `solicitud_id`, `tipo` (`servicio` | `conducta`; el de conducta nace al reportar un mensaje, la conversación o una foto, sin monto, ADR 0047), `objeto_tipo` y `objeto_id` (el mensaje, la conversación o la foto reportada; solo en `conducta`), `consentimiento_en` (el reportante acepta que soporte lea lo reportado), `abierta_por_rol` (`cliente` | `proveedor`), `motivo` (§8.3; en `conducta`: `acoso_o_insultos`, `amenazas`, `contenido_sexual_o_violento`, `datos_de_terceros`, `estafa_o_pago_fuera`, `suplantacion`, `otro`), `detalle`, `monto_en_disputa`, `estado` (§5.5), `resolucion` (`a_favor_cliente` | `a_favor_proveedor` | `parcial` | `sin_lugar`; en `conducta`: `sin_lugar` | `advertencia` | `contenido_ocultado` | `cuenta_suspendida`), `monto_ajustado` (nuevo precio final tras la resolución), `resuelta_por`, `resuelta_en`.
- **`dsp_mensajes`**: `disputa_id`, `autor_usuario_id`, `texto`, `creado_en`.

### 4.9 Transversales

- **`ntf_notificaciones`** (ADR 0025): un aviso por cuenta destinataria.
- **`ntf_preferencias`** y **`ntf_dispositivos`** (ADR 0041): preferencias por categoría, filtros de aviso del proveedor (`filtros`: hectáreas mínimas, tipos de aplicación que no quiere y si recibe solicitudes urgentes; los departamentos donde trabaja son del proveedor, no un filtro de aviso) y token de push por dispositivo. Un token está activo en una sola cuenta a la vez; el envío al sistema operativo va detrás de la interfaz `EnviadorDePush`, sin proveedor configurado todavía.
- **`sec_user.foto_perfil_ref`**: foto de perfil de la cuenta (cliente, titular o piloto), menos de 300 KB, en el disco privado. No es la selfie de verificación (§4.10).
- **`cfg_parametros`** (ADR 0016): parámetros de §11.
- **`sec_*`**: usuarios, roles y permisos (ADR 0004, ADR 0046), personas y verificaciones (§4.10).
- **`aud_bitacora`**: auditoría de cambios (ADR 0007).

### 4.10 Cuentas, personas y verificación (ADR 0046)

- **`sec_user`**: la cuenta (credenciales, foto de perfil, estado). `telefono` (E.164, obligatorio en las cuentas de app; referencia principal de autenticación) con `telefono_verificado_en` (nulo mientras no haya prueba de posesión), y `email` (correo de respaldo, obligatorio en las cuentas de app) con `email_verificado_en`. Índices únicos parciales `(telefono, tipo_cuenta)` y `(email, tipo_cuenta)` entre cuentas no eliminadas: no hay dos cuentas de cliente con el mismo teléfono ni dos de proveedor, pero el mismo teléfono sirve a una de cada tipo. `tipo_cuenta` vale `panel`, `cliente` o `proveedor`. `persona_id` apunta a `sec_personas` y es obligatorio en las cuentas de app. Las cuentas `panel` tienen un solo rol (`administrador` u `operador`) en `sec_user_role`; las cuentas de app no tienen roles. Índice único parcial `(persona_id, tipo_cuenta)`: una persona tiene a lo sumo una cuenta de cliente y una de proveedor.
- **`sec_personas`**: la persona real, con `documento_tipo` (`ci` | `pasaporte` | `licencia_conducir`) y `documento_numero` (normalizado) **únicos**, `nombres`, `apellidos` y `fecha_nacimiento` (nulo; la pide el registro del proveedor); el teléfono es de la cuenta, no de la persona. Los mismos datos sirven a sus dos cuentas sin duplicarse. Registrarse con un teléfono o un documento que ya tiene una cuenta de ese tipo responde `409 cuenta_ya_registrada` sin crear nada; el registro en el otro tipo enlaza la misma persona sin sobrescribir sus datos y sin reutilizar la verificación.
- **`sec_verificaciones`**: una por cuenta (`usuario_id`). `estado` (`en_revision` | `aprobada` | `rechazada` | `reemplazada`), `selfie_ref`, `selfie_capturada_en`, `selfie_origen` (`camara` | `administrador`), `documento_ref`, `documento_origen` (`cuenta` | `administrador`), `es_marcador` (el administrador no adjuntó documento de la persona), `motivo_alta_administrador`, `revisado_por`, `revisado_en`, `motivo_rechazo`. El tipo y el número del documento son los de la persona. Una vigente por cuenta; reenviar crea otra fila. Los archivos van al disco privado (ADR 0026) y solo los sirve el panel con `seguridad.verificacion.ver`; cada acceso queda en la bitácora. Se conservan mientras exista la cuenta; después, 12 meses si hubo antecedentes o una baja administrativa y 90 días si la persona eliminó su cuenta sin antecedentes (las rechazadas o reemplazadas, 90 días); luego una tarea diaria elimina los archivos y deja la fila sin referencias (ADR 0046, punto 33; ADR 0047).
- **`sec_codigos_verificacion`**: código de 6 dígitos (hash) para `verificar_telefono`, `recuperar_acceso`, `cambiar_contrasena`, `cambiar_telefono` y `eliminar_cuenta` por el canal `whatsapp`, `sms` o `correo`, con `vence_en` (10 minutos), `intentos` y `usado_en`. WhatsApp y SMS salen por la interfaz `EnviadorDeCodigo` y el adaptador configurado; sin adaptador, `503 canal_no_disponible`. No se escribe en logs ni en la bitácora; la fila guarda cuándo se pidió y cuándo se confirmó.
- **`sec_aceptaciones`**: la aceptación de la versión vigente de los términos y de la privacidad por cuenta (`documento`, `version`, `aceptado_en`, `app`, `uuid_dispositivo`); solo se inserta.
- **`sec_eliminaciones`** (ADR 0047): la solicitud de eliminar una cuenta. `usuario_id`, `tipo_cuenta`, `origen` (`app` | `web` | `oficina`), `estado` (`solicitada` | `pospuesta` | `cancelada` | `ejecutada`), `solicitada_en`, `ejecutar_desde` (la gracia es de 7 días), `ejecutada_en`, `cancelada_en`, `motivo_posposicion`, `con_antecedentes` y sus códigos, `retencion_hasta` y un `resumen` de conteos. Nunca guarda nombre, teléfono, correo ni documento. La tarea `EjecutarEliminacionesDeCuenta` anonimiza en el lugar (no pasa por `respaldo_*`).
- **`sec_registro_antifraude`** (ADR 0047): lo único que sobrevive a una eliminación: `huella` (HMAC del documento con una clave del servidor), `motivo`, `tipo_cuenta`, `registrado_en` y `vence_en` (12 meses sin antecedentes, 36 con ellos). Sin imágenes, nombre, teléfono ni correo. Al registrarse, una huella con un motivo distinto de `eliminacion_voluntaria` marca la cuenta «documento con antecedentes» para quien revisa; nada es automático.
- **`prv_vinculos_piloto`**: el vínculo de un piloto con una empresa (`estado`: `invitado` | `solicitado` | `activo` | `rechazado` | `liberado`), con quién lo inició y quién lo liberó; el piloto tiene su cuenta propia y el encargado de la empresa (su titular) gestiona el vínculo (ADR 0046, puntos 34 a 38).
- **`flo_equipo_integrantes`**: `equipo_id`, `nombre`, `celular` (nulo), `orden`. Datos informativos de las personas que forman el equipo del servicio, sin cuenta ni página propia; el cliente los ve solo tras adjudicar, junto al piloto (marcado como tal).
- **Perfil de la cuenta.** La cuenta `cliente` es la fila de `sol_clientes`; la cuenta `proveedor` es la fila de `prv_proveedores` (titular) o de `prv_pilotos` (piloto con cuenta propia de un tercero). El estado de la cuenta es el de esa fila.

## 5. Máquinas de estado

### 5.1 Solicitud de servicio

`borrador` → `abierta` → `con_ofertas` → `adjudicada` → `en_ejecucion` → `finalizada` → `calificada`. Más `cancelada` y `disputada`. Una solicitud sin adjudicar no vence sola.

| Estado | Significado | Transiciones (actor) |
|---|---|---|
| `borrador` | El cliente la arma. | `abierta` (cliente, al publicar), `cancelada` (cliente) |
| `abierta` | Publicada, visible en el mapa de los pilotos que le corresponde (§6.12), sin aceptaciones en la bandeja del cliente. | `con_ofertas` (sistema, cuando una aceptación llega a la bandeja), `en_acuerdo` (cliente), `adjudicada` (cliente o piloto al aceptar), `cancelada` (cliente) |
| `con_ofertas` | Hay al menos una aceptación en la bandeja o en conversación. Sigue ofertándose a los demás. | `abierta` (sistema, cuando no queda ninguna), `en_acuerdo` (cliente), `adjudicada`, `cancelada` (cliente) |
| `en_acuerdo` | El cliente la marcó firme: ya no se avisa a más pilotos y la cola está congelada. Sigue conversando con quienes ya tiene (§6.11). | `abierta` o `con_ofertas` (cliente, «Volver a buscar», o sistema si cae la última conversación), `adjudicada`, `cancelada` (cliente) |
| `adjudicada` | Hay precio acordado y un equipo. Se abren contacto y chat. | `en_ejecucion` (piloto), `abierta` (proveedor cancela), `cancelada` (cliente) |
| `en_ejecucion` | El piloto marcó el inicio. Hay fotos y avance. | `finalizada` (piloto, al cerrar con evidencia subida) |
| `finalizada` | Terminada; muestra el precio final a pagar. Se puede calificar y reclamar. | `calificada` (sistema, al calificar el cliente), `disputada` (cliente o proveedor) |
| `calificada` | El cliente calificó al proveedor. | `disputada` (dentro del plazo) |
| `disputada` | Hay un reclamo abierto. | `finalizada` o `calificada` (la que tenía), `cancelada` (reembolso total) |
| `cancelada` | Terminal; guarda quién y por qué. | — |

La **etapa** que ve el cliente («Buscando piloto», «Con aceptaciones», «Esperando piloto» o «En acuerdo») se deduce del estado y de la oleada: `en_acuerdo` es un estado, y «Esperando piloto» es una `abierta` cuya oleada espera su relanzamiento (§6.10 y §6.11). El detalle de reglas y guardas de cada transición está en `negociacion-contrato.md`.

### 5.2 Cliente, proveedor, piloto y dron

La cuenta de cliente y la de proveedor tienen cada una su estado; el cliente nace `pendiente`, como el proveedor. Una cuenta `aprobada` exige su verificación de identidad `aprobada` (selfie con cámara y documento: CI, licencia de conducir o pasaporte; ADR 0046). Con la cuenta `pendiente`, `rechazada`, `suspendida` o en `baja` la persona entra y ve las pantallas de su app, pero el servidor rechaza con `403` (`cuenta_no_aprobada`) publicar, aceptar y contraofertar en el cliente, y ofertar, contraofertar y aceptar en el proveedor. El alta que hace un administrador desde el panel la puede aprobar el mismo administrador; nadie aprueba su propia cuenta. El proveedor que aún no está aprobado no ve el mapa, los lotes, los clientes ni ninguna ubicación: ve solo un contador de solicitudes abiertas, filtrable por departamento, para saber cuántos trabajos pierde (ADR 0045, punto 29; ADR 0046, punto 12).

`pendiente` → `aprobado` ⇄ `suspendido`; `pendiente` → `rechazado` (el solicitante puede reenviar); cualquiera → `baja`. Solo un administrador aprueba, suspende o da de baja. Un proveedor no ve el mapa de solicitudes abiertas ni ofrece mientras no esté `aprobado` y tenga al menos un equipo con piloto y dron aprobados y documentos vigentes; hasta entonces ve solo cuántas solicitudes abiertas hay (§6.14). Un documento vencido suspende de forma automática al sujeto hasta que se cargue uno vigente. Un dron se aprueba solo con las fotos obligatorias aprobadas (ADR 0042).

### 5.3 Oferta

`vigente` → `aceptada` | `reemplazada` | `rechazada` | `retirada` | `vencida` | `cerrada`.

- `aceptada`: la contraparte la aceptó; es el acuerdo y adjudica la solicitud.
- `reemplazada`: la contraparte respondió con una contraoferta.
- `rechazada`: la contraparte la rechazó; cierra la negociación.
- `retirada`: su autor la retiró.
- `vencida`: pasó su `vence_en` sin respuesta.
- `cerrada`: la solicitud se adjudicó a otra negociación o se canceló.

Una oferta en la cola está `vigente` y su `vence_en` es nulo hasta que llega a la bandeja. El estado de la negociación (en cola, en bandeja, en conversación y los cierres) está en `neg_negociaciones.estado`. Solo una oferta por solicitud llega a `aceptada`; la aceptación es atómica sobre la solicitud (bloqueo de fila) y el segundo intento recibe conflicto.

### 5.4 Seguimiento

El avance es un registro de `seg_avances`: `inicio` (pasa la solicitud a `en_ejecucion`), `avance` (hectáreas acumuladas o porcentaje) y `cierre` (hectáreas ejecutadas finales). El servidor valida cada registro por `uuid_cliente`, en orden de `secuencia`. El cierre deja la solicitud en `en_ejecucion` con la marca `cierre_solicitado_en` hasta que al menos `seguimiento.evidencias_minimas_cierre` fotos de avance estén subidas; entonces pasa a `finalizada` y calcula el precio final.

### 5.5 Reclamo

`abierta` → `en_revision` → `resuelta`; `abierta` → `retirada` (por quien lo abrió). Un solo reclamo de servicio abierto por solicitud; el reclamo de conducta (`tipo = conducta`) sigue `abierta` → `en_revision` → `resuelta` y no cambia el estado de la solicitud.

### 5.6 Incidencia

`abierta` → `en_atencion` → `cerrada`. Una incidencia de severidad `emergencia` notifica a soporte en el acto.

## 6. Reglas de negociación

Detalle de la decisión en ADR 0030 y reglas exactas en `negociacion-contrato.md`. Resumen:

1. **Quién ofrece.** Un proveedor aprobado con un equipo apto puede ofrecer sobre cualquier solicitud `abierta` o `con_ofertas` que le sea visible (§6.12). En una empresa ofrece el encargado, que puede delegar la oferta a pilotos concretos (apagada por defecto); un independiente ofrece por sí mismo. Ofrece aceptando el precio propuesto (`acepta_propuesta`) o con otro precio (`contraoferta`), y elige el equipo (dron) que usará.
2. **Una negociación por proveedor y solicitud.** Cada proveedor tiene su propia negociación con el cliente; las ofertas de otros proveedores no se ven entre sí (el piloto solo ve cuántas negociaciones tiene el cliente en su bandeja o en conversación, nunca la cola).
3. **Rondas.** Las ofertas de una negociación se numeran 1, 2, 3… y alternan autor: la primera la hace el proveedor, la siguiente el cliente, y así. Quien recibe una oferta vigente puede aceptarla, contraofertar o rechazarla. Contraofertar reemplaza la oferta vigente. La negociación admite hasta `negociacion.max_ofertas_por_negociacion` ofertas (parámetro, 5 por defecto); la última se marca `definitiva` y solo se acepta o se rechaza.
4. **Adjudicación.** Cuando una parte acepta la oferta vigente de la otra, la oferta queda `aceptada`, la solicitud pasa a `adjudicada` con `precio_ha_acordado`, `proveedor_id` y `equipo_id`, y las demás ofertas vigentes de la solicitud pasan a `cerrada`.
5. **Vigencia.** Cada oferta vence a las `negociacion.vigencia_oferta_horas` (72 por defecto, admite horas, días o semanas) de llegar a la bandeja del cliente; en la cola no vence. La **solicitud no vence sola**: sigue en el mercado hasta que se adjudica o el cliente la cancela, aunque tarde semanas; no hay `vence_en`, estado `expirada` ni renovación. Puede llevar una fecha deseada hasta `negociacion.anticipacion_maxima_dias` (365) adelante. Mientras nadie acepta, el servidor avisa solo al cliente cada `negociacion.pendiente.recordatorio_dias` (7) cuánto lleva sin respuesta, con las opciones de modificarla o retirarla, y si pasa la fecha deseada le avisa y deja de relanzarla sola hasta que la actualice. En una solicitud urgente vigente manda la fecha límite, no la deseada (§6.16).
6. **Precio.** El precio por hectárea es un decimal positivo con un máximo de sanidad (parámetro). No hay precio de referencia: lo ponen el cliente y el proveedor. El **total** (hectáreas × precio) lo calcula el servidor, es de solo lectura para ambas partes y viaja en la solicitud y en cada oferta (`monto_estimado`). Cada parte puede explicar su precio con una **justificación** opcional de hasta 500 caracteres (`justificacion_precio`), visible para la contraparte: el cliente la escribe en su solicitud y cada autor en su oferta o contraoferta; nadie edita la de otro (ADR 0049).
7. **Sin penalidad automática.** Rechazar, retirar o dejar vencer una oferta no penaliza. Cancelar tras adjudicar queda registrado y visible en la tasa de cancelación, y un administrador decide si actúa (ADR 0033).
8. **Visibilidad previa a ofertar.** El piloto ve lotes, hectáreas, cultivo, accesos, observaciones y fotos del terreno. No ve nombre, teléfono ni documento del cliente hasta la adjudicación.
9. **Elección del cliente.** El comparador muestra, por oferta: precio por hectárea, total estimado, calificación y servicios del proveedor, modelo y fotos del dron, vigencia y, si el equipo no cumple un requisito del cliente, qué requisito. Orden recomendado por defecto: primero los proveedores propios de Agrocom, luego mayor calificación, luego menor precio; el cliente lo cambia a precio, calificación o más reciente, y puede filtrar por rango de precio por hectárea, total máximo, calificación mínima, tipo de proveedor y capacidad mínima del dron (ADR 0049). El orden solo sugiere: el cliente decide, y no hay distancia calculada, que se acuerda en la negociación.
10. **Avisos por oleadas** (ADR 0045). La solicitud es una ola y los proveedores son planetas en anillos de prioridad. Al publicar, el sistema arma la lista de proveedores que cumplen **todos** los filtros y los avisa (primera ola; los propios de Agrocom entran desde ahí). Los filtros son de tres orígenes: del sistema (proveedor aprobado con equipo apto, disponible, calificación mínima del paso), del cliente (requisitos como la capacidad mínima del dron) del alcance (`departamento`: el proveedor trabaja en el departamento de la solicitud, o el cliente la amplió; §6.17) y del proveedor (hectáreas mínimas, tipos de aplicación que no quiere y solicitudes urgentes, desde los ajustes de su app); la lista de filtros crece por un mecanismo extensible y no está cerrada. Cada `negociacion.oleadas.intervalo_minutos` (T; 60 mientras no haya push), si nadie ofertó, se quita o afloja un filtro relajable en el orden de la escalera (calificación mínima 4,50, luego 4,00, luego sin mínimo, y por último el requisito de dron) y se avisa solo a los proveedores que ahora cumplen y no habían sido avisados. El último paso es la **súper ola**: todos los elegibles del alcance autorizado, sin abrir la solicitud a otros departamentos por sí sola. Los filtros del proveedor, la disponibilidad, la aprobación y el departamento no se relajan: un proveedor `ocupado` o `en_trabajo` no recibe avisos. Nadie recibe dos veces el mismo aviso dentro de un ciclo. Las oleadas y los relanzamientos siguen aunque haya aceptaciones: solo los detiene el cliente al marcar la solicitud «En acuerdo» (§6.11). Si nadie acepta, la solicitud queda «Esperando piloto»: el sistema no la desactiva ni la vence y le sugiere al cliente subir el precio o «Buscar también en otros departamentos» (§6.17); el cliente la relanza a mano (un ciclo nuevo desde el paso 0) o el sistema la relanza según el horario que él eligió (mínimo 2 días por semana, máximo un relanzamiento por día, a la hora que prefiera). Sin push, los avisos se ven en la lista de la app (campana).
11. **Bandeja, cupo, cola y «En acuerdo».** (ADR 0045, puntos 15 a 17 y 30 a 36.)
    - **Asíncrona.** La solicitud se envía y los proveedores responden cuando pueden; no hay chat en vivo. Ofertar no oculta la solicitud ni pausa las oleadas; abrir la pantalla de precios sin enviar nada no cambia nada.
    - **Bandeja y cupo.** Cada aceptación (la primera oferta de un proveedor) llega a la bandeja del cliente. El cliente conversa con hasta `negociacion.cupo_negociaciones` proveedores a la vez (3 por defecto). Si aceptan más, los excedentes esperan **en cola**, sin avisar al cliente ni mostrarle cuántos son. La cola se ordena por prioridad: propios de Agrocom, mayor calificación y orden de llegada (el precio no cuenta). Cuando el cliente rechaza o cierra una conversación, el proveedor retira o la oferta vence, pasan a la bandeja las siguientes de la cola hasta completar el cupo, tras revisar que cada proveedor siga disponible (no `ocupado` ni `en_trabajo`) y que la solicitud siga vigente. Al cliente nunca le llegan más aceptaciones que el cupo a la vez.
    - **Idempotencia.** Una aceptación se libera una sola vez y se avisa una sola vez: transición con comparación de estado, casilla única por solicitud, clave de aviso por negociación y reintento de avisos no entregados.
    - **Privacidad.** El proveedor ve «Tu oferta está en cola», «El cliente la está viendo» o «En conversación», nunca su posición, cuántos esperan ni datos de otros; el cliente nunca ve la cola.
    - **En acuerdo.** Con al menos una conversación abierta, el cliente marca la solicitud «En acuerdo» (estado `en_acuerdo`): ya no se avisa a más pilotos, la solicitud sale del mapa salvo para quienes tienen una negociación en ella, la cola se congela y las conversaciones siguen. El cliente puede «Volver a buscar»; si cae la última conversación, vuelve sola a `abierta`. A quienes conversan con el cliente o ya rechazó no se les vuelve a avisar la solicitud. Quien reaparece disponible días después puede aceptar mientras no esté en acuerdo.
12. **Quién ve la solicitud.** Un proveedor habilitado la ve si tiene una negociación en ella (en cola, en bandeja o en conversación) o si el cliente no lo rechazó y, y la solicitud está dentro de su alcance (trabaja en el departamento de la solicitud, o el cliente la amplió) y, o bien ya se abrió a todos los elegibles del alcance (cuando sale la súper ola, el último paso de la escalera; desde entonces también mientras está «Esperando piloto»), o bien una oleada lo alcanzó. Así los propios y los mejor calificados tienen la primera oportunidad y luego se abre al resto del alcance autorizado.
13. **Dron que no cumple.** Un equipo que no cumple un requisito del cliente puede ofertar: la oferta se marca y se le informa al cliente, que decide. No se bloquea.
14. **Proveedor aún no habilitado.** Una cuenta de proveedor cuyo proveedor está `pendiente`, o aprobado sin un equipo apto, no ve el mapa ni el detalle y no ofrece. Ve un **contador** (`GET /solicitudes-abiertas/contador`, con `departamento` opcional) con cuántas solicitudes abiertas le corresponderían por su alcance (las de los departamentos donde dice trabajar y las ampliadas), sin lotes, clientes ni ubicación detallada, para que sepa cuántos trabajos no puede ver todavía.
15. **Disponibilidad.** Cada cuenta de proveedor tiene su interruptor `disponible`, `ocupado` o `en_trabajo`; el titular y cada piloto de una empresa tienen el suyo y el encargado puede marcar a sus pilotos. Se avisa a una cuenta solo si su interruptor y el de su empresa están en `disponible`. Al volver a `disponible` no se le mandan avisos atrasados. Quien no está disponible sigue viendo el mapa y puede enviar una oferta, que espera en la cola hasta que vuelva a `disponible`.
16. **Solicitud urgente** (ADR 0045, punto K; primer modelo, el dueño dijo que «con la práctica entraremos a más detalle», así que lo fino es revisable).
    - **La declara el cliente**: interruptor `urgente` con `fecha_limite` obligatoria (dura, distinta de la fecha deseada, dentro de un tope de 14 días) y `motivo_urgencia` opcional. La app solo sugiere marcarla cuando la fecha deseada está a menos de 2 días; nunca la marca sola. La urgencia está vigente hasta el fin del día de la fecha límite y entonces pasa a vencida.
    - **Qué cambia para el piloto**: insignia «Urgente · faltan N días»; el mapa ordena por relevancia (urgentes primero) y tiene el filtro `urgentes`; avisos más frecuentes y prioritarios; y su filtro «Recibir solicitudes urgentes» (clave `recibir_urgentes`), que nunca se relaja en las oleadas y gobierna solo los avisos.
    - **Oleadas de urgencia**: el ciclo tiene perfil `urgente` con T propio (`negociacion.urgencia.intervalo_minutos`, 15), escalera acelerada (`negociacion.urgencia.escalera`) y relanzamiento diario por defecto, respetando el máximo de un ciclo nuevo por día. Sin recargo: el precio lo negocian las partes; si pasan varias olas sin aceptación, el servidor sugiere al cliente buscar también en otros departamentos, subir el precio, ampliar la fecha o quitar el requisito de dron.
    - **Pasada la fecha límite sin trato**, la solicitud no se cancela ni vence: dejan de enviarse olas, se avisa al cliente («Pasó tu fecha límite») y queda «Esperando piloto» hasta que actualice la fecha, quite la urgencia o la cancele.
    - **Administrador**: puede forzar una súper ola («Avisar ahora a todos»), con motivo y auditada en la bitácora; abre un ciclo propio, así que no avisa dos veces a la misma persona dentro de un ciclo, y respeta el alcance autorizado de la solicitud.
    - **Alcance**: la urgencia no amplía sola el alcance; solo adelanta la sugerencia «Buscar también en otros departamentos» (§6.17).
17. **Alcance por departamento** (ADR 0045, punto L). El mercado se parece a publicar un producto en Marketplace: la solicitud sigue vigente hasta que alguien la «compra», no hay zonas y el único filtro geográfico es el departamento.
    - **Alcance base.** Una solicitud llega solo a proveedores habilitados que trabajan en su departamento (criterio `departamento`, que la escalera nunca relaja). El proveedor declara en su cuenta «Departamentos donde trabaja» (selección múltiple del catálogo, al menos uno; los define el titular o, en una empresa, el encargado) con `PUT /proveedores/mi/departamentos`. Reemplaza al filtro de departamentos excluidos.
    - **Ampliación del cliente.** Si ninguno de los pilotos disponibles le convence, el cliente puede elegir «Buscar también en otros departamentos» (`alcance = ampliado`), al publicar o después (`PUT /solicitudes/{id}/alcance`), típicamente cuando está «Esperando piloto». Es una decisión solo suya: ni el sistema, ni la urgencia, ni el administrador la activan. Es reversible hasta que haya acuerdo; las negociaciones ya abiertas con pilotos de otro departamento no se cierran.
    - **Qué ve el piloto de otro departamento.** La solicitud con la indicación «En otro departamento: <nombre>» en el mapa, el detalle y el aviso. No necesita estar allí: el viaje y el costo se negocian. Ve lo mismo que cualquier otro antes de adjudicar y nada del cliente.
    - **Sugerencia.** Al quedar «Esperando piloto» (y antes en una urgente), el servidor agrega `ampliar_alcance` a las sugerencias del cliente, junto con subir el precio y ampliar la fecha.
    - **Sin zonas.** Ni zonas, ni regiones, ni radio ni distancia: el departamento es un catálogo oficial de solo lectura. Ampliar significa «todos los demás departamentos».

## 7. Eventos de dominio

`SolicitudAbierta`, `AlcanceAmpliado`, `AlcanceRestringido`, `UrgenciaDeclarada`, `FechaLimitePasada`, `AvisoForzadoPorAdministrador`, `OfertaRecibida`, `AceptacionLiberada`, `SolicitudEnAcuerdo`, `ContraofertaRecibida`, `OfertaRechazada`, `OfertaRetirada`, `OfertaVencida`, `SolicitudAdjudicada`, `OfertaCerrada`, `EjecucionIniciada`, `AvanceRegistrado`, `EvidenciaSubida`, `SolicitudFinalizada`, `SolicitudCancelada`, `MensajeEnviado`, `ProveedorAprobado`, `ProveedorSuspendido`, `DocumentoPorVencer`, `DocumentoVencido`, `CalificacionRegistrada`, `CalificacionModerada`, `DisputaAbierta`, `DisputaResuelta`, `IncidenciaReportada`, `EmergenciaActivada`, `EliminacionSolicitada`, `CuentaEliminada`, `ReporteDeConductaAbierto`, `CuentaBloqueada`. Cada evento lo emite el módulo dueño del hecho; los demás reaccionan por oyentes (notificaciones, métricas, reputación).

## 8. Políticas de servicio (resumen)

El texto completo vive en `docs/politicas/`.

### 8.1 Cancelación

Gratuita y sin registro antes de adjudicar. Tras adjudicar, el cliente o el proveedor pueden cancelar con motivo; la cancelación queda registrada y visible en la tasa de cancelación de quien la hace, sin penalidad en dinero ni sanción automática. Una cancelación del proveedor reabre la solicitud. Ver `cancelacion.md`.

### 8.2 Precio y pago

El precio por hectárea se negocia y queda congelado al adjudicar. El precio final es hectáreas ejecutadas por precio acordado. La app no procesa cobros ni calcula comisión; el pago se hace fuera de ella. Ver `precio_y_pago.md`.

### 8.3 Calificación y reclamos

El cliente califica al proveedor y al piloto de 1 a 5 estrellas con etiquetas; el proveedor califica al cliente en privado y el administrador modera. Motivos de reclamo: cobertura incompleta, mala aplicación, daño a cultivos o terceros, producto distinto al pactado, no se presentó el equipo, hectáreas ejecutadas discutidas, precio final discutido, cobro pendiente, acceso negado. Ver `calificacion.md` y `disputas.md`.

## 9. Seguridad y confianza

### 9.1 Verificación

Verificación de identidad de cada cuenta (selfie con cámara y documento de identidad, que se piden de nuevo en cada cuenta y se conservan según la retención B: 12 meses con antecedentes o baja administrativa, 90 días tras una eliminación sin antecedentes), documentos obligatorios por sujeto, fotos del dron, revisión manual y vencimientos con aviso previo. Ver `verificacion_proveedores.md`.

### 9.2 Privacidad y visibilidad

Quién ve qué en cada estado está en `docs/politicas/seguimiento_y_privacidad.md`. La ubicación del piloto se comparte solo mientras la solicitud está `en_ejecucion`, se guarda solo la última posición y se elimina al finalizar; la única otra ubicación que se guarda es la de una emergencia o incidencia, solo si la persona pulsa el SOS o reporta, y se elimina 90 días después de cerrarla (ADR 0047). La eliminación de una cuenta, qué se anonimiza y qué se conserva, está en `docs/politicas/eliminacion_de_cuenta.md`; reportar y bloquear en el chat, en `seguimiento_y_privacidad.md`.

### 9.3 Incidencias y emergencias

Tipos: `deriva_de_producto`, `dron_caido`, `lesion`, `fuga_o_derrame`, `intrusion_de_personas_o_animales`, `conflicto_con_cliente`, `conflicto_con_proveedor`, `falla_de_equipo`, `otro`. El botón de emergencia (SOS) está en las apps del piloto y del cliente: avisa a soporte con la ubicación (la del dispositivo en el piloto; la del terreno del servicio en el cliente, que no pide permiso de ubicación), abre una incidencia de severidad `emergencia` y muestra el número de emergencias locales. El SOS no sustituye llamar a los servicios de emergencia. Ver `seguridad.md`.

### 9.4 Desactivación

Documentos vencidos, fraude o incidencia grave probada suspenden al proveedor, con aviso previo, motivo y vía de apelación. Una calificación o una tasa de cancelación bajo el umbral genera una alerta al administrador, que decide. Ver `calificacion.md` y `verificacion_proveedores.md`.

### 9.5 Soporte

Canal por la app y por la web, con cola de casos por severidad. Una incidencia `emergencia` se atiende de inmediato; un reclamo recibe primera respuesta en 24 horas hábiles (parámetro).

## 10. Endpoints

Prefijo `/api`. Autenticación por token de dispositivo (`POST /auth/token`). El contrato publicado es `docs/api/openapi.yaml`, generado desde el código (ADR 0014). La lista completa con payloads, errores y reglas está en `negociacion-contrato.md`; los verbos de abajo definen la superficie.

### 10.0 Cuenta y verificación

Cada flavor usa estos endpoints con su tipo de cuenta (ADR 0046).

| Método y ruta | Función |
|---|---|
| `POST /auth/token`, `GET /auth/sesion` | Login por dispositivo, sin rol, con la app de origen (`app`); el usuario puede ser el nombre de usuario o el teléfono. Devuelven `cuenta` (tipo, estado, teléfono y lo que falta para aprobarla), `persona`, `verificacion` y `legal_pendiente`. `403 tipo_de_cuenta_incorrecto` si la cuenta es de la otra app |
| `POST /clientes/registro`, `POST /proveedores/registro` | Alta pública de la cuenta con teléfono, correo de respaldo, los datos de la persona y la versión de términos y privacidad aceptada; `409 cuenta_ya_registrada` si ese teléfono o ese documento ya tiene una cuenta de ese tipo; `422 version_legal_desactualizada` |
| `POST /auth/recuperar`, `POST /auth/recuperar/confirmar` | Recuperación desde la app: pide un código por el canal que la persona elige (`whatsapp`, `sms` o `correo`; respuesta siempre igual) y, con el código, fija la contraseña nueva y devuelve el usuario |
| `POST /cuenta/contrasena/codigo`, `PUT /cuenta/contrasena` | Cambio de contraseña con la sesión iniciada: código por el canal elegido y contraseña nueva; revoca los demás dispositivos |
| `POST /cuenta/telefono/verificacion`, `POST /cuenta/telefono/verificacion/confirmar` | Verificación del teléfono con un código por WhatsApp (o SMS) |
| `POST /auth/renovar` | Renueva el token del dispositivo antes de que caduque (la sesión por biometría es local; el servidor no recibe biometría) |
| `POST /cuenta/telefono`, `POST /cuenta/telefono/confirmar` | Cambio de teléfono con un código al teléfono nuevo (o al correo de respaldo); el teléfono nuevo cumple la unicidad por tipo |
| `GET /cuenta/eliminacion`, `POST /cuenta/eliminacion`, `POST /cuenta/eliminacion/codigo`, `POST /cuenta/eliminacion/cancelar` | Estado y motivos que la impiden, código por el canal elegido, solicitud (contraseña o código) y cancelación durante la gracia; `409 eliminacion_bloqueada` con los `motivos`; mientras está solicitada, la sesión responde `403 cuenta_en_eliminacion` salvo estas rutas y el cierre de sesión (ADR 0047) |
| `GET /bloqueos`, `POST /bloqueos`, `DELETE /bloqueos/{id}` | Cuentas bloqueadas, bloquear a la otra parte de un chat o negociación y deshacerlo |
| `GET /legal/vigentes`, `POST /cuenta/aceptaciones` | Versiones y direcciones vigentes de términos y privacidad (público); registro de la aceptación |
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
| `GET /catalogos/ubicaciones` | Departamentos y provincias de Bolivia para ubicar el terreno (sin token) |
| `GET /catalogos/urgencia` | Tope de días de la fecha límite, umbral de sugerencia y motivos de urgencia (sin token) |
| `GET /solicitudes/{id}/pago-confirmado` | Si el proveedor informó que recibió el pago |
| `PUT/DELETE /perfil/foto` | Foto de perfil de la cuenta |
| `POST /solicitudes`, `PUT /solicitudes/{id}` | Crea (idempotente por `uuid_cliente`, con `contacto_id` opcional) y edita el borrador |
| `GET /solicitudes`, `GET /solicitudes/{id}` | Lista y detalle con avance y precio |
| `POST /solicitudes/{id}/publicar`, `/cancelar` | Abre la solicitud o la cancela |
| `PUT /solicitudes/{id}/ajustes` | Cambia requisitos y fecha deseada mientras no hay aceptaciones en la bandeja ni en conversación; declara, cambia o quita la urgencia (`urgente`, `fecha_limite`, `motivo_urgencia`) mientras no está adjudicada |
| `POST /solicitudes/{id}/precio` | Cambia el precio propuesto mientras no está adjudicada |
| `PUT /solicitudes/{id}/alcance` | «Buscar también en otros departamentos»: amplía el alcance a los demás departamentos o lo vuelve a su departamento, mientras no está adjudicada |
| `POST /solicitudes/{id}/acuerdo`, `/acuerdo/reabrir` | Marca la solicitud «En acuerdo» (ya no se avisa a más pilotos) o vuelve a buscar |
| `GET /bandeja`, `POST /ofertas/{id}/vista` | Aceptaciones nuevas en la bandeja del cliente (nunca la cola) y marca de vista |
| `POST /solicitudes/{id}/busqueda/relanzar`, `PUT /solicitudes/{id}/busqueda/programacion` | Vuelve a lanzar la ola de una solicitud «Esperando piloto» y elige los días (mínimo 2 por semana) y la hora del relanzamiento automático |
| `GET /solicitudes/{id}/ofertas` | Comparador de ofertas con orden recomendado (`orden=recomendado\|precio\|calificacion\|reciente`) y filtros (`precio_ha_min`, `precio_ha_max`, `monto_max`, `calificacion_min`, `tipo_proveedor`, `capacidad_tanque_min`) |
| `POST /ofertas/{id}/aceptar`, `/contraofertar`, `/rechazar` | Respuesta del cliente a una oferta |
| `GET /solicitudes/{id}/avance`, `GET /solicitudes/{id}/ubicacion-piloto` | Fotos, avance y última ubicación |
| `GET/POST /solicitudes/{id}/mensajes` | Chat con el piloto adjudicado |
| `GET /solicitudes/{id}/precio-final`, `POST /solicitudes/{id}/precio-final/confirmar` | Ver y confirmar el precio final del servicio cerrado |
| `GET/POST /solicitudes/{id}/calificaciones` | A quién calificar y calificar al proveedor o al piloto (7 días) |
| `POST /solicitudes/{id}/disputas`, `GET /solicitudes/{id}/disputa`, `GET /disputas`, `GET /disputas/{id}`, `POST /disputas/{id}/mensajes`, `/retirar`, `/apelar` | Reclamo |
| `POST /incidencias`, `POST /emergencias` | Reporte y SOS |
| `POST /solicitudes/{id}/mensajes/{mensaje}/reportar`, `POST /solicitudes/{id}/chat/reportar`, `POST /solicitudes/{id}/evidencias/{foto}/reportar` | Reportar un mensaje, la conversación o una foto de avance; crea un reclamo de conducta (en línea, sin outbox) |

### 10.2 Proveedor

| Método y ruta | Función |
|---|---|
| `POST /proveedores/registro` | Alta con datos de la persona y del dron (queda `pendiente`) |
| `POST /proveedores/documentos` | Carga documentos |
| `GET/POST/PUT /proveedores/pilotos`, `/drones`, `/equipos`; `POST/GET/DELETE /proveedores/drones/{id}/fotos` | Personal y flota con fotos del dron |
| `GET /solicitudes-abiertas`, `/solicitudes-abiertas/{id}` | Mapa con filtro por departamento y `urgentes`, orden por relevancia (urgentes primero) y detalle previo a ofertar (solo proveedor habilitado); cada solicitud trae `en_otro_departamento` |
| `GET /solicitudes-abiertas/contador` | Cuántas solicitudes abiertas le corresponden por su alcance, para el proveedor aún no habilitado; filtro opcional por departamento |
| `PUT /proveedores/mi/departamentos` | Los «departamentos donde trabaja» del proveedor (al menos uno del catálogo); el titular o, en una empresa, el encargado |
| `PUT /proveedores/mi/disponibilidad`, `PUT /proveedores/pilotos/{id}/disponibilidad` | La cuenta se marca `disponible`, `ocupado` o `en_trabajo`; el encargado marca a un piloto suyo |
| `PUT /proveedores/pilotos/{id}/delegacion-oferta` | El encargado delega o retira la oferta de un piloto suyo (apagada por defecto) |
| `POST /solicitudes/{id}/ofertas` | Aceptación: primera oferta de una negociación (acepta el precio o contraoferta); queda en cola o llega a la bandeja del cliente |
| `GET /ofertas`, `GET /ofertas/{id}` | Mis negociaciones, su estado (en cola, el cliente la está viendo, en conversación, cierres) y su historial |
| `POST /ofertas/{id}/aceptar`, `/contraofertar`, `/rechazar`, `/retirar` | Respuesta del proveedor |
| `PUT /ubicacion` | Última ubicación durante la ejecución |
| `GET/POST /solicitudes/{id}/mensajes` | Chat con el cliente adjudicado |
| `POST /solicitudes/{id}/pago-confirmado`, `GET /solicitudes/{id}/pago-confirmado` | El titular informa que recibió el pago; ambas partes lo consultan |
| `PUT/DELETE /perfil/foto` | Foto de perfil de la cuenta |
| `GET /proveedores/mi`, `GET /proveedores/drones` | El titular gestiona su proveedor y sus drones; el piloto los ve en solo lectura (el proveedor al que pertenece y los drones de sus equipos) |
| `GET/PUT /notificaciones/preferencias`, `POST/DELETE /dispositivos` | Preferencias de aviso, filtros de aviso del proveedor y token de push |
| `GET /reputacion`, `GET /calificaciones/recibidas`, `POST /calificaciones/{id}/reportar` | Reputación y calificaciones recibidas |
| `GET/POST /solicitudes/{id}/calificar-cliente` | Califica al cliente en privado (7 días) |
| Rutas de reclamo e incidencias | Las mismas del cliente; el lado lo decide el tipo de la cuenta |

### 10.3 Sincronización del lote (piloto)

| Método y ruta | Función |
|---|---|
| `POST /sync` | Recibe un lote de registros (`inicio`, `avance`, `cierre`, `mensaje`, `incidencia`); cada registro se aplica una sola vez por `uuid_cliente`, en orden causal fijo sin importar el orden del arreglo, y se devuelve el resultado por registro (`aplicado`, `duplicado` o `rechazado`) |
| `POST /solicitudes/{id}/evidencias` | Sube fotos de avance, finales o de incidencia comprimidas, en cola separada |
| `GET /sync/catalogo?desde={cursor}` | Catálogo desde un cursor opaco: servicios adjudicados y en ejecución del equipo con los datos que abre la adjudicación, servicios retirados, personas y mensajes recientes |

La sincronización es idempotente: reaplicar un lote dos, tres o diez veces, en orden o con desorden parcial, deja el mismo estado final.

### 10.4 Administración y soporte

Son pantallas del panel (ADR 0008), no endpoints de la API de las apps. El riel tiene un acceso directo, Inicio (el tablero), y cinco módulos: Mercado, Proveedores, Atención, Seguridad y Sistema y cada pantalla se muestra solo si el rol activo tiene su permiso de listado (`.ver`); las acciones de cada una exigen su permiso propio.

El menú son 15 ítems, en este orden (el seeder `SecMenuSeeder` es su única fuente; una base ya sembrada lo recibe con `php artisan db:seed --class=SecMenuSeeder` o `bin/demo seed`):

| Módulo | Ítems, en orden |
|---|---|
| Inicio | acceso directo al tablero, sin ítems |
| Mercado | Métricas del mercado, Solicitudes, Calificaciones (las que reciben pilotos y clientes) |
| Proveedores | Cuentas (`panel/proveedores`: personas y empresas que ofrecen el servicio), Pilotos, Drones, Documentos |
| Atención | Clientes, Reclamos |
| Seguridad | Usuarios, Permisos, Bitácora |
| Sistema | Organización, Avisos del sistema, Configuración |

Zonas no está en el menú: el mercado no usa zonas. El único filtro geográfico es el departamento, un catálogo oficial de solo lectura que no se administra desde el panel.

En una página que no pertenece a ningún ítem (búsqueda, perfil, Mis notificaciones), Inicio queda activo en el riel y su panel muestra un solo ítem, sin enlace, con el nombre de la página actual.

| Módulo | Pantalla | Qué hace |
|---|---|---|
| Inicio | `panel/dashboard` | Tablero: solicitudes por estado y por departamento, hectáreas abiertas a ofertas y la cola de lo que espera una decisión |
| Mercado | `panel/solicitudes` | Lista con filtros por estado y departamento; la ficha muestra cada negociación por proveedor con sus rondas y, con `solicitudes.solicitud.ver_seguimiento`, el chat, el avance, las fotos y la última ubicación del piloto (datos de las partes). Cancela con motivo (`solicitudes.solicitud.cancelar`) o cierra la negociación de un proveedor con motivo (`solicitudes.solicitud.intervenir`) mientras la solicitud sigue abierta a ofertas |
| Atención | `panel/clientes` | Ficha del cliente con su reputación, sus contactos y sus solicitudes; suspende y reactiva con motivo (`solicitudes.cliente.suspender`); alta a mano de persona o empresa con contactos (`solicitudes.cliente.crear`) |
| Proveedores | `panel/proveedores/crear`, `panel/pilotos/crear`, `panel/drones-flota/crear` | Alta a mano de proveedor (independiente con su piloto, o tercero), de un piloto de un tercero y del dron de un proveedor, con `proveedores.proveedor.crear`, `proveedores.piloto.crear` y `flota.dron.crear`; quedan `pendientes` y quien los cargó no los aprueba (ADR 0044) |
| Mercado | `panel/calificaciones` | Oculta o deja visible una calificación con causa y motivo |
| Atención | `panel/disputas` | Reclamos: lo que un cliente o un proveedor reclama sobre un servicio ya prestado; se toma, se media y se resuelve. Va en Atención porque atender a quien usa la plataforma no es operar el mercado |
| Mercado | `panel/metricas` | Adjudicación, ofertas por solicitud, rondas por negociación, tiempos, calidad y volumen |
| Sistema | `panel/avisos` | Auditoría: lo que el sistema avisó a cada cuenta y si lo leyó; solo lectura (`notificaciones.aviso.ver`). Los avisos de la propia cuenta están en la campana |
| Proveedores | `panel/proveedores`, `panel/pilotos` | Aprobar, rechazar, suspender y dar de baja, con motivo |
| Proveedores | `panel/documentos` | Revisión de documentos y descarga restringida |
| Proveedores | `panel/drones-flota` | Aprobación del dron y revisión de sus fotos (`flota.dron.revisar_foto`); no se aprueba sin las fotos de `flota.fotos_obligatorias` aprobadas |
| Sistema | organización y configuración | Ajustes de la plataforma que se tocan poco; la organización va primero, luego los avisos y al final la configuración |
| Seguridad | usuarios, permisos y bitácora | Roles del panel: `administrador` (todo) y `operador` (lo que marca su matriz) |

Solicitudes urgentes en el panel (ADR 0045, punto K): el tablero suma la tarjeta «Urgentes sin aceptación» (urgentes vigentes sin ninguna negociación en cola, bandeja ni conversación a las que faltan menos de `negociacion.urgencia.alerta_admin_horas`, 48); el listado de solicitudes suma el filtro «Urgentes» y la columna de fecha límite con cuenta regresiva (la insignia «Urgente» se conserva); la ficha suma la línea de tiempo de oleadas con los parámetros de urgencia usados y la acción «Avisar ahora a todos» (`solicitudes.solicitud.avisar_a_todos`, con motivo y bitácora; 409 `solicitud_en_acuerdo`, `aviso_manual_demasiado_pronto`); las métricas suman el tiempo hasta la primera aceptación de las urgentes frente a las normales. El administrador no edita la urgencia, el precio ni las fechas del cliente. La tabla de arriba sigue describiendo el menú; sus filas del tablero y de `panel/solicitudes` se actualizan con la fase de retiro de zonas.

Alcance por departamento en el panel (ADR 0045, punto L): el listado de solicitudes muestra el departamento y si la solicitud está ampliada («Ampliada a otros departamentos»), con filtro por departamento y por alcance; la ficha lo repite y su línea de tiempo de oleadas muestra la pasada de ampliación con su origen; los proveedores muestran los departamentos donde trabajan y el alta manual los pide. El administrador **no amplía por el cliente** (no hay acción para eso; puede sugerírselo por teléfono) y «Avisar ahora a todos» respeta el alcance autorizado. Los tableros y las métricas se agrupan por departamento.

Roles del panel (ADR 0046): el panel lo usa solo el personal de Agrocom y tiene dos roles, Administrador (ve todo) y Operador (ve las pantallas y acciones cuyos permisos le asigna el administrador en la matriz de roles). Este párrafo manda sobre la fila «Seguridad» de la tabla anterior y sobre «quien los cargó no los aprueba» de la fila de alta a mano: el administrador puede aprobar lo que registró, y «Ver como» solo sobre cuentas de panel. Las altas a mano pueden crear cuentas de app (cliente o proveedor, con la misma regla de una cuenta de cada tipo por persona) y aprobarlas, con la verificación marcada «cargado por administrador» (ADR 0046). La ficha del cliente y del proveedor muestra su verificación (selfie y documento, con `seguridad.verificacion.ver`), con aprobar y rechazar con motivo.

El administrador lee el chat y la ubicación solo para atender un reclamo o una intervención; no escribe en ellos. Con un reclamo de **conducta** el panel muestra solo lo reportado (el mensaje y 10 antes y después, o los últimos 50 de la conversación) mientras el reporte está abierto, y cada lectura deja un evento `chat.leido` sin contenido. El panel tiene la cola de reportes de conducta, la cola de solicitudes de eliminación de cuenta (estado, plazo, origen, y las solicitadas desde la oficina) y, en la ficha de una cuenta, cuántas cuentas la bloquearon (ADR 0047). Cerrar una negociación no es una sanción: no restringe ninguna cuenta (ADR 0033).

## 11. Parámetros configurables

Anticipación máxima de la fecha deseada y recordatorio de solicitud sin trato; parámetros de urgencia (`negociacion.urgencia.*`: tope de días de la fecha límite, umbral de sugerencia, intervalo y escalera acelerados, horario de relanzamiento, umbral de alerta del administrador, ciclos antes de sugerir ajustes, motivos) y separación mínima entre avisos manuales; vigencia de la oferta; cupo de negociaciones simultáneas del cliente (`cupo_negociaciones`, 3); intervalo (T), escalera, calificaciones mínimas y horario de relanzamiento por defecto de las oleadas; máximo de ofertas por negociación, precio máximo de sanidad por hectárea, moneda, tolerancia de hectáreas ejecutadas sobre las solicitadas, evidencias mínimas de cierre, fotos obligatorias del dron, intervalo y caducidad de la ubicación, plazos de disputa y de calificación, umbral de calificación y de tasa de cancelación para alertar, anticipo de vencimiento de documentos, eliminación de cuenta (`eliminacion.gracia_dias`, 7; `eliminacion.plazo_dias`, 30), retención (`verificacion.retencion_meses_tras_baja`, 12; `verificacion.retencion_eliminacion_dias`, 90; `verificacion.retencion_rechazadas_dias`, 90; `antifraude.registro_meses_sin_antecedentes`, 12; `antifraude.registro_meses_con_antecedentes`, 36; `retencion.servicio_meses`, 12; `retencion.aceptaciones_meses`, 36; `incidencias.ubicacion_retencion_dias`, 90; `respaldos.retencion_dias`, 30) y moderación del chat (`moderacion.contexto_mensajes`, 10; `moderacion.mensajes_conversacion`, 50; `moderacion.bloqueos_alerta`, 3; lista del filtro de mensajes).

## 12. Notificaciones

Mecanismo del ADR 0025 con preferencias del ADR 0041: un aviso por cuenta destinataria al emitir el evento, in-app y push del sistema operativo según las preferencias de la cuenta.

| Evento | Destinatario | Se puede desactivar |
|---|---|---|
| `SolicitudAbierta`, por oleadas según los filtros (las urgentes, con más frecuencia y prioridad alta) | Pilotos aprobados, disponibles y con el aviso activo que cumplen los filtros de cada paso (y `recibir_urgentes` si es urgente); sin push se ve en la lista de la app | Sí (`nuevas_solicitudes`; las urgentes también con `recibir_urgentes`) |
| Aceptación liberada a la bandeja (`aceptacion_en_bandeja`: «Tienes N aceptaciones nuevas»); no avisa mientras la aceptación espera en la cola | Cliente | Sí (`ofertas`) |
| `OfertaRecibida`, `ContraofertaRecibida` (en una conversación abierta) | La contraparte | Sí (`ofertas`) |
| Oferta liberada de la cola (`oferta_en_revision`: «El cliente ya está viendo tu oferta») | Proveedor que ofreció | Sí (`ofertas`) |
| Solicitud «En acuerdo» deshecha porque cayó la última conversación (`acuerdo_deshecho`) | Cliente | No |
| `SolicitudAdjudicada`, `OfertaCerrada` | Las partes de cada negociación | No |
| `EjecucionIniciada`, `AvanceRegistrado`, `SolicitudFinalizada` | Cliente | Sí (`avance`) |
| `MensajeEnviado` | La contraparte | Sí (`mensajes`) |
| `SolicitudCancelada` | Las partes involucradas | No |
| Aviso de solicitud sin respuesta (cuánto lleva, con las opciones de modificarla o retirarla) y aviso de fecha deseada pasada | Cliente | No |
| Urgente sin aceptación tras un ciclo (`urgente_sin_aceptacion`: sugiere subir el precio, ampliar la fecha o quitar el requisito de dron) y fecha límite pasada (`fecha_limite_pasada`: «Pasó tu fecha límite») | Cliente | No |
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

- **Mercado**: solicitudes abiertas por día, tasa de adjudicación, tiempo hasta la primera oferta, tiempo hasta la adjudicación, ofertas por solicitud, tiempo de espera en la cola y aceptaciones que esperan, diferencia entre precio propuesto y acordado, hectáreas servidas, solicitudes esperando piloto y relanzamientos, demanda abierta por departamento, solicitudes ampliadas a otros departamentos y aceptaciones que llegaron de otro departamento, avisos por paso de oleada, tiempo hasta la primera oferta por paso, y solicitudes urgentes sin aceptación y tiempo hasta la primera aceptación de las urgentes frente a las normales.
- **Calidad**: calificación promedio, tasa de disputas, incidencias por 1.000 hectáreas.
- **Oferta**: proveedores activos, drones aprobados, tasa de cancelación del proveedor, tiempo de aprobación de un proveedor.
- **Volumen**: monto final acumulado de los servicios finalizados e ingreso por hectárea acordado.
- **Sincronización**: antigüedad de la cola del outbox, tasa de reintentos, evidencias pendientes.

## 15. Alcance de la primera versión

Incluye: registro de cuentas de cliente y de proveedor (una persona, una cuenta de cada tipo), verificación de identidad y aprobación de clientes y proveedores con datos de la persona y del dron con fotos, propiedad y lotes en mapa, solicitud con precio por hectárea, mapa de solicitudes abiertas, negociación por rondas, adjudicación, ejecución con fotos y avance, ubicación del piloto, chat, precio final, calificación, reclamos, incidencias y SOS, notificaciones con preferencias y métricas básicas. No incluye: cobro dentro de la app, comisión calculada por la plataforma, cobertura de seguro propia, ruta ni tiempo estimado de llegada, ni facturación electrónica.
