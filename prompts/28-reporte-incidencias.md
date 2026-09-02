<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/reporte-incidencias etapas=2 -->

# Tarea 28 — cerrar el hueco de incidencias en el reporte técnico

## Por qué esta tarea

No es una HU nueva de `plan_sprints.md`: es un hueco que HU-18 (tarea 25) dejó
documentado a propósito. `ArmarContenidoReporteTecnico::ejecutar()`
(`app/Dominios/Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php:87-90`)
devuelve `'incidencias' => []` siempre, con este comentario:

```
// HU-08 (tarea 22, incidencias con evidencia): complemento de la
// espec §9, todavía sin datos — su PR (#59) no está integrado a
// `develop` (ver runs/25.md). Lista vacía, no una que finja datos.
```

Ese PR #59 ya se mergeó a `develop` (tarea 27, reconciliación, 2/9/2026):
`Incidencia` (`ope_incidencias`) existe, con `sesion_id`, `tipo`
(`TipoIncidencia`), `descripcion`, `hora`, `evidencia_foto_id` (FK a
`ope_evidencias`). El hueco que bloqueaba esto ya no existe. Cerrarlo.

## Qué hacer

Cargar skill `verificacion` antes de tocar nada.

1. **Relación `Sesion::incidencias(): HasMany`** en
   `app/Dominios/Operaciones/Infraestructura/Eloquent/Sesion.php` — `Incidencia`
   referencia `sesion_id`, no `trabajo_id` (a diferencia de `Condiciones`, que
   sí tiene `trabajo_id` denormalizado). Mismo patrón que
   `Trabajo::condiciones(): HasMany` (`Trabajo.php:131`).
2. **Relación `Incidencia::evidenciaFoto(): BelongsTo`** en
   `app/Dominios/Operaciones/Infraestructura/Eloquent/Incidencia.php`, a
   `Evidencia` vía `evidencia_foto_id` — mismo patrón que
   `Trabajo::imagenCampoEvidencia(): BelongsTo` (`Trabajo.php:105-108`).
3. **`ArmarContenidoReporteTecnico::ejecutar()`**: agregá `sesiones.incidencias.evidenciaFoto`
   al `loadMissing()` de la línea 59, y reemplazá el `'incidencias' => []` fijo
   por el recorrido real. Las incidencias cuelgan de la SESIÓN, no del trabajo
   directo — recolectalas de `$sesionesVigentes` (la misma colección que ya usa
   `hora_inicio`/`hora_fin`), no de `$trabajo->incidencias` (no existe esa
   relación y no hace falta crearla). Cada elemento sigue el shape que el
   docblock ya declara: `['tipo' => $incidencia->tipo->value, 'evidencia_url' =>
   $incidencia->evidenciaFoto?->archivo_url]`.
4. Actualizá el comentario de la línea 87-89: ya no está "sin integrar", así que
   el comentario viejo mentiría si queda. Podés borrarlo o dejar uno corto que
   explique de dónde sale la lista (de las sesiones vigentes, no del trabajo).
5. **Vista del PDF** (`Infraestructura/Http/Views/pdf/` — buscá la vista de
   reporte técnico): si hay un texto tipo "Sin datos disponibles todavía
   (módulo de incidencias en integración)", cambialo para listar las
   incidencias reales cuando las hay, y un texto neutral ("Sin incidencias
   registradas") cuando la lista está vacía — no repitas la frase de "en
   integración", que ya no es cierta.

## Cómo repartir las etapas

- **Etapa 1**: las relaciones Eloquent, el cambio en `ArmarContenidoReporteTecnico`,
  y los tests de contenido (no hace falta tocar el PDF todavía).
- **Etapa 2**: la vista PDF y el test end-to-end si falta, cierre.

Es chica — es razonable que cierre entera en la etapa 1.

## Qué NO hacer

- No toques `EscrituraSincronizacionEloquent::registrarIncidencia()` ni nada
  del motor de sync — HU-08 ya está implementada y verificada (`runs/22.veredicto`),
  esta tarea solo LEE lo que ya persiste.
- No toques `FirmarActa` ni `MaquinaEstadosActa` — el enganche de generación
  automática del reporte ya existe (tarea 25), no se toca.
- No reabras CR-01 (mezcla/dosis) — nada de esto tiene relación.
- No es la tarea para el bug de timezone en `Sesion.inicio`/`fin` (documentado
  en `runs/24.md` y `runs/25.md`) — tiene su propia tarea (29) en la cola.

## Criterio de aceptación

`./bin/verify` = 0, con:

- Un test en `tests/Feature/Api/ReporteTecnicoTest.php` que crea una sesión con
  una incidencia real (con su evidencia `foto_incidencia`, mismo patrón que
  `tests/Feature/Api/IncidenciaSincronizacionTest.php:103-105` para el fixture
  de `Evidencia`) y verifica que `ArmarContenidoReporteTecnico::ejecutar()` la
  incluya — `tipo` y `evidencia_url` correctos.
- Un caso (puede ser el mismo test u otro) que confirma que sin incidencias la
  lista sigue vacía — no debería requerir código nuevo, pero dejalo explícito
  para que no se rompa en silencio si alguien toca esto después.

## Cierre de la etapa

`runs/28.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/28.md` con
qué se hizo y qué falta. Al llegar a `OK`, `runs/28.pr.md` con título en la
primera línea y cuerpo debajo.

## Commits

Agrupados por función (relaciones Eloquent + contenido del reporte en un
commit; vista PDF en otro si aplica; tests pueden ir con el código que
prueban o aparte, tu criterio). Español, imperativo, explicando el porqué. Sin
`Co-Authored-By`.
