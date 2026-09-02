<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/recepcion-evidencias etapas=4 -->

# Tarea 19 — TE-07 (parte servidor): recepción de evidencias

`plan_sprints.md` describe TE-07 como "cola de evidencias: compresión <300 KB,
hash SHA-256, subida en segundo plano con reintentos". La compresión y la
cola de reintentos son de la app Flutter (`agrocom-field`, otro repo, fuera
de este ciclo — ver la fila de TE-07 en "Fuera del ciclo automático" de
`cola_tareas.md`). Lo que sí es de este repo, y todavía no existe, es **el
endpoint que recibe esa evidencia y la deja persistida e idempotente**.

Esta tarea no estaba en `cola_tareas.md` — la agrega esta planificación
porque HU-08 (incidencias) y HU-09 (cierre de lote) la necesitan como
requisito previo: ambas referencian evidencia (`evidencia_id` / `captura_rc_id`
/ `imagen_campo`) y ninguna puede escribirse en serio sin que exista antes.
Por eso va primero.

**Es crítica**: nueva infraestructura de almacenamiento de archivos, con la
que otros tipos de registro del motor de sync van a referenciarse desde acá
en adelante — mismo criterio que las tareas 13/17/18. El PR se abre en
borrador.

## Qué hacer

Cargá el skill `verificacion`, y `modelo-datos` para la migración. Leé antes
de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 50 (evidencias
  comprimidas <300 KB, eso es del cliente), líneas 52-65 (§2.1, protocolo de
  sync completo — el punto 7 es el que importa acá: "evidencias en cola
  separada: los registros livianos sincronizan primero; las imágenes
  comprimidas van en una segunda cola referenciada por UUID"), y la fila
  `evidencias` de la sección 4 (`id, tipo (captura_rc / imagen_campo /
  foto_incidencia / comprobante / firma_acta), archivo_url, hash, subido_por,
  fecha, uuid_cliente`).
- `config/filesystems.php` — el disco `r2` ya está configurado (`.env.example`),
  no hace falta agregar nada de infraestructura de storage.
- `app/Dominios/Operaciones/Contratos/CierreTrabajo.php` y `CierreSesion.php`
  — el patrón de DTO (`intentarDesdeArreglo()`, `null` ante dato inválido,
  nunca una excepción) que replicás para el DTO de evidencia, aunque esta vez
  no viaja dentro del lote de `POST /api/sync` (ver más abajo el porqué).
- `docs/decisiones/0011-convencion-prefijos-tabla.md` punto 3 y la extensión
  del 26/8/2026 ("`bases` se descartó como tabla transversal de `Compartido/`
  ... es un concepto de negocio, no un mecanismo transversal") — mismo
  argumento aplica acá: `evidencias` es un concepto de negocio (documenta un
  hecho: qué se subió, quién, cuándo), no una pieza de plataforma técnica.
  No va en `Compartido/`.

**Decisión de módulo: recomendado `Operaciones` (`ope_evidencias`), a
confirmar por vos.** Mismo argumento que la tarea 18 usó para `recepcion_caldo`:
sin lógica propia, sin ciclo de vida, sin máquina de estados — se cuelga del
módulo dueño del primer consumidor real. Hoy los tres usos conocidos
(`captura_rc`, `imagen_campo`, `foto_incidencia`) son todos de `Operaciones`.
Cuando `Finanzas` necesite `comprobante` o algo necesite `firma_acta`, la
referencian por ID plano (ADR 0003 regla 3) — mismo patrón que `base_id`.
Si encontrás una razón real para no hacerlo así, documentala en `runs/19.md`
como hizo la tarea 18 con su propia decisión de módulo.

**Decisión de endpoint: NO uses `POST /api/sync`.** El "sobre" de ese
endpoint (`RegistroSync`, ver `SyncController.php`) es JSON puro, sin
binarios — meter un archivo ahí exige base64 dentro de un lote que ya reenvía
en cada reintento, exactamente lo que el punto 7 de arriba dice que el
protocolo evita a propósito con su "cola separada". Un endpoint dedicado
(`POST /api/evidencias`, `multipart/form-data`) es la lectura directa de ese
punto 7. Si en la implementación encontrás una razón de peso para hacerlo
distinto, documentala — pero arrancá por acá.

1. **Migración + modelo.** `ope_evidencias`: `uuid_cliente` (`UNIQUE`,
   invariante 1), `tipo` (enum de la espec, `CHECK` en Postgres), `archivo_url`,
   `hash` (SHA-256 del contenido, calculado por el servidor — no confíes en
   un hash que mande el cliente sin verificarlo), `subido_por` (nullable,
   igual criterio que `Condiciones`/`RecepcionCaldo`: la espec no fija un
   dueño obligatorio), `fecha`, soft delete + auditoría (ADR 0007).
2. **Endpoint.** Recibe el archivo, lo guarda en el disco `r2`, calcula el
   hash del contenido recibido, y responde `aplicado` / `duplicado` /
   `rechazado` — mismo vocabulario que `ResultadoSync`, aunque este endpoint
   vive aparte. `uuid_cliente` repetido con el mismo contenido → `duplicado`,
   nunca sobrescribe el archivo ya guardado (mismo mecanismo de idempotencia
   por índice único que el resto del motor de sync, `ON CONFLICT`, no un
   `SELECT` previo).
3. **Validaciones de borde**: `tipo` fuera del catálogo → rechazado; archivo
   ausente o vacío → rechazado. No repliques la validación de tamaño
   `<300 KB` del lado servidor de forma estricta — esa es una guía para el
   cliente al comprimir, no una regla de negocio que el servidor deba
   exigir; si querés un límite superior de seguridad (para no aceptar
   archivos absurdos), documentalo como decisión tuya, no como el criterio
   de aceptación 4.
4. **Tests de integración** con `Storage::fake()`: subida nueva persiste
   archivo + fila; reintento del mismo `uuid_cliente` no duplica ni
   reescribe el archivo; tipo inválido rechaza; hash persistido coincide con
   el contenido subido.

## Cómo repartir las etapas

1. Decisión de módulo (documentada) + migración + modelo.
2. Endpoint (ruta, form request, caso de uso, guardado en disco `r2`, cálculo
   de hash, idempotencia).
3. Tests de integración con `Storage::fake()`.
4. Pulido y cascada verde.

## Qué NO hacer

- Sin incidencias (`ope_incidencias`, HU-08) ni el "sin captura no cierra"
  de `CierreTrabajo` (HU-09) — ninguna de las dos consume todavía esta
  evidencia; eso es tarea aparte. Esta tarea deja el mecanismo listo para
  que HU-08/HU-09 lo referencien por `uuid_cliente`, nada más.
- Sin compresión de imagen, sin cola de reintentos, sin nada de
  `agrocom-field`/Flutter — eso es TE-07 del lado app, fuera de este ciclo.
- No toques `SincronizarLote::ORDEN_CAUSAL` a menos que termines
  decidiendo meter esto en el lote (ver arriba); si lo hacés, documentá por
  qué te alejaste de la recomendación.
- No inventes un mecanismo de firma/autorización nuevo para subir
  evidencia — el token Sanctum del dispositivo (`IdentidadOperarioToken`,
  ya usado en `SyncController`) alcanza, mismo guard que el resto de la API
  de campo.

## Criterio de aceptación

`./bin/verify` = 0, con los tests del punto 4 de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/19.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/19.md` con la decisión
de módulo y de endpoint, ambas con su porqué, y qué falta (HU-08/HU-09 quedan
para tareas aparte — decilo explícito, son las tareas 20 y 21 aunque en el
orden de la cola la 20 sea HU-07, no HU-08: la cola prioriza HU-07 primero
por su propio peso, ver `runs/18-plan.md`). Al llegar a `OK`, `runs/19.pr.md`.

## Commits

Agrupados: decisión de esquema + migración + modelo, endpoint + storage +
hash + idempotencia, tests de integración. Español, imperativo, el porqué
antes que el qué. Sin trailer `Co-Authored-By`.
