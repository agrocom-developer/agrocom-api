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

### 4.1 Comercial

- `clientes` — id, razón social, nit, contacto dueño, contacto agrónomo
- `contratos` — id, cliente_id, hectáreas_contratadas, aplicaciones_previstas, precio_ha, monto_total, adelanto_monto, adelanto_pct, fecha_inicio, fecha_fin, estado
- `campos` — id, cliente_id, nombre, ubicación
- `lotes` — id, campo_id, código, hectáreas, geometría (GeoJSON), restricciones (texto: cables, viviendas, colmenas, vecinos sensibles)

### 4.2 Recursos

- `drones` — id, modelo (T50/T70/T100), serie, base_id, estado, horas_vuelo
- `baterias` — id, código propio (ej. T50-A-01), serie DJI, ciclos, fecha_alta, estado, dron_actual_id
- `vehiculos` — id, tipo, placa, base_id, gasolina o diésel
- `bases` — id, nombre, ubicación
- `personas` — id, nombre, rol, tarifa_ha (piloto/auxiliar), sueldo_mensual (jefe/encargado), base_id, activo

### 4.3 Operación

- `ordenes_aplicacion` — id, contrato_id, lote_id, nro_aplicacion, litros_ha, humedad_minima, observaciones, emitida_por (agrónomo), fecha_emision, estado
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

### 4.4 Financiero

- `rubros` — id, nombre (los 8 del presupuesto + Indirectos), presupuesto_bs_ha
- `subrubros` — id, rubro_id, nombre, tipo_imputacion (directo_dron / directo_vehiculo / compartido)
- `gastos` — id, fecha, rubro_id, subrubro_id, cantidad, precio_unitario, monto, base_id, dron_id, vehiculo_id, medio_pago, evidencia_id, rendicion_id, cargado_por, tiene_comprobante (bool)
- `cargas_combustible` — id, fecha, litros, destino (generador / camioneta), dron_id, vehiculo_id, registrado_por, gasto_id. *El auxiliar registra litros; el encargado carga precio; se vinculan después — separa desvío de precio de mercado de desvío de consumo real.*
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

## 7. Preparación de mezcla

Vive en la app del auxiliar. Es el punto donde una aplicación se arruina sin que nadie se dé cuenta hasta 15 días después.

### 7.1 Cálculo automático

```
hectáreas por tanque = volumen de carga ÷ litros por hectárea de la orden
cantidad de producto = dosis por hectárea × hectáreas por tanque
                        (o dosis por 100 L × volumen ÷ 100, según la unidad)
```

Volúmenes de carga reales por modelo (no nominales):

| Dron | Carga habitual |
|---|---|
| T50 | 30 L |
| T70 | 50 L |
| T100 | 60 L |

El T100 admite más, pero cargarlo al máximo devuelve baterías muy descargadas y calientes.

### 7.2 Checklist secuencial

Lista ordenada bloqueante: no se habilita el paso siguiente sin confirmar el anterior con la cantidad realmente incorporada. Orden por defecto (configurable y sobrescribible por el agrónomo en cada receta):

1. Agua: 50–75% del volumen final, con agitación activa
2. Corrector de pH / acidificante
3. Antiespumante
4. Formulaciones sólidas (WG, WP) pre-disueltas aparte
5. Suspensiones concentradas (SC)
6. Solubles (SL)
7. Emulsionables (EC, EW, OD)
8. Aceites, coadyuvantes y surfactantes
9. Antideriva
10. Completar agua hasta el volumen final

### 7.3 Registros asociados

- Foto de la mezcla o etiquetas como evidencia.
- Confirmación de EPP al iniciar (guantes, protección respiratoria, antiparras, ropa impermeable).
- Sobrantes: volumen, destino y triple lavado de envases.
- Vinculación mezcla → recarga → sesión → lote.

### 7.4 Qué protege esto

Demuestra, tanque por tanque, que se incorporó lo que la orden pedía, en el orden y cantidad que pedía. El desvío entre cantidad calculada y real es control interno propio: si aparece de forma sistemática, hay un problema de proceso antes de que se convierta en reclamo.

---

## 8. Endpoints principales

```
POST   /api/sync                      Lote de registros offline (idempotente por uuid)
GET    /api/ordenes?lote_id=&estado=  Órdenes vigentes para el piloto
POST   /api/trabajos                  Abrir trabajo (valida orden + condiciones)
POST   /api/trabajos/{id}/sesiones    Abrir sesión (piloto + dron + ha acumulada)
POST   /api/sesiones/{id}/condiciones Registrar condiciones
POST   /api/mezclas                   Abrir mezcla (devuelve cantidades calculadas)
POST   /api/mezclas/{id}/items/{n}    Confirmar paso con cantidad real
POST   /api/mezclas/{id}/cerrar       Cierra mezcla, EPP y evidencia
POST   /api/mezclas/{id}/sobrante     Registrar sobrante y destino
POST   /api/sesiones/{id}/recargas    Registrar recarga + batería + mezcla
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
GET    /api/portal/reportes           Portal cliente (solo su contrato)
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
- Un solo cliente contratante en v1, pero el modelo admite varios contratos y campos.
- La planilla es una liquidación interna de pagos, no un documento laboral normado. Sin aportes ni retenciones.
- Sueldos del jefe de campo y del encargado de operaciones: se cargan como parámetro, sin impacto en el diseño.
- Tolerancia de solape entre sesiones: parámetro configurable, a definir con la experiencia de campo.
- Catálogo inicial de productos y coadyuvantes habituales, con su formulación, para no cargarlos a mano en plena campaña.
- Tolerancia de desvío de mezcla: propuesta ±5%, a validar con el agrónomo.
- Las firmas del agrónomo se capturan como firma en pantalla o foto del acta física.
- Sin integración con la API de DJI en v1: las hectáreas son declaradas y respaldadas por captura de RC.

El contexto de negocio completo (cadena comercial, economía del piloto, conflictos típicos, guía de conversación con pilotos y operarios) vive en `docs/negocio/ventana_al_negocio.md`.
