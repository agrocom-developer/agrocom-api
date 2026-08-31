<!-- ciclo: critica=no turno-noche=0 -->
# Tarea 05 — Rescatar el dashboard: integrar `feature/dashboard-agro`

Sesión nueva y aislada. Esto es una **integración**, no una fase de diseño.

Corre con los tests descongelados (`turno-noche=0`) porque un merge puede traer
conflictos dentro de `tests/`. Eso **no** te habilita a debilitar un test para
que pase: resolver un conflicto es conservar las dos intenciones, no borrar la
que molesta.

Cargá los skills `verificacion`, `flujo-git-pr` y `panel-design-ui`.

## Qué pasó

La rama `feature/dashboard-agro` tiene 11 commits con los tabs Mapa, Resumen
por lote y Multimedia. Se abrió el PR #22 **en borrador** y se cerró sin
mergear el 31/8/2026, sin comentarios: el trabajo quedó fuera de `develop`.
`runs/01.md` tiene el detalle de esa integración previa.

Desde entonces `develop` avanzó (HU-03, el ciclo, la limpieza de ramas), así
que la rama envejeció más. Cuanto más espere, más caro sale.

## Qué hacer

1. Traé `develop` a `feature/dashboard-agro` con **merge**, no rebase: la rama
   está publicada.
2. Resolvé los conflictos que aparezcan **conservando lo de `develop`** en todo
   lo que no sea del dashboard. El dashboard no toca `app/Dominios/Seguridad/`,
   ni rutas de API, ni migraciones: si un conflicto aparece ahí, es que estás
   revirtiendo trabajo ajeno — pará y marcá `BLOQUEADA`.
3. Corré `./bin/verify`. Si falla, arreglá **solo** lo que rompió el merge.
4. `runs/01.md` deja anotado que el plan quedó desincronizado: la Fase 6 figura
   como cerrada ✅ describiendo tabs ("Sesiones", "Pausas", tarjetas KPI) que la
   rama quitó a propósito. **Resolvelo así**: en
   `docs/gestion/plan_dashboard_rediseno.md` no reescribas la Fase 6 —
   documentá la novena vuelta como una fase posterior que la reemplaza,
   diciendo qué quitó y por qué (el contenido esencial ya vivía en Resumen y
   `stat-card` sigue en el catálogo). Conservar la historia de lo decidido vale
   más que dejar el documento prolijo.
5. Abrí el PR con `runs/05.pr.md` (título y cuerpo), **no en borrador**: el
   PR #22 quedó en draft y por eso el auto-merge nunca lo tocó. Fue lo que hizo
   que este trabajo se perdiera.

## Qué NO hacer

- No agregues funcionalidad al dashboard ni reabras decisiones de diseño ya
  tomadas. Si algo visual te parece que falta, anotalo en el cuerpo del PR.
- No revivas los tabs "Sesiones" y "Pausas" ni las tarjetas KPI: quitarlos fue
  una decisión tomada, no un descuido.
- No mergees a mano.

## Criterio de aceptación

`./bin/verify` devuelve 0 y el PR queda abierto y **fuera de borrador**.

## Máximo de intentos

3. Si el merge trae conflictos que no podés resolver sin decidir por el
usuario, `BLOQUEADA` con la lista exacta de archivos en conflicto es la
respuesta correcta.

## Advertencia sobre lo visual

La cascada **no** cubre regresión visual, y esto es una pantalla. El PR tiene
que decir explícitamente que falta la pasada manual en claro y oscuro. La tarea
06 de la cola es justamente construir ese gate.

## Cierre obligatorio

- `runs/05.estado`: `OK` o `BLOQUEADA`.
- `runs/05.md`: qué conflictos hubo y cómo se resolvieron, el número de PR, y
  qué quedó afuera.
- `runs/05.pr.md`: título en la primera línea, cuerpo debajo.
