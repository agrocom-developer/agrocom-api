# Cómo encarar el desarrollo — Sistema de Gestión de Operaciones de Fumigación

**Agrocom SRL · Documento de trabajo · Complementa la Especificación Técnica v1.0**

Este documento responde una sola pregunta: **cómo construir lo que la especificación describe, en el orden correcto, con un desarrollador principal trabajando con agentes de IA, y llegando antes de la primera aplicación de la campaña.**

---

## 1. Qué clase de sistema es (el modelo mental)

Antes de discutir stack o módulos, conviene nombrar qué es este sistema, porque esa definición ordena todas las decisiones técnicas:

**Es un sistema de registro de eventos operativos con consecuencias financieras, no un ERP.**

- El **activo central es la trazabilidad**: cada hectárea facturada se reconstruye hasta su origen. Eso implica que los registros de campo son *hechos* que ocurrieron — no filas editables. De ahí salen tres reglas de la spec que no son negociables: idempotencia por UUID, el servidor nunca sobrescribe un registro validado, y las correcciones son registros nuevos que anulan al anterior.
- El **dinero es derivado, nunca capturado**: el devengo del piloto no se "carga", se *genera* al validar una sesión. La factura no se inventa, sale de actas. Si los eventos de origen están bien, la plata cuadra sola; si están mal, ninguna pantalla lo arregla.
- La **captura ocurre sin conectividad y el resto del sistema con conectividad**. Esto divide el proyecto en dos mundos con exigencias distintas: la app de campo (offline-first, robusta, mínima) y el backend/panel (online, convencional, 80% CRUD y reportes).

Consecuencia práctica: la dificultad del proyecto **no está repartida uniformemente**. Cuatro problemas concentran el riesgo técnico; el resto es volumen de trabajo convencional que los agentes de IA producen rápido.

---

## 2. Los cuatro problemas que definen el proyecto

### 2.1 Sincronización offline e idempotencia

Es el problema más difícil y el que hay que construir **primero**, porque todo lo demás se apoya en él.

**Diseño propuesto (protocolo completo):**

1. **Base local SQLite en el dispositivo** (en Flutter: `drift`). Toda escritura del piloto/auxiliar va primero a la base local; la interfaz **siempre lee de la base local**, nunca del servidor. La app funciona idéntica con o sin señal.
2. **Patrón outbox**: una tabla `cola_sync` local registra cada escritura pendiente con su `uuid_cliente`, tipo de entidad, payload JSON y orden causal. Estados por registro: `pendiente → enviado → confirmado | rechazado`.
3. **Push en lote**: `POST /api/sync` recibe un arreglo ordenado causalmente (trabajo antes que sesión, sesión antes que recarga). El servidor procesa **registro por registro en transacciones individuales** — no todo el lote en una transacción — y devuelve estado por registro: `aplicado`, `duplicado` o `rechazado {motivo}`. Un rechazo no frena el resto del lote.
4. **Idempotencia en la base, no en el código**: restricción `UNIQUE (uuid_cliente)` en cada tabla operativa de PostgreSQL. Reintento de un lote ya aplicado → `ON CONFLICT` → respuesta `duplicado`, que el cliente trata como éxito. Así una sincronización repetida **no puede** duplicar hectáreas aunque haya un bug en el cliente.
5. **Referencias por UUID de cliente**: una sesión creada offline referencia a su trabajo por el `uuid_cliente` del trabajo, no por el id del servidor (que aún no existe). El servidor resuelve la referencia al aplicar.
6. **Pull de catálogo**: `GET /api/sync/catalogo?desde={cursor}` baja órdenes vigentes, recetas, productos, lotes y personal, con cursor por `updated_at`. Se ejecuta al abrir la app, al recuperar señal y a demanda. El piloto sale al lote con las órdenes ya en el dispositivo.
7. **Evidencias en cola separada**: los registros (livianos, críticos) sincronizan primero; las imágenes comprimidas (<300 KB) van en una segunda cola referenciada por UUID. Una sesión puede quedar `confirmada` con su evidencia aún subiendo — pero **la validación exige la evidencia ya subida**.
8. **Relojes**: se guarda la hora del dispositivo y el `recibido_en` del servidor. El orden entre eventos de una misma sesión lo da un campo `secuencia` local, nunca la comparación de relojes entre dispositivos.

