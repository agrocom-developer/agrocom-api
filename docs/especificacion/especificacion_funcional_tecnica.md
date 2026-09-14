# Especificación funcional y técnica — Sistema de Gestión de Operaciones de Fumigación

**Agrocom SRL · v1.1 — documento oficial vigente**

Consolida la Especificación Técnica v1.0 (`docs/legacy/Especificacion_Tecnica_Sistema_Fumigacion_v1.0.docx`) con los ajustes de arquitectura decididos después de esa versión. Las decisiones de stack y arquitectura tienen su propio registro en `docs/decisiones/` (ADRs); este documento es la fuente única de **qué** hace el sistema y **cómo se modela** — los ADR explican **por qué** se eligió cada pieza técnica.

---

## 1. Alcance

Automatizar doce funciones:

| # | Función | Salida |
|---|---|---|
| 1 | Operaciones | Trabajo por lote/dron con condiciones, recargas e incidencias |
| 2 | Trazabilidad | Cada hectárea aplicada, reconstruible de punta a punta |
| 3 | Acta de conformidad | Documento firmado por lote, inmediatamente después de aplicar |
| 4 | Reporte al cliente | Técnico para el agrónomo, comercial para el dueño |
| 5 | Gastos | Imputados por rubro, dron y lote |
| 6 | Ingresos | Contratado → aplicado → facturado → cobrado |
| 7 | Planilla de pagos | Liquidación por período para los cuatro roles |
| 8 | Contabilidad básica | Registro de gastos e ingresos por rubro, exportable |
| 9 | Mantenimiento | Drones, vehículos y generadores |
| 10 | Inventario de repuestos | Stock, costo y consumo por equipo |
| 11 | Portal del cliente | Acceso de solo lectura a sus reportes |
| 12 | Gestión de usuarios | Altas, roles múltiples, bases, bloqueo |

Fuera de alcance en v1: contabilidad formal con plan de cuentas normado, integración con la API de DJI, facturación electrónica.

---

## 2. Arquitectura

Cuatro superficies, un solo backend (Laravel + PostgreSQL 16, API REST — ver ADR 0001):

| Superficie | Usuarios | Alcance | Implementación |
|---|---|---|---|
| App piloto (RC Android) | Piloto | Órdenes, condiciones, cierre de lote, captura RC, incidencias, acta | Flutter, flavor `piloto` (repo `agrocom-field`) |
| App auxiliar (celular Android) | Auxiliar | Preparación de mezcla, recargas, batería, combustible, incidencias | Flutter, flavor `auxiliar` (repo `agrocom-field`) |
| Panel web | Jefe de campo, encargado de operaciones, dueño | Todo lo demás | AdminLTE + Blade/Livewire (ver ADR 0002) |
| Portal cliente | Dueño del campo, agrónomo | Reportes de aplicación de sus lotes, solo lectura | Mismo panel, guard separado (ver ADR 0002) |

Una sola base de código Flutter con dos perfiles de interfaz (piloto/auxiliar), no dos aplicaciones separadas — comparten sincronización, autenticación, cola offline y evidencias (ver ADR 0005). Se instalan por separado; para el usuario son dos apps distintas, para el desarrollo es una.

**Decisiones de la app de campo:**

- **Offline-first obligatorio.** Hay Starlink en la base, no en el lote. Toda escritura se encola localmente y sincroniza al volver a cobertura.
- **Idempotencia por UUID generado en cliente.** Cada registro nace con su UUID en el dispositivo; el servidor rechaza duplicados vía `UNIQUE (uuid_cliente)` (ver ADR 0001). Sin esto, una sincronización repetida duplica hectáreas — y las hectáreas son dinero.
- **El servidor nunca sobrescribe un registro validado.** Las correcciones son registros nuevos que anulan al anterior, con motivo y autor.
- **Evidencias comprimidas en el dispositivo antes de subir**, objetivo <300 KB por imagen.

### 2.1 Protocolo de sincronización (el problema de mayor riesgo del proyecto)

1. **Base local SQLite** (`drift` en Flutter). Toda escritura va primero a la base local; la interfaz siempre lee de ahí, nunca del servidor directamente.
2. **Patrón outbox**: tabla `cola_sync` local con cada escritura pendiente (`uuid_cliente`, tipo de entidad, payload JSON, orden causal). Estados: `pendiente → enviado → confirmado | rechazado`.
3. **Push en lote**: `POST /api/sync` recibe un arreglo ordenado causalmente (trabajo antes que sesión, sesión antes que recarga). El servidor procesa **registro por registro en transacciones individuales** — nunca el lote completo en una transacción — y devuelve estado por registro: `aplicado`, `duplicado` o `rechazado {motivo}`. Un rechazo no frena el resto del lote.
4. **Idempotencia en la base, no en el código**: `UNIQUE (uuid_cliente)` por tabla operativa. Reintentar un lote ya aplicado → `ON CONFLICT` → `duplicado`, que el cliente trata como éxito.
5. **Referencias por UUID de cliente**: una sesión creada offline referencia su trabajo por `uuid_cliente`, no por id de servidor. El servidor resuelve la referencia al aplicar.
6. **Pull de catálogo**: `GET /api/sync/catalogo?desde={cursor}` baja órdenes vigentes, recetas, productos, lotes y personal, con cursor por `updated_at`.
7. **Evidencias en cola separada**: los registros livianos sincronizan primero; las imágenes comprimidas van en una segunda cola referenciada por UUID. Una sesión puede quedar `confirmada` con evidencia aún subiendo, pero la validación exige la evidencia ya subida.
8. **Relojes**: se guarda la hora del dispositivo y el `recibido_en` del servidor. El orden entre eventos de una sesión lo da un campo `secuencia` local, nunca la comparación de relojes entre dispositivos.

**La simplificación que elimina conflictos de merge:** cada tipo de registro tiene exactamente un rol escritor (la sesión la escribe el piloto, la mezcla el auxiliar, la validación el jefe desde el panel). No hay dos dispositivos editando la misma fila, por lo tanto no hace falta lógica de merge — solo inserciones idempotentes y anulaciones. Si una pantalla nueva necesitara que dos roles editen el mismo registro, la respuesta correcta es dividirlo en dos registros.

