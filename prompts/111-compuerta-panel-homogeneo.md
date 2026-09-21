<!-- ciclo: critica=no turno-noche=1 rama=feature/compuerta-panel-homogeneo etapas=2 descongela=tests,claude -->

# Tarea 111 — compuerta de la homogeneización del panel

Primera tarea del plan `docs/gestion/plan_homogeneizacion_panel.md` (leelo
entero antes de empezar: es la fuente del alcance). Las tareas 112 a 122 llevan
cada módulo al patrón de Comercial; esta convierte «quedó igual que Comercial»
en un comando con exit code. Sin ella, cada tarea se cerraría por criterio
propio.

Cargá los skills `verificacion` y `panel-design-ui`.

## Qué hacer

1. **`tests/Unit/PanelHomogeneoTest.php`** (Pest, sin HTTP ni base: lee
   archivos, como `PulidoNavegacionPanelTest` y `RedaccionNeutraTest`). Recorre
   `app/Dominios/*/Infraestructura/Http/Views/pages/` y exige:

   **A todo `index.blade.php` que pinte una tabla de objetos:**
   - usa `x-molecules.index-table` (nada de tabla propia por página);
   - no queda `class="ag-filtros"` ni ningún `confirm(` nativo (ni en el Blade
     ni en su JS de página);
   - si la fila tiene botones, van dentro de `x-organisms.row-actions`, y dentro
     de `row-actions` no hay `<form` ni `x-molecules.confirm-modal`;
   - los vacíos usan `x-molecules.empty-state`, y ningún `empty-state` de un
     `index` lleva `<x-slot:action>`;
   - color de las acciones de fila (plan §3.1): un botón con `icon="edit"` es
     `warning-outline`, con `icon="visibility"` es `info-outline`, con
     `icon="delete"` es `danger-outline`; ningún botón `size="sm"` dentro de
     `row-actions` usa `variant="primary"`.

   **A todo formulario (`_formulario.blade.php`, o `create`/`edit` sin partial):**
   - usa `x-molecules.form-layout`, `x-molecules.form-section`,
     `x-organisms.form-actions-bar` y `x-organisms.page-header`;
   - pinta `session('estado')` con `alert-strip`;
   - si el directorio tiene `edit.blade.php`, el formulario declara
     `<x-slot:aside>`;
   - si el módulo tiene una `Transiciones<Objeto>.php` para ese objeto y el
     objeto tiene ficha de edición, usa `x-molecules.step-arrow` (mapealo con una
     tabla explícita directorio → clase dentro del test, no por adivinanza de
     nombres).

2. **Dos listas, con distinto dueño:**
   - **Exclusiones permanentes**, dentro del test (congelado en las demás
     tareas): `Seguridad/roles`, `Seguridad/organizacion`, `Seguridad/bitacora`,
     `Seguridad/dashboard*`, `Seguridad/{perfil,configuracion,busqueda}`,
     `Portal/*`, y todo `show.blade.php`. Son las del plan §1.1.
   - **Pendientes**, en `docs/diseno/panel_homogeneo_pendientes.txt`: una ruta
     relativa por línea (`Finanzas/gastos/index.blade.php`), `#` para
     comentarios. El test perdona a las que figuran ahí. Cargala con el
     diagnóstico real de hoy (plan §2 — pero medilo de nuevo, no lo copies).
   - **La lista no puede mentir:** el test falla si una ruta pendiente no existe,
     o si una pantalla pendiente ya cumple todas las reglas (hay que sacarla).
     Así cada tarea solo puede achicar la lista, y achicarla la vuelve más
     exigente, nunca menos.

3. **Las referencias tienen que pasar sin estar en pendientes.** Si una pantalla
   de referencia (Campaña, Cliente, Propiedad, Lote, Contrato, Orden de
   aplicación, Orden de trabajo, Estadías, Cuadrillas) incumple una regla,
   primero decidí si la regla está mal escrita. Corrección conocida: hay un
   botón «Ver» con `variant="outline"` en una referencia — pasa a
   `info-outline`. `Comercial/cultivos` sí va a pendientes (la cierra la 120).

4. **Variantes de botón.** Confirmá en `atoms/button` + `button.css` que exista
   la variante `<tono>-outline` para cada tono que use algún `TONO_POR_ESTADO`
   del repo (`grep -rn TONO_POR_ESTADO app`). Si falta alguna, agregala con
   tokens, siguiendo las que ya están; anotala en
   `docs/diseno/sistema_diseno_panel.md` §3.

5. **Skill `panel-design-ui`:** una sección corta «Homogeneización en curso» que
   apunte al plan, a la lista de pendientes y al test. Dos o tres líneas.

6. `docs/diseno/guia_pantalla_panel.md` §8: un ítem de checklist — la pantalla
   no figura en `panel_homogeneo_pendientes.txt`.

## Cómo repartir las etapas

- Etapa 1: test + lista de pendientes + correcciones de las referencias.
- Etapa 2: variantes de botón, skill, guía, `./bin/verify` completo.

## Qué NO hacer

- No homogeneizar ninguna pantalla pendiente: eso es de las tareas 112–121.
- No relajar una regla para que una referencia pase sin entender por qué falla.
- No escribir un test que ejercite HTTP (CLAUDE.md §Testing).

## Criterio de aceptación

`./bin/verify` = 0, con `PanelHomogeneoTest` corriendo y, como prueba de que
muerde: sacá a mano una línea de la lista de pendientes, corré
`docker compose exec -T app ./vendor/bin/pest tests/Unit/PanelHomogeneoTest.php`
y tiene que fallar nombrando esa pantalla y la regla. Volvé a poner la línea y
dejá el resultado de esa prueba en `runs/111.md`.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/111-verify.log 2>&1 & echo $! > runs/111-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/111-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/111-verify.log`.

## Cierre de cada etapa

`runs/111.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/111.md`
con qué se hizo y qué falta. Al llegar a `OK`: `runs/111.pr.md` (título en la
primera línea, cuerpo debajo) y la fila 111 de la tabla §7 del plan marcada.
**No abras el PR vos**: lo abre el ciclo.

Commits agrupados por función, en español, imperativo. Sin `Co-Authored-By`.