**La simplificación que elimina los conflictos:** cada tipo de registro tiene **exactamente un rol escritor** (la sesión la escribe el piloto, la mezcla el auxiliar, la validación el jefe desde el panel). No hay dos dispositivos editando la misma fila, por lo tanto **no hace falta lógica de merge** — solo inserciones idempotentes y anulaciones. Mantener esta propiedad al diseñar cualquier pantalla nueva es lo que mantiene el sync simple. Si alguna vez una pantalla necesita que dos roles editen el mismo registro, la respuesta correcta es dividirlo en dos registros.

**Prueba de fuego (test automatizado obligatorio):** reproducir el mismo lote de sincronización dos, tres, diez veces, en orden y en desorden parcial → el estado final de la base debe ser idéntico. Este test se escribe antes que la primera pantalla.

### 2.2 Máquinas de estado con dinero colgando

Trabajo y sesión tienen máquinas de estado explícitas (spec §5), y de dos transiciones sale plata: `sesión → validada` genera devengos, `trabajo → conformado` habilita factura.

**Implementación:** las transiciones viven en **una tabla de transiciones permitidas + un servicio de dominio** en Laravel (enum de estados, guardas por transición, excepción si la transición no existe). Nunca `UPDATE estado = ...` suelto en un controlador. Cada transición escribe en la tabla de auditoría: quién, cuándo, de qué estado a cuál, motivo.

**El devengo se genera por evento de dominio** (`SesionValidada` → listener crea los devengos del piloto y auxiliar de esa sesión), con restricción `UNIQUE (sesion_id, persona_id)` para que un doble disparo no pague dos veces. Las reglas de la spec se traducen directo a guardas: validador ≠ piloto de la sesión (a nivel persona, no rol); suma de sesiones ≤ hectáreas del lote + tolerancia; sin orden vigente no se abre trabajo.

**Dinero en `DECIMAL`, jamás float.** Hectáreas `DECIMAL(10,2)`, montos `DECIMAL(12,2)`. Todo monto derivado (devengo, planilla, factura) debe poder **recalcularse desde los registros de origen** y cuadrar exacto — ese recálculo es un comando artesanal (`php artisan cuadrar:periodo`) que corre antes de aprobar cada planilla.

### 2.3 Inmutabilidad y correcciones

"El servidor nunca sobrescribe un registro validado" se implementa así: los modelos operativos validados quedan **bloqueados a nivel de aplicación** (observer que lanza excepción ante update) y la corrección es un registro nuevo con `anula_a_id`, motivo y autor. Las consultas operativas filtran anulados; las de auditoría los muestran. Es event-sourcing *conceptual* sin el costo de un event store real — no implementar event sourcing formal: para este tamaño de sistema es sobreingeniería.

### 2.4 Aislamiento del portal del cliente

La spec lo dice bien: el filtro por contrato va **a nivel de consulta, no de interfaz**. Implementación: guard de autenticación separado para usuarios-cliente, endpoints bajo `/api/portal/*`, y **toda consulta del portal nace desde `contrato` del usuario autenticado** (`$usuario->contrato->actas()...`), nunca desde la tabla global con un `where` agregado después. Se acompaña de un test explícito: usuario del cliente A pide un recurso del cliente B → 404 siempre. Ese test corre en CI para cada endpoint nuevo del portal.

---

## 3. Arquitectura en profundidad

### 3.1 Backend: monolito modular Laravel

Un solo proyecto Laravel, PostgreSQL, API REST con Sanctum. **Nada de microservicios**: un desarrollador, un dominio cohesionado, transacciones que cruzan módulos (validar sesión toca operaciones y finanzas) — el monolito es la respuesta correcta, y la modularidad se logra con carpetas de dominio:

```
app/
  Dominios/
    Operaciones/   (trabajos, sesiones, condiciones, recargas, incidencias)
    Mezclas/       (recetas, mezclas, sobrantes, productos)
    Comercial/     (clientes, contratos, campos, lotes, órdenes)
    Finanzas/      (gastos, devengos, anticipos, planillas, facturas, cobranzas)
    Mantenimiento/ (equipos, planes, órdenes, repuestos, stock)
    Portal/        (consultas de solo lectura, scoping por contrato)
    Sync/          (protocolo offline, idempotencia, evidencias)
  Compartido/      (estados, auditoría, evidencias, PDF)
```

Los límites de módulo coinciden con las fases de la spec §15 — no es casualidad, es lo que permite congelar fases 3–8 sin que contaminen la ruta crítica.

**PostgreSQL se usa a fondo, no como almacén tonto:** `UNIQUE` sobre `uuid_cliente` (idempotencia), `CHECK` sobre enums y rangos, índices parciales (p. ej. órdenes `estado = 'vigente'`), `JSONB` para la geometría GeoJSON de lotes. **PostGIS no hace falta en v1** — la geometría solo se guarda y se dibuja, no se consulta espacialmente.