**Prueba obligatoria:** reproducir el mismo lote de sincronización dos, tres, diez veces, en orden y en desorden parcial → el estado final de la base debe ser idéntico. Este test se escribe antes que la primera pantalla del esqueleto vertical.

---

## 3. Roles y permisos

Modelo de autorización: `sec_*` con permiso abstracto y multi-rol (ver ADR 0004). La tabla siguiente describe las reglas de negocio que las policies deben implementar; el mecanismo de autorización (permiso general + regla por registro) es responsabilidad de `sec_permission` + policies de Laravel.

| Acción | Piloto | Auxiliar | Jefe de campo | Enc. operaciones | Dueño | Agrónomo |
|---|---|---|---|---|---|---|
| Ver orden de aplicación | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Abrir trabajo / registrar condiciones | ✔ | — | ✔ | — | — | — |
| Registrar recarga y batería | — | ✔ | ✔ | — | — | — |
| Preparar mezcla | — | ✔ | ✔ | — | — | — |
| Definir receta de mezcla | — | — | — | — | — | ✔ |
| Registrar incidencia | ✔ | ✔ | ✔ | — | — | — |
| Cerrar lote (ha + captura RC) | ✔ | — | ✔ | — | — | — |
| Validar trabajo | — | — | ✔ ¹ | ✔ | — | — |
| Generar y presentar acta | ✔ | — | ✔ | — | — | — |
| Firmar acta | — | — | — | — | — | ✔ |
| Registrar rendición con comprobante | — | — | ✔ | — | — | — |
| Cargar gasto contable | — | — | — | ✔ | ✔ | — |
| Registrar cobranza / facturar | — | — | — | ✔ | ✔ | — |
| Ver costos y márgenes | — | — | — | ✔ ² | ✔ | — |
| Ver reportes técnicos | — | — | ✔ | ✔ | ✔ | ✔ ³ |
| Registrar mantenimiento | — | — | ✔ | ✔ | ✔ | — |
| Mover stock de repuestos | — | — | ✔ ⁴ | ✔ | ✔ | — |
| Generar planilla de pagos | — | — | — | ✔ | ✔ | — |
| Aprobar planilla | — | — | — | — | ✔ | — |
| Gestionar usuarios | — | — | — | ✔ ⁵ | ✔ | — |

¹ Solo trabajos de otros pilotos, nunca los propios (regla por registro, a nivel persona — ver ADR 0004). ² Sin acceso a la línea de ganancia. ³ Solo los lotes de su propio contrato, desde el portal. ⁴ Solo salidas de stock de su base; las entradas las carga el encargado. ⁵ No puede crear ni modificar usuarios con rol dueño.

Reglas de identidad y acceso (ver ADR 0004 para el modelo completo):

- Un usuario puede tener **más de un rol, con un único login** (el jefe de campo que también es piloto entra una sola vez, con un único usuario y contraseña); los permisos se evalúan por **unión de roles** vía `sec_user_role` (ADR 0004) — nunca se crean cuentas duplicadas por rol.
- La regla "nadie valida su propio trabajo" se aplica **a nivel de persona** (`sec_user.persona_id`), no de rol — un jefe-piloto no valida sus propias sesiones aunque el rol jefe tenga el permiso general de validar.
- Apps y celulares autentican con **token por dispositivo** (Sanctum), revocable individualmente desde el panel sin bloquear al usuario.
- `sec_user.type` (`interno`/`cliente`) separa el guard del panel interno del guard del portal.

---

## 4. Modelo de datos

### 4.0 Campaña (`cpn_*`) — el eje de todo lo demás

- `campanias` — id, **cliente_id**, código (`2025-2026`), nombre, fecha_inicio, fecha_fin, estado (planificada / abierta / cerrada). *La campaña es **del cliente**: Agrocom no corre campañas propias, aplica dentro de la del cliente (corrección del 8/9/2026, ADR 0015). Único por `(cliente_id, código)`, y **sin guarda de solapamiento** — hay tantas abiertas como clientes en campaña, y un mismo cliente puede tener dos (soya de verano, maíz de invierno). No hay campaña activa de sesión ni chip en el header: la campaña se elige dentro del cliente o del contrato, y en los informes es un filtro. Nada se imputa a una campaña `cerrada`.*

Llevan `campania_id` propio solo las entidades donde alguien la **elige explícitamente**: `contratos` (obligatorio, y la campaña tiene que ser del mismo cliente del contrato) y `lote_campania` (obligatorio, por definición). En `gastos` y `cargas_combustible` es **nullable** y significa otra cosa: **en qué campaña se consumió** ese gasto — atribución de costo para saber cuánto cuesta atender a ese cliente, nunca un cargo que se le facture (el cliente paga por hectárea aplicada, ADR 0015 punto 6). No la llevan `ordenes_aplicacion`, `trabajos`, `sesiones`, `actas` ni `facturas` —cuelgan de un contrato que ya la tiene—, ni `equipos_trabajo` (el equipo es de Agrocom y trabaja para varias campañas), ni `estadias_hacienda` (el cliente sale del campo y la fecha ubica la campaña).

### 4.1 Comercial

