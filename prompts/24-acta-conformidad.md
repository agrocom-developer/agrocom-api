<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/acta-conformidad etapas=5 -->

# Tarea 24 — HU-17: acta por lote con firma del agrónomo

`plan_sprints.md`, Sprint 5, fila HU-17: "Como piloto/jefe, quiero generar el
acta por lote apenas conformado y capturar la firma del agrónomo en
pantalla, para cobrar sin pelea". CA: "Acta PDF con hectáreas conformadas;
firma en pantalla o foto del acta física como evidencia".

Escrita asumiendo que la tarea 23 (HU-13, recargas) ya está integrada. No hay
dependencia real de código entre ambas — si no llegó a integrarse, esta
tarea no queda bloqueada por eso.

**Es crítica, por dos motivos**: define una máquina de estados nueva
(`ope_actas`, pendiente → firmada — invariante 7 y la lista de "qué no
delegar" de `CLAUDE.md`, "el servicio de estados") y agrega un tipo de
recurso nuevo consumido por el panel. El PR se abre en borrador.

## Qué es de este repo y qué no

"Firma en pantalla" es una UI de `agrocom-field` (Flutter, piloto en el RC) —
fuera de este ciclo, regla permanente de `cola_tareas.md`. Lo que SÍ es de
este repo: el endpoint que genera el PDF del acta, y el que registra la
firma — sea cual sea su origen (captura en pantalla convertida a imagen, o
foto del acta física), las dos llegan de la MISMA forma: como una evidencia
ya subida vía `POST /api/evidencias` (tarea 19), tipo `firma_acta` (el caso
del enum `TipoEvidencia` ya existe desde esa tarea, sin usar todavía). Esta
tarea NO implementa ninguna pantalla de captura de firma — solo recibe la
referencia a la evidencia ya subida, mismo patrón que
`CierreTrabajo::$evidenciaImagenCampoUuidCliente` (tarea 21).

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend` y `seguridad-roles`
(hacen falta permisos `sec_action` nuevos para los dos endpoints). Leé antes
de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 139 (fila
  `actas` de §4.3), línea 200 ("→ conformado: Acta firmada por el agrónomo,
  sobre el lote completo" — es el diagrama de 8 estados ORIGINAL, no lo
  reabras, ver "Qué NO hacer"), líneas 314-315 (endpoints de referencia,
  igual de informativos que el resto de la lista §8: este repo los
  reinterpreta según su propio motor, no los copia literal).
- `app/Dominios/Operaciones/Dominio/EstadoTrabajo.php` — el trabajo sigue
  con dos estados (`abierto`/`cerrado`) desde la tarea 09; "conformado" NO es
  un estado de `Trabajo`, es un estado del ACTA (tabla nueva de esta tarea).
- `database/migrations/2026_09_01_100001_create_ope_trabajos_table.php`,
  el comentario sobre por qué `hectareas_validadas` quedó fuera del esquema
  — no existe esa columna. `hectareas_conformadas` de esta tarea toma
  `trabajo.hectareas_declaradas` como snapshot AL MOMENTO DE GENERAR el
  acta (no recalculado después): una vez firmada, el número no debe moverse
  aunque después se corrija una sesión — documentá esta decisión en
  `runs/24.md` con ese porqué.
- `app/Dominios/Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php`,
  el método `cerrarTrabajo()` — patrón a copiar para: (a) referenciar una
  evidencia por `uuid_cliente` y validar tipo/existencia, (b) el guardarraíl
  "INSERT antes que I/O" de `RegistrarEvidencia` si generás el PDF a disco.
- `app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php`
  y `Dominio/MaquinaEstados/TransicionesTrabajo.php` — patrón exacto a
  replicar para `MaquinaEstadosActa`: tabla de transiciones permitidas +
  guardas, invariante 7, ninguna asignación de estado suelta fuera de esa
  clase.

### Diseño

1. **Migración `ope_actas`**: `trabajo_id` FK real (`restrictOnDelete`, único
   — un trabajo tiene a lo sumo un acta, `UNIQUE` real no solo parcial de
   `uuid_cliente`), `hectareas_conformadas` decimal (snapshot, ver arriba),
   `firmante` string (nombre del agrónomo — texto libre, no existe tabla de
   contactos del cliente en este esquema, documentalo igual que
   `bateria_saliente_id` de la tarea 23), `fecha_firma` nullable,
   `evidencia_firma_id` FK a `ope_evidencias` nullable (se completa recién al
   firmar, mismo patrón que `imagen_campo_evidencia_id`), `observaciones`
   nullable, `estado` (`pendiente`/`firmada`), auditoría + soft delete,
   índice único parcial sobre `uuid_cliente` y sobre `evidencia_firma_id`
   (mismo criterio preventivo que la tarea 21/22: una firma no debería
   respaldar dos actas).
2. **`Dominio/MaquinaEstados/TransicionesActa.php` + `Aplicacion/MaquinaEstados/MaquinaEstadosActa.php`**:
   única transición permitida `pendiente → firmada`. Nada de
   `estado = ...` suelto en el caso de uso.
3. **Guarda de generación**: el trabajo debe estar `EstadoTrabajo::Cerrado`
   para poder generar su acta — sin eso, rechazado. Evaluá además si exigir
   que TODAS las sesiones vigentes del trabajo ya estén validadas
   (`ValidarSesion`/`SesionValidada`, tarea 14) antes de permitir generarla:
   el propósito del acta es cobrar sobre datos ya validados, no sobre lo
   declarado en crudo por el piloto. Es una decisión real, no la des por
   sentada — documentala en `runs/24.md` con tu porqué, sea cual sea.
4. **`POST /api/trabajos/{id}/acta`**: genera la fila `ope_actas` (estado
   `pendiente`) si no existe una para ese trabajo, y devuelve el PDF (o su
   URL de descarga). Idempotente: un segundo llamado sobre un trabajo que ya
   tiene acta devuelve la existente, no crea una segunda ni regenera el PDF.
   Agregá una librería de PDF a `composer.json` (dompdf es la opción
   estándar del ecosistema Laravel, sin binarios externos — usala salvo que
   encuentres una razón real para otra). Contenido del PDF: SOLO lo mínimo
   de un acta de conformidad — lote/trabajo, hectáreas conformadas, fecha.
   Nada del reporte técnico completo (mezcla, condiciones, litros/ha) — eso
   es HU-18, tarea aparte.
5. **`POST /api/actas/{id}/firmar`**: recibe `evidencia_firma_uuid_cliente`
   (debe existir y ser tipo `firma_acta`, si no → rechazado, mismo patrón que
   `cerrarTrabajo()`), `firmante`, `fecha_firma`. Transiciona `pendiente →
   firmada` vía `MaquinaEstadosActa`. Reintento sobre una acta ya `firmada`
   con el mismo evento → idempotente (no re-firma, no error). Firmar una
   acta que no existe → rechazado/404.
6. **Permisos**: dos acciones `sec_action` nuevas (generar acta / firmar
   acta), seedeadas y asignadas a los roles que corresponda según la tabla de
   roles de la espec (línea 83-84: piloto y jefe de campo generan; solo el
   rol que representa al agrónomo/cliente firma — si ese rol no existe
   todavía en `sec_*`, documentá la decisión de a quién se lo asignás
   provisoriamente).
7. **Panel**: un botón/enlace mínimo en
   `app/Dominios/Operaciones/Infraestructura/Http/Views/pages/trabajos/show.blade.php`
   (ya existe esa pantalla desde la tarea 15) para ver el estado del acta y
   descargar el PDF. No hace falta una pantalla nueva completa.
8. **`composer openapi`** al final; confirmá que el diff no borra nada ya
   commiteado (repetí el chequeo que hizo la tarea 21).

### Tests de integración (`tests/Feature/Api/ActaConformidadTest.php`, nuevo)

1. Generar acta sobre trabajo `abierto` → rechazado.
2. Generar acta sobre trabajo `cerrado` → creada, `hectareas_conformadas`
   igual a `trabajo.hectareas_declaradas` en ese momento.
3. Generar acta dos veces sobre el mismo trabajo → la segunda devuelve la
   MISMA fila, no crea una segunda (`ope_actas` cuenta 1).
4. Firmar con evidencia inexistente → rechazado.
5. Firmar con evidencia de tipo distinto de `firma_acta` → rechazado.
6. Firmar válido → estado `firmada`, `fecha_firma`/`firmante`/
   `evidencia_firma_id` persistidos correctos.
7. Reintento de firma sobre una acta ya `firmada` → idempotente, no
   re-escribe los campos.
8. Firmar un acta inexistente → rechazado/404.
9. Test de `MaquinaEstadosActa`: `pendiente → firmada` permitida; cualquier
   otra transición, rechazada (mismo patrón que
   `tests/Unit/TransicionesEstadoTest.php`).

## Cómo repartir las etapas

1. Migración `ope_actas` + `TransicionesActa`/`MaquinaEstadosActa` + DTOs.
2. Endpoint generar acta + PDF (nueva dependencia) + endpoint firmar.
3. Permisos `sec_action` + botón mínimo en `trabajos/show.blade.php`.
4. Los nueve casos de test de arriba.
5. `composer openapi`, pulido, cascada verde.

## Qué NO hacer

- No relances el diagrama de 8 estados de la espec §4.3/§5 completo — el
  trabajo sigue con `EstadoTrabajo` de dos valores; "conformado" es un
  estado de `ope_actas`, no de `Trabajo` (mismo criterio ya fijado en las
  tareas 20/21).
- No implementes HU-18 (reporte técnico completo) en esta tarea — comparten
  evidencia y estructura, pero son historias distintas. El PDF de acá es
  SOLO el acta de conformidad.
- No agregues `hectareas_validadas` como columna nueva de `ope_trabajos` —
  usá el snapshot de `hectareas_declaradas` al generar el acta, documentando
  la limitación.
- No captures la firma como blob binario en `ope_actas` — reusá el
  mecanismo de evidencias ya existente (`POST /api/evidencias`, tipo
  `firma_acta`).
- No implementes nada de Flutter/captura de firma en pantalla real — es
  `agrocom-field`, fuera de este ciclo.
- No generes el PDF sin persistir primero la fila `ope_actas` en la misma
  transacción/orden que `RegistrarEvidencia` (INSERT antes que I/O de
  archivo).

## Criterio de aceptación

`./bin/verify` = 0, con los tests de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/24.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/24.md` con qué se hizo,
tu decisión documentada sobre exigir o no sesiones validadas antes de
generar el acta, y qué falta. Al llegar a `OK`, `runs/24.pr.md`.

## Commits

Agrupados: migración + máquina de estados + DTOs; endpoints (generar/PDF +
firmar); permisos + panel; tests. Español, imperativo, el porqué antes que
el qué. Sin trailer `Co-Authored-By`.