### 3.2 App de campo: un código Flutter, dos perfiles

La recomendación de la spec es correcta y se implementa con **build flavors**: `flutter build apk --flavor piloto` y `--flavor auxiliar` producen dos APKs con ícono y nombre distintos, que comparten el 70% real del código: motor de sync, base local, autenticación, cola de evidencias, compresión de imágenes. Cada flavor monta solo sus pantallas.

**Restricciones reales del RC del Agras que hay que verificar en la semana 1** (spike de hardware, medio día con el equipo en mano):

- El RC corre Android (versión vieja, ~10): confirmar `minSdkVersion`, permisos de instalación de APK de origen desconocido, y que no haya Google Play Services garantizados → **no depender de Firebase/FCM**; el sync es por polling y por evento de conectividad, no por push.
- **El flujo de la captura de RC**: el piloto toma el screenshot de la app de DJI con los botones del RC, y la app de Agrocom lo levanta de la galería al cerrar la sesión. Verificar acceso a la galería/almacenamiento en esa versión de Android. Este flujo es el corazón de la evidencia — se prueba antes de escribir una sola pantalla más.
- Brillo/tamaño de pantalla: interfaz de botones grandes, alto contraste, cero tipografía fina.

**Control de actualizaciones (requisito propio):** distribución por APK autohospedado, no por Play Store. La app consulta `GET /api/version` al abrir: si hay versión nueva *autorizada*, ofrece descargar; si la instalada quedó por debajo de la mínima permitida, bloquea hasta actualizar. El dueño autoriza versiones desde el panel. Esto también resuelve el despliegue a los RC en campo vía Starlink.

### 3.3 Panel web: la decisión React vs. Filament (a cerrar en la reunión)

La spec propone React. Hay una alternativa que para un desarrollador solo cambia el calendario: **Filament** (panel de administración nativo de Laravel).

El panel es, en su mayoría: CRUDs (usuarios, productos, contratos, lotes, repuestos), colas de trabajo (validaciones pendientes, rendiciones, alertas), formularios financieros y reportes exportables. Eso es exactamente lo que Filament da hecho: tablas con filtros, formularios con validación, permisos por rol, exportación, widgets de dashboard — integrado con la misma autenticación y modelos del backend, sin SPA separada, sin CORS, sin segundo pipeline de build.

| Criterio | React (SPA propia) | Filament |
|---|---|---|
| Tiempo del panel mínimo de ruta crítica | ~10–12 días | ~5–6 días |
| Control total del UX del dashboard | Total | Alto (widgets propios, Blade/Livewire donde haga falta) |
| Piezas móviles | API pública + SPA + auth por token | Una sola aplicación |
| Productividad de agentes de IA | Muy alta | Muy alta (convenciones fuertes ayudan al agente) |

**Recomendación: Filament para el panel interno y también para el portal del cliente** (segundo panel Filament con guard propio, o vistas Blade simples). React queda como opción si el dashboard del dueño exige un UX muy particular — y aun así puede agregarse después solo para esa vista, consumiendo la misma API. La API REST existe de todos modos porque la consumen las apps Flutter; no se pierde nada del contrato.

### 3.4 Evidencias, PDFs e infraestructura

- **Evidencias**: compresión en el dispositivo a <300 KB, hash SHA-256 calculado en el cliente y verificado al subir (integridad de la prueba), almacenamiento en un bucket S3-compatible (Cloudflare R2 o Backblaze B2 — costo despreciable a este volumen), servidas por URL firmada con expiración. Nunca públicas.
- **PDFs** (actas, reporte técnico, recibos, planilla): plantillas HTML renderizadas en el servidor. Generación automática al conformar/cerrar, guardado del PDF como evidencia más — el reporte enviado al cliente es un artefacto inmutable, no una vista que cambia si cambian los datos.
- **Despliegue**: un VPS es suficiente (decenas de usuarios, no miles): Laravel + PostgreSQL + colas con supervisord, HTTPS con Caddy/nginx. **Respaldo diario automatizado de PostgreSQL hacia el bucket + prueba de restauración mensual.** La base de datos es la campaña entera; el respaldo no es opcional. Un entorno de *staging* barato donde probar los APK antes de autorizar versión.

---

## 4. Plan de ejecución

### 4.1 El anclaje: la campaña manda

La spec §15 ya dice lo esencial: la hectárea que no se registró el martes se perdió. Trabajando hacia atrás desde una campaña de verano (primeras aplicaciones estimadas para fines de noviembre / diciembre), con arranque el 1 de septiembre quedan **~12 semanas** para tener la ruta crítica (fases 1 y 2) operativa **y probada en campo**. Es alcanzable a dedicación completa; no sobra nada.