- `clientes` — id, razón social, nit, tipo_persona (física / jurídica), contacto dueño, contacto agrónomo. *El dueño, cuando el cliente es una sociedad, se registra como contacto tipo `dueno` — no como cliente propio (ADR 0018).*
- `contratos` — id, campania_id, cliente_id, hectáreas_contratadas, aplicaciones_previstas, precio_ha, monto_total, adelanto_monto, adelanto_pct, fecha_inicio, fecha_fin, estado, + parámetros de vuelo y límites de condiciones (altura_vuelo_m, velocidad_max_kmh, viento_max_kmh, temperatura_max_c, humedad_min_pct, humedad_max_pct, umbral_reporte_avance_ha; NULL = rige el valor por defecto del sistema)
- `contrato_ventanas` — id, contrato_id, hora_inicio, hora_fin. *N por contrato y **opcionales**: sin ninguna ventana cargada, el contrato aplica a cualquier hora ("todo el día"). No hay booleano de "todo el día" — la ausencia de filas es el dato (ADR 0015).*
- `contrato_alcances` — id, contrato_id, propiedad_id, campo_id (nullable), hectareas. *Qué terreno cubre el contrato: una propiedad entera (`campo_id` nulo), un campo específico, o una mezcla de varias propiedades — N filas por contrato, y la suma no puede superar `hectareas_contratadas` (ADR 0018).*
- `propiedades` — id, cliente_id, nombre, ubicación (departamento, provincia, municipio o pueblo — ej. Cuatro Cañadas, Roboré, San Matías). *Un cliente tiene varias propiedades — el nivel de negocio ("Gamelera"), no necesariamente un único predio físico delimitado.*
- `campos` — id, propiedad_id, nombre, geometría (GeoJSON, perímetro de referencia). *Una propiedad tiene uno o más campos físicos (ej. dos mitades separadas por una carretera, cada una con su propia campaña); cada campo tiene varios lotes (ADR 0018).*
- `lotes` — id, campo_id, código, hectáreas, geometría (GeoJSON), restricciones (texto: cables, viviendas, colmenas, vecinos sensibles)
- `cultivos` — id, nombre (soya, maíz, girasol, trigo, sorgo…), activo. *Catálogo.*
- `lote_campania` — id, lote_id, campania_id, cultivo_id, hectareas_sembradas, fecha_siembra, fecha_cosecha_estimada. *Qué se sembró en cada lote en cada campaña — un cultivo por lote y campaña. El lote no "es" de soya: se siembra de soya esta campaña y de maíz la siguiente. Es la dimensión que agrupa el informe de avance de contratos (§9.1).*

### 4.2 Recursos

- `drones` — id, modelo (T50/T70/T100), serie, base_id, estado, horas_vuelo
- `baterias` — id, código propio (ej. T50-A-01), serie DJI, ciclos, fecha_alta, estado, dron_actual_id
- `vehiculos` — id, tipo, placa, base_id, gasolina o diésel
- `bases` — id, nombre, ubicación
- `personas` — id, nombre, rol, tarifa_ha (piloto/auxiliar), sueldo_mensual (jefe/encargado), base_id, activo
- `generadores` — id, identificador, modelo, base_id, estado, horas_uso
- `equipos_trabajo` — id, código (`E1`), nombre, base_id, estado, desde, hasta. *La cuadrilla: **el piloto y su auxiliar, no dónde están trabajando**. Es la unidad a la que se imputa el gasto que no pertenece a ningún trabajo en particular (combustible, viáticos, mantenimiento de la camioneta). **Es de Agrocom, no de una campaña**: en la misma semana trabaja para varios clientes.*
- `equipo_integrantes` — id, equipo_trabajo_id, persona_id, rol_equipo (piloto / auxiliar), desde, hasta. *Con vigencia: un gasto de marzo queda atribuido a quienes integraban el equipo en marzo, no a la formación de hoy.*
- `equipo_recursos` — id, equipo_trabajo_id, recurso_tipo (dron / vehiculo / generador), recurso_id, desde, hasta. *El equipamiento asignado al equipo. Es lo que permite saber **qué** vehículo o generador consumió el combustible que se imputó al equipo.*

### 4.3 Operación

- `ordenes_aplicacion` — id, contrato_id, lote_id, nro_aplicacion, tipo_aplicacion (siembra / desarrollo / cosecha), litros_ha, humedad_minima, parámetros de vuelo acordados (altura_vuelo_m, velocidad_vuelo_kmh, ancho_pasada_m), observaciones, emitida_por (agrónomo), fecha_emision, estado. *`tipo_aplicacion` dice en qué momento del ciclo se fumiga: `siembra` (barbecho o presiembra), `desarrollo` (el grueso de las 6-8 aplicaciones, desde el desarrollo vegetativo) y `cosecha` (desecante previo a cosechar). Cambia qué se espera de la aplicación y cómo se agrupa el informe de avance.*
- `recetas_mezcla` — id, orden_id, volumen_referencia_l, agitacion_requerida, ph_objetivo, observaciones
- `receta_items` — id, receta_id, secuencia, producto_id, tipo (fitosanitario / coadyuvante / antiespumante / antideriva / corrector_ph / aceite / fertilizante_foliar), dosis_valor, dosis_unidad (ml/ha, g/ha, ml/100L, %v/v), pre_disolucion_requerida (bool), nota. *La receta la define el agrónomo, con su orden de incorporación; Agrocom la ejecuta y la documenta, no la modifica.*
- `productos` — id, nombre_comercial, ingrediente_activo, formulación (WG / WP / SC / SL / EC / EW / OD / adyuvante), unidad, densidad, proveedor
- `mezclas` — id, uuid_cliente, sesion_id, secuencia, receta_id, volumen_agua_l, volumen_final_l, hectareas_cubiertas, preparada_por, inicio, fin, estado (en_preparacion / lista / cargada / anulada), evidencia_id
- `mezcla_items` — id, mezcla_id, receta_item_id, secuencia, cantidad_calculada, cantidad_real, unidad, hora_incorporacion, confirmado_por. *Cada tanque preparado es una mezcla; el sistema calcula la cantidad de cada producto y el auxiliar confirma la real — la diferencia explica una aplicación fallida.*
- `sobrantes` — id, mezcla_id, volumen_sobrante_l, destino (aplicado_en_lote / devuelto / dispuesto), triple_lavado (bool), observacion
- `trabajos` — id, uuid_cliente, orden_id, lote_id, nro_aplicacion, hectareas_declaradas (suma de sesiones), hectareas_validadas, inicio, fin, estado, motivo_observacion. *La orden es requisito previo para abrir un trabajo; sin orden vigente, el sistema no permite iniciar. El trabajo no lleva dron ni piloto propio — un lote puede tener varios pilotos y drones por relevo, falla o logística; eso vive en las sesiones.*
- `sesiones` — id, uuid_cliente, trabajo_id, secuencia, dron_id, piloto_id, auxiliar_id, hectareas_declaradas, hectarea_inicial_acumulada, inicio, fin, motivo_cierre (completado / relevo_piloto / cambio_dron / falla_equipo / clima / fin_jornada / otro), captura_rc_id, estado, validado_por, fecha_validacion. *Cada sesión es una unidad de trabajo continua de un piloto con un dron; se cierra con su propia captura de RC.*
- `condiciones` — id, trabajo_id, sesion_id, momento (inicio_sesion / incidencia), viento_kmh, temperatura_c, humedad_pct, autorizado (bool), observacion_agronomo, firma_observacion
- `recargas` — id, sesion_id, mezcla_id, secuencia, litros_caldo, problema_caldo (enum: ninguno / filtro_tapado / grumos / decantacion / espuma / color_olor_anormal), bateria_saliente_id, temperatura_bateria_c, hora, registrado_por
- `incidencias` — id, sesion_id, tipo (enum: caldo / esc / bateria / mecanica / clima / otro), descripcion, hora, evidencia_id
- `evidencias` — id, tipo (captura_rc / imagen_campo / foto_incidencia / comprobante / firma_acta), archivo_url, hash, subido_por, fecha, uuid_cliente
- `actas` — id, trabajo_id, hectareas_conformadas, firmante (agrónomo), fecha_firma, evidencia_firma_id, observaciones, estado
- `estadias_hacienda` — id, uuid_cliente, equipo_trabajo_id, campo_id, entrada, salida, vehiculo_id, observacion. *Entrada y salida del equipo en cada hacienda. Nace en la app de campo (lleva `uuid_cliente` y viaja por `POST /api/sync`, §2.1) porque la registra el equipo al llegar y al irse, no la oficina. Responde cuántos días efectivos estuvo cada cuadrilla en cada propiedad — el dato que hoy falta para justificar el gasto imputado al equipo. Sin `campania_id`: el campo dice de qué cliente es y la fecha ubica la campaña; el piloto no elige campañas desde el celular. Un equipo no puede tener dos estadías abiertas a la vez; `salida` nula = estadía en curso, sin columna de estado que pueda contradecirla.*

