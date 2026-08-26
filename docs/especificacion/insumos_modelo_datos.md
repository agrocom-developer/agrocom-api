# Insumos del campo para el modelo de datos

**Agrocom SRL · Documento de trabajo vigente · Derivado de las respuestas de campo del 25/8/2026**

Prepara la consolidación del modelo de datos (espec §4) con lo que las respuestas revelaron: módulos potenciales, procesos críticos, ajustes a las máquinas de estado y la separación de superficies. **No modifica la especificación**: es el insumo para hacerlo en una iteración dedicada, cuando lleguen las **capturas del RC** (pantallas reales del flujo DJI) que permitan cerrar campos y formatos exactos.

---

## 1. Potenciales módulos de dominio (`app/Dominios/`)

Un módulo = una carpeta con `Contratos/`, `Aplicacion/`, `Dominio/`, `Infraestructura/` (ADR 0003). Propuesta derivada de agrupar entidades por rol escritor y por fase:

| Módulo | Entidades (espec §4 + hallazgos) | Rol escritor dominante | Fase |
|---|---|---|---|
| **Comercial** | clientes, contratos, campos, lotes, **contactos del cliente** (nuevo: el "encargado de la propiedad"), **parámetros por contrato** (ventanas, límites, velocidad máx.) | Encargado / dueño | 1 |
| **Operaciones** | ordenes_aplicacion, trabajos, sesiones, condiciones, **pausas** (nuevo), incidencias, evidencias, actas | Piloto (app RC) | 1–2 |
| **Mezcla** | recetas_mezcla, receta_items, productos, mezclas (+ **origen**: cliente/Agrocom), mezcla_items, sobrantes, recargas | Auxiliar (app celular) | 1 |
| **Personal** | personas, devengos_personal, anticipos, liquidaciones, planillas, planilla_detalle | Sistema / dueño | 4 |
| **Finanzas** | rubros, subrubros, gastos, cargas_combustible, rendiciones, fondos_caja, facturas, cobranzas | Encargado / jefe | 3–4 |
| **Inventario** | repuestos, stock, movimientos_stock, **pedidos_repuesto** (nuevo: circuito solicitado→enviado→recibido con tiempos) | Encargado | 5 |
| **Mantenimiento** | equipos, generadores, planes_mantenimiento, ordenes_mantenimiento, **checklist de jornada** (nuevo: limpieza diaria del dron) | Jefe / auxiliar | 6 |
| **Reportes** | reporte técnico, comercial, de avance por umbral, dashboard/estado de resultados, mapa de avance | Sistema (lectura) | 2, 7–8 |
| **Portal** | vistas de solo lectura sobre Comercial/Operaciones/Reportes, scoping por contrato | Cliente (lectura) | 7 |
| **Seguridad** | `sec_*` (ADR 0004) | Plataforma | 1 |
| **Sync** | cola de sincronización, resolución de `uuid_cliente` | Plataforma | 1 |

Regla de oro que el campo confirmó: **cada tipo de registro tiene exactamente un rol escritor** (sesión → piloto, mezcla/recarga → auxiliar, validación → jefe, gasto contable → encargado). Celda en revisión: quién registra el sobrante cuando lo riega el piloto en las cortinas (hoy lo ejecuta el piloto, el modelo lo asigna al auxiliar).

## 2. Procesos críticos (revisión línea por línea, nunca solo por diff)

Los de CLAUDE.md, ratificados y ampliados por el campo:

1. **Motor de sync** (offline-first, idempotencia por `uuid_cliente`) — sin señal en el lote, todo se juega aquí.
2. **Cierre de sesión con hectárea acumulada** — el control de doble conteo en relevos; el campo confirma que las capturas ya evitan disputas y el modelo debe preservar eso.
3. **Validación y devengo** — genera dinero; validador ≠ piloto a nivel de persona (crítico: el dueño también vuela).
4. **Registro de pausas atribuibles** — nuevo en la lista: protege la factura ante demoras del cliente ("se pueden perder horas" y hoy no queda rastro) y explica los días malos.
5. **Mezcla en dos escenarios** — asigna responsabilidad del caldo; hoy la disputa se resuelve "de palabra".
6. **Scoping del portal** — cliente A pidiendo recurso de cliente B → 404, con test por endpoint.

## 3. Ajustes a las máquinas de estado (propuestas, a confirmar en la consolidación)

- **Sesión**: sin cambios de estados, pero con **pausas** como registros hijos (inicio, fin, causa: `clima` / `imprevisto_del_cliente` / `falla_equipo` / `logistica`), no como estados — una sesión pausada sigue `en_ejecucion`.
- **Trabajo**: agregar la salida `suspendido_por_cliente` (caso real: cambio de lote ordenado con el caldo listo; se volvió 3–4 días después) con autor y motivo — hoy caería en `parcial` sin explicar por qué.
- **Mezcla**: agregar origen y el estado `recibida_de_cliente` (escenario dominante hoy) junto a `en_preparacion → lista → cargada → anulada` del escenario Agrocom.
- **Acta**: `generada → pendiente_firma → firmada`, admitiendo firma por lote inmediata **o agrupada al cierre de la aplicación** (flexibilización aceptada por el agrónomo para no comer ventana) — decisión de negocio pendiente porque tensiona el racional comercial del acta inmediata (`ventana_al_negocio.md` §2.2).
- **Pedido de repuesto** (máquina nueva, módulo Inventario): `solicitado → cotizado → comprado → enviado → recibido`, con tiempos por tramo (el envío por trufi/encomienda es un tramo con duración propia: 4–48 h).
- **Orden de aplicación**: sin cambios (`emitida → vigente → consumida | vencida`).