### 4.2 La estrategia: esqueleto vertical primero

El error clásico sería construir módulo por módulo en horizontal (todas las migraciones, luego toda la API, luego todas las pantallas). La estrategia correcta con un solo desarrollador y agentes de IA es el **esqueleto que camina**: en las primeras 3 semanas, un flujo completo de punta a punta con campos mínimos —

> orden cargada en panel → piloto la ve en el RC sin señal → abre trabajo y sesión → cierra sesión con hectáreas y captura → sincroniza al volver a cobertura → jefe la ve y la valida en el panel → devengo generado

— aunque falten la mezcla, las condiciones, las incidencias y todo lo financiero. Ese esqueleto valida de una vez las tres apuestas riesgosas (sync, hardware del RC, máquina de estados) y después **solo se ensancha**, que es trabajo de bajo riesgo donde los agentes de IA rinden al máximo.

### 4.3 Secuencia y estimaciones (un desarrollador + agentes de IA, dedicación completa)

| Sem. | Bloque | Contenido | Criterio de salida |
|---|---|---|---|
| 1 | Fundaciones + spike RC | Repo, CI, migraciones del núcleo, seeds del catálogo; **APK de prueba corriendo en el RC real**: instalación, galería, screenshot, versión Android | El RC ejecuta un APK propio y lee un screenshot de la galería |
| 2–3 | Motor de sync | Base local drift, outbox, `POST /api/sync` idempotente, pull de catálogo, cola de evidencias; **test de replay** | El mismo lote aplicado 10 veces deja la base idéntica; sync probado con avión-modo en el RC |
| 3 | Esqueleto vertical | Flujo mínimo orden→trabajo→sesión→cierre→validación→devengo | Demo de punta a punta con datos reales de un lote |
| 4–5 | App piloto completa | Órdenes, condiciones (con rangos 30 °C / 17 km/h / 90%), sesiones con relevo y `hectarea_inicial_acumulada`, incidencias, cierre con evidencia | Todos los flujos del piloto operables sin señal |
| 6–7 | App auxiliar | Mezclas: cálculo automático, checklist secuencial bloqueante con cantidad real, EPP, sobrantes; recargas + batería + temperatura; combustible (litros); incidencias | Una mezcla completa registrada tanque por tanque contra una receta real |
| 7–8 | Panel ruta crítica | Usuarios/roles, catálogos, carga de órdenes y recetas, cola de validación, alertas mínimas (sin evidencia, suma excedida, sin orden) | El jefe opera su día completo desde el panel |
| 9–10 | Fase 2 | Validación cruzada (persona ≠ persona), actas con firma en pantalla, generación de PDF del acta y del reporte técnico | Acta firmada y reporte técnico en PDF generados desde datos reales |
| 11 | Ensayo general | Simulacro de campo: 1 dron, 1 lote real o de prueba, piloto y auxiliar reales, día completo con el sistema | Los operarios completan el ciclo sin asistencia del desarrollador |
| 12 | Colchón | Correcciones del ensayo, respaldo/restauración probados, staging→producción | Sistema declarado listo para la primera aplicación |

**Total ruta crítica: ~55–60 días efectivos.** Las fases 3–8 (gastos, planilla, inventario, mantenimiento, portal, dashboard) se desarrollan **con la campaña andando**, en este orden sugerido por urgencia de plata: fase 4 (devengos→planilla, porque la gente cobra a fin de mes) antes que la 3 (gastos, que admiten carga retroactiva desde la planilla transitoria de cálculo), luego 5–6–7–8. La spec ya prevé la carga manual transitoria con los mismos rubros — mantener esa planilla desde el día uno para que la migración sea una importación.

**Advertencia honesta sobre el calendario:** la semana 11 (ensayo general) no es recortable. Si algo se atrasa, se recorta alcance de las apps (p. ej. incidencias con foto puede entrar dos semanas tarde), nunca el ensayo. Un sistema que los pilotos ven por primera vez el día de la primera aplicación fracasa por adopción, no por código.

### 4.4 Trabajo que no es código y conviene disparar ya (no bloquea, pero tiene plazo)

- Catálogo inicial de productos con formulación (pedirlo al agrónomo del cliente **esta semana** — es su información, no de Agrocom).
- Lotes con geometría y restricciones (cables, colmenas, viviendas): relevarlos antes de campaña.
- Códigos físicos grandes para baterías (T50-A-01…) impresos y pegados.
- Definir con el agrónomo la tolerancia de solape y el ±5% de desvío de mezcla (supuestos §16).