### 4.4 Financiero

- `rubros` — id, nombre (los 8 del presupuesto + Indirectos), presupuesto_bs_ha
- `subrubros` — id, rubro_id, nombre, tipo_imputacion (directo_dron / directo_vehiculo / compartido)
- `gastos` — id, campania_id (nullable), fecha, rubro_id, subrubro_id, cantidad, precio_unitario, monto, equipo_trabajo_id, base_id, trabajo_id, medio_pago, evidencia_id, rendicion_id, cargado_por, tiene_comprobante (bool). *`equipo_trabajo_id` es la imputación principal del gasto de campo: la mayor parte no pertenece a ningún trabajo concreto (no se sabe a qué lote cargarle la carga de combustible) y la base no alcanza porque varias cuadrillas la comparten. `campania_id` es la dimensión analítica: en qué campaña se consumió, para comparar costo contra lo facturado por hectárea. **No es un cargo al cliente** — el combustible y la comida son logística de Agrocom. Vacío si es gasto interno que no pertenece a ninguna campaña.*
- `cargas_combustible` — id, campania_id (nullable), fecha, litros, monto, equipo_trabajo_id, recurso_tipo (dron / vehiculo / generador), recurso_id, base_id, registrado_por, gasto_id. *Se imputa al equipo, y el recurso concreto dice qué unidad consumió — el equipo tiene su equipamiento asignado (§4.2, `equipo_recursos`), así que la lista de destinos posibles sale de ahí y no del catálogo entero.* *La carga es la unidad y no se prorratea: se atribuye entera a la campaña donde se cargó, y la sobra que se consume después no se recalcula (ADR 0015 punto 6). El detalle de cuánta gasolina se usó por propiedad, campo y lote sale de `trabajo_id` y de las estadías del equipo, no de columnas nuevas.* *El auxiliar registra litros; el encargado carga precio; se vinculan después — separa desvío de precio de mercado de desvío de consumo real.*
- `rendiciones` — id, base_id, jefe_campo_id, fecha, monto, descripcion, evidencia_id, estado (pendiente / procesada / rechazada), gasto_id
- `fondos_caja` — id, base_id, responsable_id, monto_asignado, monto_rendido, saldo, fecha_apertura, fecha_cierre
- `devengos_personal` — id, persona_id, sesion_id, hectareas, tarifa_ha, monto, fecha. *Se calcula por sesión, no por lote — si dos pilotos trabajaron el mismo lote, cada uno cobra exactamente sus hectáreas. Se genera automáticamente al validar un trabajo, nunca al cerrarlo.*
- `anticipos` — id, persona_id, fecha, monto, autorizado_por, saldo_devengado_momento, pct_sobre_devengado
- `liquidaciones` — id, persona_id, periodo, devengado, anticipos, descuentos, saldo, estado, fecha_pago
- `facturas` — id, contrato_id, periodo, hectareas, monto, actas_incluidas (relación), fecha_emision, estado
- `cobranzas` — id, factura_id, fecha, monto, medio, aplicado_a_adelanto (bool)
- `planillas` — id, periodo (mes), estado (borrador / aprobada / pagada), total, generada_por, aprobada_por, fecha_aprobacion
- `planilla_detalle` — id, planilla_id, persona_id, rol, hectareas_periodo, tarifa_ha, devengado_variable, sueldo_fijo, anticipos_periodo, descuentos, liquido_pagar, estado_pago, fecha_pago

### 4.5 Mantenimiento e inventario