## 4. Cambios puntuales al modelo de datos sugeridos por el campo

| Entidad | Cambio | Fuente |
|---|---|---|
| `drones` | Catálogo de modelos: agregar **T30** (operado hoy; una de las dos caídas relatadas, junto al T50) y revisar T70P; volúmenes de carga por modelo parametrizados (T30 20–26 L, T40/T50 30–36 L) | piloto/auxiliar/jefe |
| `clientes` | Contactos operativos: **encargado de la propiedad** (indica lotes, prepara caldo, ordena pausas — opera mucho, no firma nada) | los 5 cuestionarios |
| `contratos` | Parámetros: ventanas horarias permitidas, límites de condiciones, velocidad máxima exigida, umbral de reporte de avance (~500 ha) | piloto/encargado/agrónomo |
| `mezclas` | `origen` (cliente / agrocom) + quién preparó; el checklist §7 aplica solo al origen agrocom | los 3 auxiliares + 3 pilotos |
| `sesiones` | Hija nueva `pausas` (causa, inicio, fin, atribuible_a) | encargado ("falta siempre") |
| `incidencias` | Tipos nuevos: `salud_personal` (intoxicación/enfermedad en campaña), `caida_dron`; señal `rpm_anormal` como detección de grumos | jefe/auxiliar |
| `condiciones` | Humedad como rango (80–95%) además de mínima; registro de con qué se midió (anemómetro) | agrónomo |
| `ordenes_aplicacion` | Parámetros de vuelo acordados (altura, velocidad, ancho de pasada) — hoy los definen piloto+agrónomo a voz y no quedan en la orden | piloto |
| `sobrantes` | Destino explícito `aplicado_en_cortinas` (práctica universal) | auxiliares |
| `gastos` | Escalera de autorización por monto parametrizable (jefe ~500 → encargado ~1.000 → dueño); medio `qr` con captura como evidencia | jefe/encargado |
| `trabajos` | Motivo de no aplicación por **ausencia de contraparte del cliente** (caso real: 1 ha sin fumigar porque no había nadie que indicara el lugar) | piloto |
| `evidencias` | Tipo `captura_qr` (rendiciones y pagos) | encargado |

**Pendiente de las capturas del RC** (bloquea la consolidación): campos exactos del reporte de misión DJI (qué números muestra: ha, tiempo, caudal, altura, % avance), formato del mapeo y sus obstáculos, y qué muestra la pantalla al retomar misión de otro piloto — definen qué se transcribe, qué se adjunta como imagen y qué se puede validar automáticamente.

## 5. Separación de superficies (qué proceso vive dónde)

| Proceso | App RC (piloto) | App celular (auxiliar) | Panel web (jefe/enc./dueño) | Portal (cliente) |
|---|---|---|---|---|
| Ver órdenes y lotes del día | ✔ | ✔ (consulta) | ✔ | ✔ (las suyas) |
| Emitir orden de aplicación | — | — | ✔ (transcribe el jefe/enc. con evidencia) ¹ | ¿emite el agrónomo? ¹ |
| Plan del día (lotes, parejas, turnos) | consulta | consulta | ✔ jefe | — |
| Abrir/cerrar trabajo y sesión | ✔ | — | ✔ jefe (excepción) | — |
| Condiciones y autorización fuera de rango | ✔ registra | — | — | firma agrónomo ¹ |
| Pausas con causa | ✔ | ✔ | — | — |
| Mezcla (ambos escenarios) | — | ✔ | — | — |
| Recargas, batería, combustible | — | ✔ | — | — |
| Incidencias | ✔ | ✔ | ✔ | — |
| Checklist prevuelo / cierre de jornada | ✔ | ✔ | — | — |
| Validar sesiones | — | — | ✔ jefe/enc. (≠ piloto, por persona) | — |
| Acta: presentar / firmar | ✔ presenta | — | — | ✔ firma ¹ |
| Gastos, rendiciones, caja chica | — | — | ✔ jefe registra, enc. procesa | — |
| Compras, stock, pedidos de repuesto | — | — | ✔ encargado | — |
| Anticipos (aprobar / entregar) | — | — | ✔ dueño / encargado | — |
| Planilla (generar / aprobar) | — | — | ✔ enc. / dueño | — |
| Facturas y cobranzas | — | — | ✔ dueño (práctica actual) ² | consulta |
| Reportes técnicos/comerciales/avance | — | — | ✔ | ✔ (solo su contrato) |
| Dashboard / estado de resultados | — | — | ✔ dueño (enc. sin línea de ganancia) | — |

¹ **Decisión abierta**: la espec §13 declara el portal de solo lectura, pero el agrónomo emite órdenes y firma actas. Opciones: (a) el portal gana esas dos acciones de escritura acotadas, o (b) la orden la transcribe Agrocom con evidencia del original (WhatsApp/papel) y la firma se captura en la app del RC presentada por el piloto (práctica actual). La (b) es la de menor fricción para v1 y no obliga a extender el modelo de guards y permisos del portal (ADR 0004); decidir antes de la fase 2.
² La espec §3 permite al encargado registrar cobranza/facturar; la práctica actual es solo-dueño ("no tengo ese dato, lo maneja el dueño con trato directo con el cliente"). Mantener el permiso, registrar la práctica.

**Regla UX transversal de la app RC** (unánime): captura solo con el dron en tierra — inicio de jornada, mapeo/esperas, cambio de lote, pausas, cierre. "En pleno vuelo nada se debe hacer, solo pilotear."