---

## 5. Método de trabajo con agentes de IA

Con agentes como implementadores principales, el rol del desarrollador cambia: **escribe contratos y revisa pruebas; el agente escribe el resto.**

1. **La spec ya es el insumo correcto.** Convertirla en artefactos que el agente consume: un `CLAUDE.md` en el repo con las invariantes innegociables (idempotencia por UUID; nunca sobrescribir validados; devengo solo al validar y por sesión; validador ≠ piloto a nivel persona; portal siempre desde el contrato del usuario; dinero en DECIMAL; toda transición por el servicio de estados). El agente las relee en cada sesión — es la diferencia entre coherencia y deriva.
2. **Contratos antes que código**: migraciones y tablas de transición de estados se escriben (o se revisan a mano) primero; los endpoints se especifican con request/response de ejemplo. El agente implementa contra el contrato, no inventa el contrato.
3. **Una conversación por rebanada vertical**, no "hazme el módulo de finanzas". Sesiones cortas y enfocadas producen código revisable; sesiones enormes producen código que nadie leyó.
4. **Las pruebas son el instrumento de control.** Cada regla de la spec §5 (tabla de transiciones) se vuelve un test antes de implementarse. Prioridad de cobertura: (a) replay de sync, (b) transiciones prohibidas, (c) matemática de devengos/planilla con casos calculados a mano (dos pilotos comparten lote de 120 ha: 70 + 50 → Bs 490 + Bs 350), (d) aislamiento del portal. El desarrollador **lee y aprueba los tests aunque no lea todo el código** — y jamás acepta que un agente "arregle" un test financiero cambiando el valor esperado.
5. **Cada sesión de trabajo termina con la suite en verde y un commit.** CI simple (GitHub Actions: Pest + análisis estático con Larastan) desde la semana 1.
6. **Qué no delegar sin revisión línea por línea**: el motor de sync, el servicio de estados, los listeners que generan dinero, el scoping del portal. **Qué delegar con revisión ligera**: CRUDs, pantallas Flutter no críticas, recursos de Filament, plantillas PDF.

---

## 6. Riesgos principales

| Riesgo | Señal temprana | Mitigación |
|---|---|---|
| El sync resulta más difícil de lo estimado | El test de replay no está verde en la semana 3 | Se construyó primero justamente para descubrirlo con 9 semanas de margen; recortar alcance de pantallas, no del sync |
| Sorpresas de hardware del RC | El spike de la semana 1 falla (permisos, galería, Android) | Plan B: la app del piloto corre en un celular Android junto al RC; feo pero funcional, y no cambia una línea del backend |
| Scope creep hacia fases 3–8 antes de tiempo | "Ya que estamos" aparece en una conversación | Congelamiento explícito: hasta el ensayo general solo se acepta trabajo de fases 1–2; lo demás se anota |
| Un solo desarrollador, y la campaña lo absorbe | Semanas de <20 h efectivas en octubre | El orden del plan pone lo irreemplazable primero; la carga manual transitoria de la spec §15 es el paracaídas de las fases 3–6 |
| Adopción de los operarios | El ensayo general requiere asistencia constante | Pantallas de botones grandes, flujos de ≤5 pasos, y el ensayo en semana 11 con tiempo para corregir |
| Validación como cuello de botella humano | Sesiones cerradas sin validar se acumulan >48 h | Ya previsto en alertas; además la planilla muestra pendientes — presión natural del que quiere cobrar |
| Pérdida de datos | — | Respaldo diario a bucket + restauración probada mensualmente; no es negociable |

---

## 7. Qué cerrar en la reunión con el equipo

1. **Panel: Filament o React** (sección 3.3). Es la decisión con mayor impacto en calendario.
2. **Fecha real de la primera aplicación** — todo el plan cuelga de ahí; confirmarla con el cliente.
3. **Ratificar el orden de la ruta crítica** y el congelamiento de fases 3–8 hasta el ensayo general.
4. **Supuestos §16 de la spec**: tolerancia de solape, ±5% de mezcla (con el agrónomo), firma en pantalla vs. foto del acta física.
5. **Responsables de los datos maestros**: quién consigue el catálogo de productos, quién releva lotes y geometrías, para cuándo.
6. **Definición de "listo" de la fase 1**: la lista de criterios de salida de la tabla 4.3, aceptada por todos, para que "terminado" signifique lo mismo para todos.
7. **Fecha del ensayo general** en el calendario desde hoy — es el compromiso que protege todo lo demás.