- `equipos` — vista unificada de drones, vehículos y generadores. id, tipo (dron / vehiculo / generador), referencia_id, base_id, unidad_de_uso (horas / km / hectáreas), uso_acumulado
- `generadores` — id, modelo, serie, base_id, horas_uso
- `planes_mantenimiento` — id, equipo_tipo, modelo, tarea, intervalo_valor, intervalo_unidad (horas / km / hectáreas / días), repuestos_previstos
- `ordenes_mantenimiento` — id, equipo_tipo, equipo_id, tipo (preventivo / correctivo), plan_id, descripcion, fecha_apertura, fecha_cierre, uso_al_momento, ejecutado_por, costo_repuestos, costo_mano_obra, gasto_id, estado
- `repuestos` — id, codigo, descripcion, categoria, compatible_con (modelos), unidad, costo_promedio_ponderado, punto_reposicion, requiere_serie (bool), critico (bool)
- `stock` — id, repuesto_id, base_id, cantidad, actualizado
- `movimientos_stock` — id, repuesto_id, base_id, tipo (compra / salida / ajuste / traslado / devolucion), cantidad, costo_unitario, orden_mantenimiento_id, gasto_id, serie, motivo, registrado_por, fecha. *Costeo por promedio ponderado: cada compra recalcula el costo unitario; cada salida se valoriza a ese costo y se imputa al dron o vehículo.*

### 4.6 Seguridad (`sec_*`)

Ver el modelo completo en ADR 0004 (`docs/decisiones/0004-modelo-seguridad-sec-multirol.md`).

---

## 5. Máquinas de estado

**Campaña:**
```
planificada ──► abierta ──► cerrada
```
*Sin vuelta atrás desde `cerrada`: reabrir una campaña cerrada es el agujero por donde entran las imputaciones retroactivas que descuadran un cierre ya presentado. Nada se imputa a una campaña cerrada — lo verifica cada módulo dueño al crear, no un trigger. La campaña la cierra Agrocom en su sistema cuando el cliente terminó su ciclo; no hay guarda de solapamiento entre campañas, ni de distintos clientes ni del mismo.*

**Sesión:**
```
abierta ──► en_ejecucion ──► cerrada ──► validada
                                 │
                                 └──► cerrada_por_relevo / cerrada_por_falla ──► (abre nueva sesión)
```

**Trabajo (lote en una aplicación):**
```
planificado ──► autorizado ──► en_ejecucion ──► parcial ──► completo ──► validado ──► conformado ──► facturado
                     │              ▲              │
                     │              │  (nueva sesión: relevo o cambio de dron)
                     ▼              └──────────────┘
                bloqueado ──► autorizado_con_observacion
```

| Transición | Condición |
|---|---|
| → autorizado | Existe orden de aplicación vigente y condiciones dentro de rango |
| → autorizado_con_observación | Condiciones fuera de rango + observación firmada por el agrónomo |
| Sesión → cerrada | Hectáreas de la sesión + captura de RC + motivo de cierre |
| → parcial | Sesión cerrada con motivo distinto de completado y hectáreas del lote sin cubrir |
| → completo | Suma de hectáreas de las sesiones ≥ hectáreas del lote |
| Sesión → validada | Validador ≠ piloto de esa sesión (a nivel persona). Genera el devengo de ese piloto y su auxiliar |
| → validado | Todas las sesiones del trabajo validadas |
| → conformado | Acta firmada por el agrónomo, sobre el lote completo |

**Relevo de piloto y cambio de dron:** un lote queda a medias por relevo, falla del dron o fin de jornada. Manejo uniforme: el piloto saliente cierra su sesión con hectáreas, captura de RC y motivo; el trabajo queda `parcial` con las hectáreas pendientes visibles; el piloto entrante abre una sesión nueva registrando la `hectarea_inicial_acumulada`; cada sesión se valida y devenga por separado.

**Control de doble conteo:** si la misión de DJI se retoma, la pantalla del RC del segundo piloto muestra el acumulado del lote, no lo suyo — por eso cada sesión registra `hectarea_inicial_acumulada` y las hectáreas de la sesión son la diferencia contra ese valor.

**Validación de suma:** la suma de sesiones no puede superar las hectáreas del lote más una tolerancia configurable por solape. Si la excede, el trabajo queda `observado` hasta que el encargado lo resuelva.

**Otras máquinas:** Orden de aplicación: `emitida → vigente → consumida | vencida`. Rendición: `pendiente → procesada | rechazada`.

**Implementación:** las transiciones viven en una tabla de transiciones permitidas + un servicio de dominio en Laravel (enum de estados, guardas por transición, excepción si la transición no existe) — nunca un `UPDATE estado = ...` suelto en un controlador (ver ADR 0003). Cada transición escribe en la tabla de auditoría: quién, cuándo, de qué estado a cuál, motivo.

**Dinero:** siempre en `DECIMAL`, jamás float. Hectáreas `DECIMAL(10,2)`, montos `DECIMAL(12,2)`. Todo monto derivado debe poder recalcularse desde los registros de origen y cuadrar exacto.

**Inmutabilidad:** los modelos operativos validados quedan bloqueados a nivel de aplicación (observer que lanza excepción ante update); la corrección es un registro nuevo con `anula_a_id`, motivo y autor. Las consultas operativas filtran anulados; las de auditoría los muestran.

---

## 6. Trazabilidad

Principio: toda hectárea facturada debe reconstruirse hasta su origen. Dado un número de acta, el sistema devuelve la cadena completa:

| Eslabón | Fuente |
|---|---|
| Qué se aplicó | Orden del agrónomo: producto, dosis, L/ha |
| Dónde | Lote, hectáreas, restricciones declaradas |
| Cuándo y en qué condiciones | Viento, temperatura, humedad al inicio |
| Con qué equipo | Dron o drones, baterías usadas con su temperatura |
| Cadena de ejecución | Sesiones en orden: qué piloto, con qué dron, cuántas hectáreas, por qué cerró |
| Con qué mezcla | Receta ordenada, productos incorporados, cantidad real vs. calculada, orden cumplido |
| Cuánto caldo | Recargas, litros, problemas detectados |
| Quién | Piloto y auxiliar de cada sesión, validador de cada una |
| Qué salió mal | Incidencias con evidencia |
| Prueba | Captura del RC + firma del agrónomo |
| Cuánto costó | Gastos imputados al trabajo y prorrateados |

Este es el activo real del sistema: defiende ante el reclamo de eficacia del agrónomo y sostiene la factura ante un pedido de descuento.

