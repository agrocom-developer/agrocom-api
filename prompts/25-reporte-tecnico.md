<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/reporte-tecnico etapas=4 -->

# Tarea 25 — HU-18: reporte técnico por lote

`plan_sprints.md`, Sprint 5, fila HU-18: "Como agrónomo (cliente), quiero
recibir el reporte técnico por lote (imagen del campo, horas, condiciones,
mezcla ejecutada, sesiones), para verificar la aplicación". CA: "PDF
automático al conformar el lote; incluye dosis ordenada vs. incorporada y
superficie no aplicada con motivo".

Escrita asumiendo que la tarea 24 (HU-17, acta de conformidad) ya está
integrada — el disparador de esta HU es la firma del acta. Si no llegó a
integrarse, esta tarea queda `BLOQUEADA`: no hay "conformado" sin acta
firmada, y sin eso no hay qué generar.

No es crítica: no toca el motor de sync ni la máquina de estados de
`Trabajo`/`Sesion` (solo LEE su estado), no genera dinero, y no expone nada
por `/api/portal/*` (ese portal todavía no existe — ver "Qué es de este
repo y qué no").

## Qué es de este repo y qué no

**"Dosis ordenada vs. incorporada" y "mezcla ejecutada" NO se implementan.**
CR-01 (1/9/2026, espec §7) cerró que Agrocom no prepara ni dosifica el
caldo — no existe módulo `Mezclas`, no hay `receta_id`, `dosis_valor` ni
`cantidad_real` en ningún lado del esquema. La columna de CA de
`plan_sprints.md` es anterior a esa decisión (igual que la espec §9, que
sigue listando "mezcla ejecutada" en el contenido del reporte). El reporte
de esta tarea cubre TODO lo demás que el §9 pide y que sí tiene datos reales
detrás: imagen del campo, horas de inicio/fin, acta firmada, resumen
(hectáreas, litros de caldo/ha reales, condiciones), superficie no aplicada
y motivo, incidencias con evidencia, detalle de sesiones con relevo/cambio
de dron. Documentá la exclusión de mezcla en el PDF mismo (una línea, no un
placeholder vacío) y en `runs/25.md`.

**El portal del cliente no se construye acá.** La espec (§13) dice que el
agrónomo "recibe" el reporte desde un portal de solo lectura scopeado a su
contrato — ese portal es trabajo de Sprint 12 (`plan_sprints.md`, "Después
de la v1.0"), no existe todavía ni el modelo de `sec_user.type = cliente`
consumiéndolo. Esta tarea genera el PDF y lo deja descargable desde el
**panel interno** (jefe de campo / encargado / dueño, misma pantalla de
`trabajos/show.blade.php` que ya muestra el acta desde la tarea 24) —
"recibir" queda para cuando el portal exista. No es un recorte silencioso:
dejalo escrito en `runs/25.md`.

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend` y `seguridad-roles`. Leé
antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 342 (contenido
  obligatorio y complementario del reporte técnico), línea 328 (`GET
  /api/reportes/lote/{id}`, referencia informativa como el resto del §8), §6
  (línea 218-236, trazabilidad — el reporte es básicamente esa cadena
  resumida en PDF).
- El prompt de la tarea 24 (`prompts/24-acta-conformidad.md`) completo: fija
  el vocabulario que esta tarea reutiliza sin reabrir — "lote" en la espec es
  "trabajo" en este esquema (un `trabajo_id` por aplicación), "conformado" es
  un estado de `ope_actas`, no de `Trabajo`. Mismo criterio acá: el reporte
  es "por trabajo", no una tabla nueva de "lote".
- Lo que la tarea 24 haya dejado en `app/Dominios/Operaciones/Infraestructura/Eloquent/Acta.php`
  y `Aplicacion/MaquinaEstados/MaquinaEstadosActa.php` — el disparador de
  esta tarea es la transición `pendiente → firmada`. Si esa tarea dejó un
  evento de dominio explícito (`ActaFirmada` o similar), enganchá un listener
  ahí (mismo patrón que `SesionValidada` → devengo, tarea 16). Si NO dejó
  ningún evento —el prompt de la 24 no lo exigía—, extendé el caso de uso de
  firmar para disparar la generación al final de la misma transacción,
  documentando la decisión en `runs/25.md` (no reabras ni reescribas
  `MaquinaEstadosActa` para inventar un evento que la 24 no dejó, si alcanza
  con enganchar el caso de uso).
- `app/Dominios/Operaciones/Infraestructura/Eloquent/Trabajo.php`: relaciones
  ya existentes a reusar — `sesiones()`, `recepcionesCaldo()`,
  `imagenCampoEvidencia()`, `cuadreCaldo()`. `Sesion` trae `motivo_cierre`,
  `dron_id`, `piloto_id`, `auxiliar_id`, `litros_consumidos`. `Condiciones`
  (tarea 17), `Incidencia` (tarea 22) y `Recarga` (tarea 23) — todas
  referencian `sesion_id`/`trabajo_id` dentro del mismo módulo `Operaciones`,
  sin cruzar módulos (ADR 0003).
- `app/Dominios/Operaciones/Dominio/EstadoCoberturaTrabajo.php`: proyección
  de lectura (`Completo`/`Parcial`/`Observado`), NO persistida, calculada por
  `Aplicacion/CalcularCoberturaTrabajo.php`. Es la fuente de "superficie no
  aplicada y motivo": si la cobertura no es `Completo`, el reporte debe
  mostrar cuánto falta (hectáreas del lote − `hectareas_declaradas`) y el
  motivo (de las sesiones cerradas con motivo distinto de `completado`, si
  hay alguna).

### Diseño

1. **Migración `ope_reportes_tecnicos`**: `trabajo_id` FK real
   (`restrictOnDelete`, único — un trabajo tiene a lo sumo un reporte),
   `archivo_url` o ruta del PDF generado, `generado_en` datetime, auditoría +
   soft delete. Mismo criterio de idempotencia que `ope_actas`: no se
   regenera el PDF una vez creado (snapshot al momento de conformar, igual
   razón que `hectareas_conformadas` de la tarea 24 — si después se corrige
   una sesión, el reporte ya emitido no se mueve).
2. **Generación automática al firmar el acta** (ver el punto de "Qué
   hacer" sobre el disparador): crea la fila `ope_reportes_tecnicos` y el
   PDF en la MISMA operación que transiciona el acta a `firmada`, dentro de
   la misma transacción que el INSERT de la fila (mismo guardarraíl "INSERT
   antes que I/O" que usó la tarea 24 para el acta). Si el reporte ya existe
   para ese trabajo (reintento de firma idempotente sobre una acta ya
   `firmada`), no regenera nada.
3. **`GET /api/reportes/lote/{id}`** (`{id}` = `trabajo_id`, mismo criterio
   que `/api/trabajos/{id}/acta` de la tarea 24): devuelve el PDF (o su URL)
   si existe; `404`/rechazado si el trabajo todavía no tiene acta firmada.
   No genera nada al llamarse — solo sirve lo ya generado.
4. **Contenido del PDF** (dompdf, ya agregado como dependencia por la tarea
   24 — no agregues una segunda librería):
   - Imagen del campo (`Trabajo::imagenCampoEvidencia()`).
   - Hora de inicio y fin: `MIN(sesiones.inicio)` / `MAX(sesiones.fin)` del
     trabajo.
   - Acta de conformidad: número/fecha de firma, firmante (de `ope_actas`).
   - Resumen: hectáreas (`hectareas_declaradas` y, si corresponde, la
     cobertura de `EstadoCoberturaTrabajo`), litros de caldo/ha reales
     (`Trabajo::cuadreCaldo()` o el cálculo equivalente: litros
     consumidos ÷ hectáreas), condiciones (de `ope_condiciones`: viento,
     temperatura, humedad, si autorizado con observación).
   - Superficie no aplicada y motivo (ver arriba, `EstadoCoberturaTrabajo`).
   - Incidencias con evidencia (`ope_incidencias`, listado con tipo y foto).
   - Detalle de sesiones CUANDO hubo relevo o cambio de dron (más de una
     sesión vigente en el trabajo): piloto, dron, hectáreas, motivo de
     cierre de cada una.
   - Una línea explícita: "mezcla y dosis: fuera de alcance (CR-01), el
     cliente formula y aplica su propio control de calidad" — no un campo
     vacío ni un placeholder.
5. **Permisos**: una acción `sec_action` nueva ("ver reporte técnico"),
   asignada según la fila de la espec línea 89 a los roles que YA existen en
   el panel interno (jefe de campo, encargado, dueño) — el rol agrónomo/
   cliente queda fuera hasta que exista el portal (ver "Qué es de este repo y
   qué no"), documentalo.
6. **Panel**: enlace/botón mínimo junto al de la acta en
   `trabajos/show.blade.php` para descargar el reporte una vez generado. No
   hace falta pantalla nueva.
7. **`composer openapi`** al final; confirmá que el diff no borra nada ya
   commiteado.

### Tests de integración (`tests/Feature/Api/ReporteTecnicoTest.php`, nuevo)

1. Firmar el acta de un trabajo → genera automáticamente la fila
   `ope_reportes_tecnicos` y el PDF, en la misma operación.
2. Reintento de firma sobre una acta ya `firmada` (idempotente, tarea 24) →
   NO regenera el reporte ni duplica la fila.
3. `GET /api/reportes/lote/{id}` sobre un trabajo sin acta firmada →
   rechazado/404.
4. `GET /api/reportes/lote/{id}` sobre un trabajo conformado → devuelve el
   reporte, con horas de inicio/fin correctas (contra `MIN`/`MAX` real de
   las sesiones).
5. Trabajo con cobertura `Parcial` (sesión cerrada por motivo distinto de
   `completado`, lote no cubierto del todo) → el reporte incluye superficie
   no aplicada y el motivo correcto.
6. Trabajo con relevo de piloto (2+ sesiones vigentes) → el reporte detalla
   cada sesión por separado (piloto, dron, hectáreas).
7. El contenido del PDF/reporte NO incluye ningún campo de mezcla, dosis,
   receta o producto (assert de ausencia, no solo de presencia de lo demás).
8. Test de idempotencia contra Postgres real si la sesión corre contra el
   compose (mismo criterio que las tareas 22/23), o documentado como
   pendiente si solo corrió contra SQLite.

## Cómo repartir las etapas

1. Migración `ope_reportes_tecnicos` + enganche de generación al firmar el
   acta (sin PDF real todavía, solo la fila + los datos armados).
2. Generación del PDF (dompdf) con el contenido completo del punto 4 +
   endpoint `GET /api/reportes/lote/{id}`.
3. Permisos `sec_action` + botón mínimo en `trabajos/show.blade.php`.
4. Los ocho casos de test de arriba + `composer openapi` + pulido.

## Qué NO hacer

- No implementes nada de dosis, receta, producto ni "mezcla ejecutada" — CR-01
  los saca de alcance, y no hay datos de origen para calcularlos.
- No implementes el portal del cliente ni ningún endpoint bajo
  `/api/portal/*` — eso es Sprint 12, tarea aparte, con su propio scoping por
  contrato (invariante 5 de `CLAUDE.md`, que hoy no aplica porque el portal
  no existe).
- No reabras el diagrama de 8 estados de la espec §5 ni le agregues columnas
  a `Trabajo` — todo el contenido del reporte se arma leyendo relaciones que
  ya existen, sin mutar nada del lado de `Trabajo`/`Sesion`.
- No regeneres el PDF en cada `GET` — se genera una sola vez, al firmar el
  acta; el endpoint de lectura solo sirve lo ya persistido.
- No agregues una segunda librería de PDF — reusá la que trajo la tarea 24.

## Criterio de aceptación

`./bin/verify` = 0, con los ocho tests de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/25.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/25.md` con qué se hizo,
cómo enganchaste la generación automática (evento de dominio de la tarea 24
o extensión directa del caso de uso, con tu porqué), y qué falta. Al llegar
a `OK`, `runs/25.pr.md`.

## Commits

Agrupados: migración + enganche de generación; PDF + endpoint de lectura;
permisos + panel; tests. Español, imperativo, el porqué antes que el qué.
Sin trailer `Co-Authored-By`.
