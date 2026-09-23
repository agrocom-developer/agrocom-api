<!-- ciclo: critica=no turno-noche=1 rama=feature/limpieza-panel etapas=1 -->

# Tarea 129 — limpieza que dejó la homogeneización: CSS muerto y JS huérfano

Última fila de la cola que escribió el dueño el 22/9/2026, con los hallazgos de
la tarea 122 (`docs/gestion/cola_tareas.md`, «Pendiente al 20/9/2026»). Es
chica y va **después** de las fichas (124–126) porque ellas tocan `ordenes.css`
y `reparto-cuadrillas.css`: medí sobre lo que quedó, no sobre la lista vieja.

Cargá el skill `verificacion` y `panel-design-ui`.

## Qué hacer

1. `python3 runs/122-css-muerto.py` y `runs/122-css-patrones.py` sobre `develop`
   actual (si los scripts no están —`runs/` no viaja en git— reconstruilos con el
   mismo criterio: cada clase de `resources/css/pages/*.css` y cuántos usos tiene
   en `app/`, `resources/views/`, `resources/js/` y el resto del CSS). Borrá las
   clases con 0 usos **salvo** en las pantallas excluidas del plan
   (`dashboard.css`, `organizacion.css`, `seleccionar-rol.css`, roles,
   bitácora): esas son del dueño y no se tocan.
2. `resources/js/pages/campos-form.js`: comprobá que nadie lo importa
   (`grep -rn 'campos-form' resources app vite.config.*`) y que ningún Blade
   emite `data-ag-campos-form`; si es así, borralo (HU-94 quedó superada por el
   ADR 0020).
3. `docs/diseno/sistema_diseno_panel.md`: las dos menciones al usuario viejo
   (`carlos.ferrufino` / `password`) pasan a `miguelo` / `0000`. El skill
   `seguridad-roles` (`.claude/`) tiene la misma mención y el ciclo no edita
   `.claude/`: anotalo en `runs/129.md` para el dueño.
4. `./bin/verify` y el barrido `runs/122-barrido.cjs` (copiado a
   `runs/129-barrido.cjs` con salida en `runs/129-capturas/`) con exit 0: nada
   cambió a ojo.

## Qué NO hacer

Ningún cambio funcional ni de copy. Nada en `.claude/`. Sin `git stash`.

## Criterio de aceptación

`./bin/verify` = 0; el barrido sale con 0; `runs/129.md` lista cada clase
borrada con su archivo y la cuenta de usos (0) que lo justifica.

## Cierre obligatorio

`runs/129.estado`, `runs/129.md`, `runs/129.pr.md`. Commits por función, en
español, imperativo, sin `Co-Authored-By`.