---

## 7. Recepción del caldo (lo prepara el cliente)

> **CR-01 revertida el 13/9/2026.** El dueño cambió de opinión: ahora sí
> quiere que el piloto registre qué productos y en qué cantidad se cargaron en
> el caldo (ver `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`
> y HU-78 de `docs/gestion/plan_sprints.md`, Sprint 16). El texto de 7.1 que
> sigue describe el razonamiento de la decisión **vieja** — se conserva porque
> el deslinde de responsabilidad que argumenta sigue siendo relevante para
> decidir qué datos carga el piloto y cuáles siguen siendo del agrónomo del
> cliente — pero la prohibición absoluta de modelar fórmula/dosis **ya no
> aplica**. HU-78 reescribe esta sección cuando se implemente; hasta entonces,
> el código (`ArmarContenidoReporteTecnico::notaMezcla()`) todavía imprime la
> nota vieja.

**Agrocom no prepara la mezcla y no quiere prepararla.** El caldo lo formula y
lo prepara el cliente, con su propio ingeniero agrónomo. Agrocom recibe el
caldo ya hecho y lo rocía. Esta era la decisión de negocio CR-01, cerrada el
1/9/2026 por el dueño y confirmada en todas las entrevistas de campo, hasta su
reversión parcial del 13/9/2026 (ver nota arriba).

### 7.1 Por qué el alcance termina acá

Es un deslinde de responsabilidad, no una comodidad. Quien elige el producto,
la dosis y la compatibilidad de la mezcla asume el resultado agronómico. Si la
aplicación se hace y el producto no hace efecto, o el cultivo se daña, o falla
la germinación, la causa está en la formulación — y la formulación no es de
Agrocom. Tomar la preparación sería tomar esa responsabilidad junto con ella.

Por eso el sistema **no** modela: fórmula, receta, dosis por hectárea, cálculo
de producto por tanque, checklist secuencial de incorporación, orden de mezcla,
compatibilidad entre productos, ni triple lavado de envases. Nada de eso entra
al alcance, ni siquiera como campo opcional: un dato de fórmula guardado acá
sugiere una responsabilidad que Agrocom no tiene.

### 7.2 Qué sí registra Agrocom

Lo que necesita para cobrar y para demostrar qué hizo con lo que le dieron:

- **Litros recibidos**: cuánto caldo entrega el cliente, cuándo y quién lo entregó.
- **Litros consumidos por sesión**: qué se roció efectivamente en cada sesión.
- **Sobrante**: cuánto quedó sin aplicar al cerrar, y que se devuelve al cliente.
- **Retraso o rechazo por calidad del caldo**: si el vuelo se demoró, se
  interrumpió o no se hizo porque el caldo llegó tarde, en mal estado, mal
  filtrado o en cantidad insuficiente. Se registra con hora y motivo.

Ese último punto es el que más protege: es la prueba de que la demora o el
resultado no fueron del servicio de aplicación.

### 7.3 Qué protege esto

Cierra el circuito del volumen sin entrar en el del contenido: Agrocom puede
demostrar cuántos litros recibió, cuántos aplicó sobre qué lote y cuántos
devolvió, y que la diferencia cuadra. Sobre la composición de esos litros no
opina, no calcula y no responde.

### 7.4 Volúmenes de carga de la flota

Esto sí es de Agrocom — es su equipo — y se usa para planificar cuántas
recargas lleva un lote, no para calcular producto. Volúmenes reales, no
nominales:

| Dron | Carga habitual |
|---|---|
| T50 | 30 L |
| T70 | 50 L |
| T100 | 60 L |

El T100 admite más, pero cargarlo al máximo devuelve baterías muy descargadas
y calientes.

---

## 8. Endpoints principales

```
POST   /api/sync                      Lote de registros offline (idempotente por uuid)
GET    /api/ordenes?lote_id=&estado=  Órdenes vigentes para el piloto
POST   /api/trabajos                  Abrir trabajo (valida orden + condiciones)
POST   /api/trabajos/{id}/sesiones    Abrir sesión (piloto + dron + ha acumulada)
POST   /api/sesiones/{id}/condiciones Registrar condiciones
POST   /api/trabajos/{id}/caldo       Registrar caldo recibido del cliente (litros)
POST   /api/trabajos/{id}/caldo/sobrante  Sobrante devuelto al cerrar
POST   /api/sesiones/{id}/recargas    Registrar recarga + batería + litros cargados
POST   /api/sesiones/{id}/incidencias Registrar incidencia con evidencia
POST   /api/sesiones/{id}/cerrar      Hectáreas + captura RC + motivo de cierre
POST   /api/sesiones/{id}/validar     Validación (bloquea si validador = piloto de la sesión)
POST   /api/trabajos/{id}/imagen      Imagen del campo capturada por el dron
POST   /api/trabajos/{id}/acta        Generar acta
POST   /api/actas/{id}/firmar         Firma del agrónomo + evidencia
POST   /api/rendiciones               Rendición del jefe de campo
POST   /api/gastos                    Carga contable (solo encargado)
POST   /api/anticipos                 Valida tope 3.000 Bs/mes y 70% del devengado
POST   /api/planillas                 Genera planilla del período (borrador)
POST   /api/planillas/{id}/aprobar    Aprobación del dueño
GET    /api/planillas/{id}/recibo/{p} Recibo individual en PDF
POST   /api/mantenimiento/ordenes     Abrir orden de mantenimiento
POST   /api/mantenimiento/{id}/cerrar Cierra, consume repuestos y genera gasto
GET    /api/repuestos?base=&bajo_min= Stock y alertas de reposición
POST   /api/stock/movimientos         Compra, salida, ajuste, traslado
       /portal/*                      Portal cliente: sesión Blade, guard `cliente` (solo su contrato; no es JSON — ADR 0002 punto 6)
POST   /api/usuarios                  Alta de usuario, roles y base
GET    /api/reportes/lote/{id}        Reporte técnico
GET    /api/reportes/aplicacion/{n}   Reporte comercial
GET    /api/dashboard                 Ganancia devengada, caja, costo Bs/ha
GET    /api/version                   Versión mínima y autorizada del APK
```

