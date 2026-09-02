<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/incidencias-sesion etapas=3 -->

# Tarea 22 — HU-08: incidencias con foto, ligadas a la sesión

`plan_sprints.md`, Sprint 3, fila HU-08: "Como piloto, quiero registrar
incidencias con foto (caldo/ESC/batería/mecánica/clima) para respaldar el
reporte". CA: "Incidencia offline con evidencia comprimida, ligada a la
sesión".

Escrita asumiendo que la tarea 19 (TE-07, recepción de evidencias) está
integrada — lo está, `develop` la tiene desde el PR #56. Si por lo que sea no
lo está, esta tarea queda `BLOQUEADA`.

**Es crítica**: agrega un tipo de registro nuevo al motor de sync
(`Contratos/EscrituraSincronizacion.php`,
`Sincronizacion/Aplicacion/SincronizarLote.php::ORDEN_CAUSAL`,
`EscrituraSincronizacionEloquent`). El PR se abre en borrador.

## Qué hacer

Cargá el skill `verificacion` y `dominio-backend`. Leé antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 137 (fila
  `incidencias` de §4.3: "id, sesion_id, tipo (enum: caldo / esc / bateria /
  mecanica / clima / otro), descripcion, hora, evidencia_id").
- `app/Dominios/Operaciones/Dominio/TipoEvidencia.php` — ya existe el caso
  `FotoIncidencia = 'foto_incidencia'` (tarea 19, anticipando exactamente
  esta HU). No lo toques, solo referencialo.
- `app/Dominios/Operaciones/Contratos/RegistroCondiciones.php` +
  `EscrituraSincronizacionEloquent::registrarCondiciones()` — es el patrón más
  cercano a copiar: un registro nuevo (no una mutación de fila existente) que
  referencia una `sesion` por `uuid_cliente`, sin `$operarioPersonaId` (la
  espec no define dueño individual de este registro — piloto, auxiliar y
  jefe de campo pueden registrar incidencias por igual, misma tabla de roles
  de §2 que ya vale para condiciones).
- `app/Dominios/Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php`,
  el método `cerrarTrabajo()` (líneas ~277-310) — es el patrón a copiar para
  la validación de una evidencia referenciada por `uuid_cliente`: existe,
  es del tipo correcto, y no fue usada ya por otro registro (ahí fue un
  hallazgo de revisión crítica sobre la marcha; acá aplicalo desde el
  principio, no esperes a que te lo señalen).

1. **Enum `Dominio/TipoIncidencia.php`**: `caldo`, `esc`, `bateria`,
   `mecanica`, `clima`, `otro`. Mismo patrón que `TipoEvidencia`.
2. **DTO `Contratos/RegistroIncidencia.php`**: `uuid_cliente`,
   `sesion_uuid_cliente`, `tipo` (del enum de arriba), `descripcion`
   (opcional), `hora`, `evidencia_foto_uuid_cliente` (obligatorio —
   referencia por `uuid_cliente` una evidencia ya subida vía
   `POST /api/evidencias` con `tipo: foto_incidencia`; mismo criterio que
   `CierreTrabajo::$evidenciaImagenCampoUuidCliente`, tarea 21: "con foto"
   en el título de la HU y el propósito "respaldar el reporte" son motivo
   suficiente para exigirla siempre, no dejarla opcional — si encontrás una
   razón real para que algún tipo de incidencia no pueda traer foto,
   documentalo como decisión propia en vez de asumirlo).
3. **Migración `ope_incidencias`**: `sesion_id` FK real a `ope_sesiones`
   (`restrictOnDelete`, mismo criterio que `ope_condiciones.sesion_id`),
   `tipo` con `CHECK` en Postgres (mismo patrón que el `momento` de
   `ope_condiciones`), `descripcion` nullable, `hora`, `evidencia_foto_id` FK
   a `ope_evidencias` (NOT NULL dado el punto 2), auditoría + soft delete,
   índice único parcial sobre `uuid_cliente` (idempotencia real, invariante
   1), e índice único parcial sobre `evidencia_foto_id` (mismo criterio que
   `ope_trabajos_imagen_campo_evidencia_id_unico` de la tarea 21: sin él, la
   misma foto podría "respaldar" dos incidencias distintas).
