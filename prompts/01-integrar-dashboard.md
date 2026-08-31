# Tarea 01 — Integrar el dashboard (verificar, no seguir diseñando)

Sesión nueva y aislada. Todo lo que necesitás saber está acá o en el repo.

Cargá los skills `verificacion`, `flujo-git-pr` y `panel-design-ui`. No leas
`docs/` entero. `docs/gestion/estado_proyecto.md` está desfasado al 27/8/2026:
verificá contra el código, no contra ese documento.

## Qué hacer

La rama `feature/dashboard-agro` tiene 10 commits sin PR y le falta el commit
`cc0e90b` de `develop`.

1. Traé `develop` a esa rama con **merge**, no rebase (ya está publicada).
2. Corré `./bin/verify`. Si falla, arreglá **solo** lo que rompió el merge.
3. Abrí PR a `develop` (`gh pr create`, **sin** `--draft`), listando en el
   cuerpo qué tabs entraron y qué quedó fuera. Con la cascada en verde el
   auto-merge lo integra solo: eso es deliberado.

## Qué NO hacer

- No agregues funcionalidad al dashboard. Es una integración, no una fase nueva.
- No reabras decisiones de diseño ya tomadas. Ojo con
  `docs/gestion/plan_dashboard_rediseno.md`: se contradice a sí mismo — la
  cabecera registra la decisión de Fase 6 como confirmada el 28/8/2026
  (enriquecer los tabs existentes, NO rutas propias), mientras §1.4 y la tabla
  de fases todavía la describen como pendiente. Vale la cabecera. Si te parece
  que falta algo visual, anotalo en el cuerpo del PR; no lo implementes.
- No mergees a mano. El auto-merge lo hace cuando CI queda verde.

## Criterio de aceptación

`./bin/verify` devuelve 0 y el PR queda abierto.

## Cierre obligatorio

Escribí `runs/01.estado` con **una sola palabra**: `OK` o `BLOQUEADA`.
Escribí `runs/01.md` con: qué hiciste, el número de PR, y si algo quedó
bloqueado, el motivo exacto y qué decisión hace falta para destrabarlo.