El contrato completo (payloads de ejemplo, códigos de respuesta) vive en `docs/api/openapi.yaml` — se agrega cuando arranca el desarrollo del motor de sync (ver `docs/gestion/plan_sprints.md`, Sprint 2). Mientras no exista, este listado es la referencia.

---

## 9. Reporte al cliente

Dos reportes, dos destinatarios.

**Técnico — por lote.** Contenido obligatorio: imagen del campo (capturada por el dron), hora de inicio y fin (primera y última sesión del lote), acta de conformidad firmada, resumen general (hectáreas, litros de caldo/ha reales, condiciones), mezcla ejecutada (productos, dosis ordenada vs. incorporada, orden cumplido, número de tanques). Complementos: superficie no aplicada y motivo, incidencias con evidencia, detalle de sesiones cuando hubo relevo o cambio de dron.

**Comercial — por aplicación.** Hectáreas aplicadas vs. contratadas, aplicaciones completadas, cumplimiento de la ventana, monto del período, saldo del adelanto, actas firmadas.

Generación: PDF automático al conformar el lote (técnico) y al cerrar la aplicación (comercial), sin intervención manual.

## 9.1 Informe de avance de contratos (interno)

Consulta interna del dueño y del encargado: cuánto de lo contratado ya se aplicó, agrupado por cultivo y por cliente. No es un reporte del portal — el cliente ve el suyo, acotado a su contrato (§13).

- **Entrada obligatoria**: al menos un cliente y al menos un cultivo, ambos de selección múltiple y sin valor por defecto. Sin las dos cosas no se habilita ni la pantalla de filtros ni la generación; el selector que falta muestra su propio mensaje de error.
- **Filtros** (pantalla aparte, no editables desde los chips): cliente, campaña (múltiple, dentro del cliente elegido), rango de fechas, estado del contrato, saldo (`a aplicar` / `cumplido` / `pendiente`), e incluir contratos deshabilitados (apagado por defecto). Los filtros aplicados se ven como chips en la pantalla principal; quitar un chip regenera el informe sin ese filtro.
- **Dos agrupaciones**, en pestañas: *Por cultivo* (por defecto) y *Por cliente* — esta última anida cliente dentro de cultivo, en tarjetas colapsadas, una abierta a la vez.
- **Columnas por contrato**: contrato, hectáreas pactadas, hectáreas aplicadas y **a aplicar** (pactadas − aplicadas), con totalizador de esta última al pie de cada grupo. Lista ordenada por vencimiento más cercano.
- **Barra de avance** con el porcentaje de cumplimiento (aplicadas ÷ pactadas), por tramos de color: 0-33 %, 34-66 %, 67-99 %, 100 % y más de 100 % — cinco tramos, cada uno con su token de color, ninguno hardcodeado (invariante 11).
- **Estado vacío** hasta generar la primera consulta, y también cuando la consulta no devuelve nada.

La forma de la pantalla sigue el molde del informe de contratos de producción de `synagroweb.com/manual/contrato-de-produccion/`, con hectáreas donde ese sistema pone kilos de grano. Agrocom vende servicio de aplicación, no compra grano: de ahí se toma la interacción, no el modelo (ADR 0015).

---

## 10. Alertas por excepción

El encargado de operaciones no revisa todo: recibe solo lo anómalo.

| Alerta | Condición |
|---|---|
| Hectáreas incoherentes | ha declaradas vs. litros aplicados fuera de ±15% de la dosis ordenada |
| Batería caliente | Temperatura de salida > 50 °C |
| Dron sospechoso | Mismo dron con 3+ baterías sobrecalentadas → revisar motores/ESC |
| Consumo anómalo | Gasolina por hectárea fuera del promedio del dron |
| Sin evidencia | Sesión cerrada sin captura de RC, o lote conformado sin imagen del campo |
| Suma excedida | Sesiones que suman más hectáreas que el lote, fuera de la tolerancia de solape |
| Lote parado | Trabajo en parcial sin sesión nueva en más de 48 horas |
| Sin orden | Intento de abrir trabajo sin orden vigente |
| Desvío de mezcla | Cantidad real vs. calculada fuera de ±5% en cualquier producto |
| Mezcla fuera de orden | Paso confirmado sin haber cerrado el anterior |
| Condiciones forzadas | Trabajo autorizado con observación |
| Anticipo al límite | Acumulado > 70% del devengado |
| Rendición pendiente | Gasto de campo sin procesar a más de 7 días |

---

## 11. Planilla de pagos

Se genera por período mensual, con dos lógicas conviviendo en la misma planilla:

| Rol | Componente variable | Componente fijo |
|---|---|---|
| Piloto | Hectáreas validadas del período × 7 Bs/ha | — |
| Auxiliar | Hectáreas validadas del período × 4,25 Bs/ha | — |
| Jefe de campo | — | Sueldo mensual |
| Encargado de operaciones | — | Sueldo mensual |

Cálculo: devengado − anticipos del período − descuentos = líquido a pagar. Estados: `borrador → aprobada → pagada`. Solo el dueño aprueba. Una planilla aprobada no se edita: se anula y se regenera, dejando rastro. Salidas: planilla consolidada (PDF y Excel) y recibo individual por persona.

Reglas: solo entran hectáreas validadas de las sesiones de cada persona (si dos pilotos compartieron lote, cada uno cobra sus propias hectáreas); las sesiones cerradas sin validar quedan pendientes para el período siguiente; los anticipos otorgados se descuentan automáticamente; si el líquido da negativo, el saldo se arrastra y dispara alerta.

---

## 12. Mantenimiento e inventario

Tres tipos de equipo con unidades de uso distintas: dron (horas de vuelo y hectáreas), vehículo (kilómetros), generador (horas de uso). El plan preventivo define tareas por intervalo; el sistema avisa al acercarse al umbral y abre la orden. Cada orden cerrada consume repuestos del stock y genera un gasto imputado a ese equipo.