4. **`EscrituraSincronizacion::registrarIncidencia()`** + su implementación
   Eloquent: resuelve la sesión por `uuid_cliente` (rechaza si no existe),
   valida la evidencia (existe, tipo `foto_incidencia`, no usada por otra
   incidencia — rechaza con mensaje claro en cada caso), crea la fila dentro
   de una transacción, `duplicado` vía la violación del `UNIQUE` de
   `uuid_cliente` (nunca un `SELECT` previo).
5. **`SincronizarLote`**: agregá `'incidencia'` a `ORDEN_CAUSAL`, justo
   después de `'condiciones'` (depende de `sesion`, no de `condiciones` ni al
   revés — el orden entre ambos no importa, solo que las dos vayan después
   de `sesion`). Agregá `aplicarIncidencia()` siguiendo el patrón de
   `aplicarCondiciones()`.
6. **`composer openapi`** al final, y confirmá que el diff del yaml generado
   no borra nada que ya estuviera commiteado (mismo chequeo que hizo la
   tarea 21).

### Tests de integración (`tests/Feature/Api/IncidenciaSincronizacionTest.php`, nuevo)

1. Sin `evidencia_foto_uuid_cliente` → `rechazado`.
2. Con evidencia existente pero de tipo distinto (p. ej. `imagen_campo`) →
   `rechazado`.
3. Con evidencia inexistente → `rechazado`.
4. Con `sesion_uuid_cliente` que no existe → `rechazado`.
5. Incidencia válida → `aplicado`, fila persistida con
   `evidencia_foto_id`/`sesion_id` correctos (comparar contra el id real, no
   solo el JSON de respuesta).
6. Reintento idempotente del mismo `uuid_cliente` → `duplicado`, sin
   duplicar la fila (`count()->toBe(1)`).
7. Misma evidencia referenciada por dos incidencias distintas → la segunda
   `rechazado`, la primera sigue intacta.

### Test unitario del DTO

`RegistroIncidencia::intentarDesdeArreglo()` devuelve `null` ante datos
incompletos o mal tipados — agregalo a
`tests/Feature/EscrituraSincronizacionTest.php` (congelado, `descongela=tests`
ya declarado arriba te habilita a tocarlo).

## Cómo repartir las etapas

1. Enum + DTO + migración + `registrarIncidencia()` + `ORDEN_CAUSAL`.
2. Los siete casos de integración + el test unitario del DTO.
3. `composer openapi`, pulido, cascada verde.

## Qué NO hacer

- No reabras `ope_condiciones.momento` para admitir `'incidencia'` como
  valor nuevo. El comentario de esa migración (tarea 17) menciona
  "`incidencia` es HU-08" como alcance futuro, pero es OTRO concepto:
  condiciones climáticas capturadas EN el momento de una incidencia, no el
  registro de la incidencia en sí (que es lo que agrega esta tarea, en su
  propia tabla `ope_incidencias`). El CA literal de esta HU en
  `plan_sprints.md` no pide registrar condiciones junto con la incidencia —
  si encontrás una razón real para hacerlo, es una decisión aparte,
  documentada con su porqué, no algo que se dé por sentado acá.
- No toques `cerrarTrabajo()` ni `cerrarSesion()` — esta HU es un registro
  nuevo e independiente, no una extensión de un cierre existente.
- No crees un endpoint REST aparte (`POST /api/sesiones/{id}/incidencias`,
  como lo describe la espec §8 original). Desde la tarea 09 este repo
  consolidó todo el push offline en `POST /api/sync`
  (`SincronizarLote::ORDEN_CAUSAL`) — seguí ese patrón, no el de la espec
  original.
- No le agregues `$operarioPersonaId`/verificación de pertenencia a
  `registrarIncidencia()` — la espec no define un dueño individual de este
  registro (mismo criterio que `condiciones`/`recepcion_caldo`).

## Criterio de aceptación

`./bin/verify` = 0, con los tests de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/22.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/22.md` con qué se hizo y
qué falta. Al llegar a `OK`, `runs/22.pr.md`.

## Commits

Agrupados: enum + DTO + migración + motor de sync; tests. Español,
imperativo, el porqué antes que el qué. Sin trailer `Co-Authored-By`.
