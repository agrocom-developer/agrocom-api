<!-- ciclo: critica=no turno-noche=1 rama=feature/cierre-panel-homogeneo etapas=2 -->

# Tarea 122 — cierre de la homogeneización del panel

Última tarea del plan `docs/gestion/plan_homogeneizacion_panel.md` (leelo
entero). Las tareas 112 a 121 llevaron cada módulo al patrón de Comercial; esta
barre lo que quedó y deja la documentación diciendo la verdad. No depende de
ninguna decisión: si una tarea anterior quedó trabada, lo suyo NO se hace acá —
se anota.

Cargá los skills `verificacion` y `panel-design-ui`.

## Qué hacer

1. **Estado real.** Leé `runs/111.estado` … `runs/121.estado`, la tabla §7 del
   plan y `gh pr list --state merged --limit 20`. Corregí la tabla §7 con lo que
   diga git (PR mergeado), no con lo que declaró cada sesión.

2. **Lista de pendientes.** `docs/diseno/panel_homogeneo_pendientes.txt` tiene
   que quedar solo con pantallas de tareas que NO se integraron, cada una con un
   comentario `#` que diga de qué tarea es y por qué quedó. Si todas se
   integraron, queda vacía (solo su comentario de cabecera). No homogeneices acá
   una pantalla que le tocaba a otra tarea.

3. **CSS muerto.** Con los módulos migrados a `index-table`, `filter-panel` y
   `form-layout`, buscá en `resources/css/pages/` las reglas que ya no aplica
   ningún Blade: tablas por página (`.ag-<pagina>__tabla`, `__head`, `__fila`),
   `.ag-filtros`, layouts propios (`__layout`, `__main`, `__aside`). Para cada
   clase candidata: `grep -rn` en `app/`, `resources/views/` y `resources/js/`;
   se borra solo si no aparece en ningún lado. Si un archivo de página queda
   vacío, se borra con su `@import` de `pages/index.css`. Lo mismo con JS de
   página que solo servía al `confirm()` nativo.

4. **Componentes sin uso.** `molecules/confirm-button` y `molecules/state-transition`:
   si ningún Blade los usa ya, NO los borres — anotalo en `runs/122.md` como
   decisión para el dueño.

5. **Documentación:**
   - `docs/diseno/guia_pantalla_panel.md`: la primera línea de §6.2 («El resto
     del rollout todavía migra…») y el último párrafo de §5.1 (pantallas que aún
     usan `alert-strip` para el vacío) dejan de ser ciertos — reescribilos con
     el estado real. El párrafo «Quedan 25 pantallas por construir» de la
     cabecera también, si el conteo cambió. Sumá en §6.2 la regla de color de
     las acciones de fila (plan §3.1) y en §6.3.1 la del aviso de estado del
     aside (plan §3.5), con su referencia viva.
   - `docs/gestion/estado_proyecto.md`: una entrada con qué quedó homogéneo, qué
     no, y los PR.
   - `docs/gestion/cola_tareas.md`: filas 111–122 marcadas.
   - El checklist §8 de la guía dice `carlos.ferrufino` / `password`: el usuario
     vigente es `miguelo` / `0000`.

6. **Barrido visual final.** Un script Playwright (en `runs/`) que recorra TODOS
   los `index` del panel que el menú de `miguelo` alcanza, en tema claro y
   oscuro, con capturas a la ruta ABSOLUTA `$PWD/runs/122-capturas/`, y falle si
   alguna página responde ≥ 500, si `.ag-panel__content` desborda en horizontal,
   o si en el DOM queda un `.ag-filtros`. Mirá las capturas: lo que se vea
   distinto del resto y no esté en las exclusiones, va a `runs/122.md` como
   hallazgo — no lo arregles acá si pertenece a una tarea trabada.

## Cómo repartir las etapas

- Etapa 1: estado real, pendientes, CSS muerto, `./bin/verify`.
- Etapa 2: documentación y barrido visual.

## Qué NO hacer

- No tocar las pantallas excluidas (plan §1.1) ni ningún `show.blade.php`.
- No borrar componentes del catálogo.
- No escribirte tareas nuevas ni encolar nada: los hallazgos van a `runs/122.md`
  y a la sección «Deuda técnica detectada» de `cola_tareas.md`.
- No abrir el PR: lo abre el ciclo.

## Criterio de aceptación

`./bin/verify` = 0 (con `npm run build` incluido: es el que avisa si se borró
CSS que algo importaba), el script del barrido visual sale con 0, y
`docs/diseno/panel_homogeneo_pendientes.txt` no nombra ninguna pantalla de una
tarea con PR mergeado.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/122-verify.log 2>&1 & echo $! > runs/122-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/122-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/122-verify.log`.

## Cierre de cada etapa

`runs/122.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/122.md`
con qué se hizo, qué falta y los hallazgos para el dueño. Al llegar a `OK`:
`runs/122.pr.md` (título en la primera línea, cuerpo debajo).

Commits agrupados por función, en español, imperativo. Sin `Co-Authored-By`.