Inventario de repuestos: costo promedio ponderado (recalculado en cada compra); punto de reposición por base con alerta; marca de repuesto crítico (el que deja un dron en tierra) con stock mínimo garantizado en base; trazabilidad por serie en componentes que la tengan (ESC, motores, baterías); traslados entre bases como movimiento, no como compra. Regla clave: un repuesto que sale del stock siempre se imputa a un equipo.

---

## 13. Portal del cliente

Acceso separado, de solo lectura, limitado al contrato del usuario. Ve: reportes técnicos por lote, reportes comerciales por aplicación, actas firmadas, hectáreas aplicadas vs. contratadas, historial de la campaña. No ve: costos, márgenes, personal, gastos, inventario, ni datos de otros clientes.

**Aislamiento obligatorio:** toda consulta del portal nace desde el `contrato` del usuario autenticado (`$usuario->contrato->actas()...`), nunca desde la tabla global con un `where` agregado después. Test explícito en CI para cada endpoint nuevo del portal: usuario del cliente A pide un recurso del cliente B → 404 siempre.

---

## 14. Gestión de usuarios

Alta, baja (lógica) y bloqueo de usuarios; asignación de uno o más roles y base, con un único login por persona; reseteo de contraseña. Roles del sistema: piloto, auxiliar, jefe de campo, encargado de operaciones, dueño, cliente. Un usuario puede tener más de un rol (ver sección 3 y ADR 0004); la regla de que nadie valida su propio trabajo se aplica a nivel de persona, no de rol.

---

## 14.1 Convenciones transversales: borrado lógico y bitácora de auditoría

Dos reglas que aplican a **todo** el sistema, no solo a usuarios — detalladas en ADR 0007:

- **Borrado lógico (soft delete) por defecto** en todo modelo de dominio. Ningún recurso (cliente, producto, dron, persona, orden…) se elimina físicamente; queda marcado como eliminado y deja de listarse por defecto, pero se conserva para no romper referencias históricas y para auditoría.
- **Bitácora de auditoría transversal**: toda creación, modificación, borrado lógico y cambio de estado relevante registra quién (actor autenticado), cuándo, sobre qué entidad, qué acción, y — donde aplique — los valores antes/después. No se limita a validaciones, planillas, gastos y movimientos de stock: es un mecanismo de plataforma (trait/observer), no una tarea que cada módulo implemente por separado.
- **Excepción única al "valores antes/después": los secretos de configuración.** Las llaves y tokens de `/panel/configuracion` registran el cambio (quién, cuándo, qué clave) pero **nunca su valor**, ni el viejo ni el nuevo — si no, la bitácora sería el lugar más fácil del sistema para leer todas las llaves en claro. Ver ADR 0016.

**Configuración del sistema vs. datos de la empresa** — son dos pantallas distintas y no se mezclan: `/panel/organizacion` guarda quién es la empresa (identidad y datos de facturación); `/panel/configuracion` guarda con qué parámetros funciona la herramienta (llaves de mapas, correo, integraciones), cifrados en reposo, con `.env` como respaldo y sin volver nunca al navegador (ADR 0016).

---

## 15. Fases de desarrollo

**Ruta crítica** — debe estar operativo antes de la primera aplicación:

| Fase | Contenido |
|---|---|
| 1 | Usuarios y roles · órdenes de aplicación · recetas y preparación de mezcla · trabajos · sesiones · condiciones · recargas · incidencias · cierre con evidencia · sincronización offline |
| 2 | Validación cruzada · actas · firma del agrónomo · reporte técnico |

Sin la fase 1 no hay dato, y el dato de campo no se recupera después. Sin la fase 2 no hay documento cobrable.

**Entra en marcha** — puede desarrollarse con la campaña andando:

| Fase | Contenido | Depende de |
|---|---|---|
| 3 | Gastos · rendiciones · combustible · prorrateo · contabilidad básica | Trabajos ya cargados (fase 1) |
| 4 | Devengos · anticipos · planilla de pagos · facturación · cobranzas | Hectáreas validadas (fase 2) |
| 5 | Inventario de repuestos · movimientos · costeo | Independiente, pero alimenta la 6 |
| 6 | Mantenimiento de drones, vehículos y generadores | Inventario (fase 5) y uso acumulado (fase 1) |
| 7 | Portal del cliente · reporte comercial | Actas firmadas (fase 2) |
| 8 | Dashboard · alertas por excepción | Datos de todas las anteriores |

Advertencia de alcance: entre las fases 3 y 8 hay más trabajo que en las dos de la ruta crítica juntas — no arriesgar la fase 1 por adelantar fases posteriores. Carga manual transitoria: mientras las fases 3–6 no estén, gastos y stock se llevan en planilla de cálculo con los mismos rubros, para que la migración sea una importación.

El calendario concreto (sprints, historias de usuario, betas) vive en `docs/gestion/plan_sprints.md`.

---

## 16. Supuestos a confirmar

- La app de campo corre en el RC del Agras (Android), con control de actualizaciones por red (`GET /api/version`, sin Firebase/FCM — polling, no push).
- Un solo cliente contratante en v1, pero el modelo admite varios contratos, propiedades y campos.
- La planilla es una liquidación interna de pagos, no un documento laboral normado. Sin aportes ni retenciones.
- Sueldos del jefe de campo y del encargado de operaciones: se cargan como parámetro, sin impacto en el diseño.
- Tolerancia de solape entre sesiones: parámetro configurable, a definir con la experiencia de campo.
- Catálogo inicial de productos y coadyuvantes habituales, con su formulación, para no cargarlos a mano en plena campaña.
- Tolerancia de desvío de mezcla: propuesta ±5%, a validar con el agrónomo.
- Las firmas del agrónomo se capturan como firma en pantalla o foto del acta física.
- Sin integración con la API de DJI en v1: las hectáreas son declaradas y respaldadas por captura de RC.

El contexto de negocio completo (cadena comercial, economía del piloto, conflictos típicos, guía de conversación con pilotos y operarios) vive en `docs/negocio/ventana_al_negocio.md`.
