<!-- ciclo: critica=no turno-noche=1 rama=feature/usuarios-dispositivos-panel etapas=2 -->

# Tarea 123 — integrar el panel homogéneo de Usuarios, Dispositivos y Versiones de APK (cierra la 121)

La tarea 121 quedó hecha y verificada en la rama **local**
`feature/panel-usuarios-dispositivos` (seis commits sobre `a3644f6c`; el último,
`107032df`, la marca hecha en el plan). Nunca llegó a `develop`: el push se cortó
(`unexpected disconnect`), el ciclo no pudo abrir el PR y siguió con la 122. Por
eso `runs/121.estado` dice `OK` y miente. Es la única fila del plan
`docs/gestion/plan_homogeneizacion_panel.md` sin entregar (§7).

Esta tarea **no implementa nada nuevo**. Trae esa rama a una rama nueva nacida de
`develop`, resuelve lo que la 122 dejó escrito en contra, corrige los documentos
que dicen «sin integrar», verifica, y deja que el ciclo empuje y abra el PR. La
rama es nueva a propósito: el ciclo no puede retomar la vieja porque
`docs/gestion/cola_tareas.md` difiere entre ella y `develop` (lo cambió el PR
#264) y `git checkout` se niega a cambiar de rama con ese archivo modificado.

Carga los skills `verificacion` y `panel-design-ui`. Lee `runs/121.md`,
`runs/121.pr.md`, `runs/122-plan.md` y, de `runs/122.md`, los hallazgos 1 y 2.

## Qué hacer

1. **Comprueba que hay algo que integrar.**
   - `git rev-parse --verify feature/panel-usuarios-dispositivos` debe resolver, y
     `git log --oneline develop..feature/panel-usuarios-dispositivos` debe listar
     los seis commits de la 121 (el último es `107032df`).
   - `gh pr list --state all --head feature/panel-usuarios-dispositivos` debe salir
     vacío, y también
     `git log develop --format=%s | grep -iE 'usuarios, dispositivos y versiones de apk|\(tareas? 121'`.
     Busca solo en el **asunto**: el cuerpo del commit del PR #264 nombra la «tarea 121»
     (para decir que no se integró) y un `--grep` sobre el mensaje entero se dispara con eso.
   - Si alguien ya la integró (PR abierto o mergeado, o commit en `develop`), no
     hagas nada: `runs/123.estado` = `BLOQUEADA` y `runs/123.md` con lo que
     encontraste. Si la rama local no existe, igual: `BLOQUEADA`, sin reimplementar.

2. **Trae la 121:** `git merge feature/panel-usuarios-dispositivos`. Sin rebase, sin
   `git reset --hard` (un hook lo bloquea) y sin `git stash` (hay uno ajeno en el
   árbol). La planificación hizo la prueba en seco con `git merge-tree` y chocan
   **dos archivos, los dos de docs**:
   - `docs/diseno/panel_homogeneo_pendientes.txt` → queda solo la cabecera, sin
     ninguna ruta. Reescribe las líneas de la cabecera que la 122 dejó hablando de
     pantallas sin integrar («La tarea 122 la dejó con lo que NO se integró», «quedan
     4…») para que digan la verdad: la lista está vacía y las 46 pantallas salieron
     con los PR #255 a #263 y el de esta rama.
   - `docs/gestion/plan_homogeneizacion_panel.md` §7 → fila 121 en **hecha** (lado
     de la 121, con el mismo giro que la 122: «el que abre el ciclo con esta rama»),
     y el párrafo «Cierre (tarea 122…)» ajustado: cuenta que la 121 se integró después,
     con la tarea 123.
   Si aparece un conflicto en `app/`, `resources/` o `lang/`, no debería: detente y
   averigua qué cambió en `develop` antes de resolver; no te quedes con un lado a ciegas.
   Commitea el merge (español, imperativo).

3. **Documentos que quedaron diciendo «sin integrar»** (pasan a «integrada»):
   - `docs/gestion/estado_proyecto.md`: la nota de cierre de la homogeneización
     (busca «Sin integrar: la tarea 121»).
   - `docs/gestion/cola_tareas.md`: **aplica primero los cambios que dejó la
     planificación en `runs/122-cola-cambios.md`** (encabezado, filas 95 y 102 a 109,
     fila 123 nueva, cuatro filas de «Fuera del ciclo automático», párrafo final): la
     planificación no pudo editar ese archivo. Cada cambio cita el PR o el ADR que lo
     prueba; compruébalo con `gh pr view N --json state,title` antes de marcarlo, y si
     alguno no se sostiene, no lo cambies y dilo en `runs/123.md`. Después: fila 121 →
     `hecha`; fila 123 → `hecha` (ambas «en el PR que abre el ciclo con esta rama»); en
     «Deuda técnica detectada», el primer punto del bloque del 20/9/2026 («La tarea 121
     no está integrada…») pasa a resuelto, y en el punto de `?q[]=x` saca Usuarios de la
     lista de listados que dan 500 (la 121 lo arregla).
   - `docs/diseno/guia_pantalla_panel.md` (§5.1 y §6.2, líneas ~183 y ~199): si dicen
     que Usuarios, Dispositivos o Versiones de APK siguen sin migrar, corrígelo.
   - Al terminar: `grep -rn "sin integrar" docs/` no debe dejar ninguno que hable de la 121.

4. **CSS de las tres hojas.** La 122 no borró `usuarios.css`, `dispositivos.css` ni
   `versiones-apk.css` porque la 121 las reescribe. Con el merge hecho, corre
   `python3 runs/122-css-muerto.py` y `python3 runs/122-css-patrones.py`. Si listan
   clases de esas tres hojas, bórralas (mismo criterio de la 122: solo si no aparecen
   en `app/`, `resources/views/`, `resources/js/`, `lang/` ni otro CSS). Si no listan
   ninguna, no toques nada.

5. **Verifica.**
   - Commitea todo **antes** de lanzar `./bin/verify`: dos tareas se agotaron esperándolo
     con el trabajo sin commitear.
   - `./bin/verify` = 0. Incluye `npm run build`, `ArquitecturaModulosTest` (la 121 agrega
     un `Contratos/` en Personal) y `PanelHomogeneoTest` con la lista ya vacía.
   - Barrido de solo lectura sobre el árbol mezclado:
     `NODE_PATH=$PWD/node_modules node runs/122-barrido.cjs` → exit 0 (cómo correrlo:
     `runs/122.md` §6). Mira las capturas de Usuarios (listado y formulario), Dispositivos
     y Versiones de APK, en claro y oscuro: en el barrido de la 122 se veían fuera de
     línea (filas altas, sin columna de índice, «Autorizar» en verde relleno) y ya tienen
     que verse de la misma familia que Clientes.
   - `?q[]=x` en Usuarios y Dispositivos → 200 (`runs/122-sondas.cjs`, solo GET).

## Cómo repartir las etapas

- Etapa 1: pasos 1 a 4 y `./bin/verify`.
- Etapa 2, solo si hace falta: barrido, sondas y cierre.

Lo normal es que entre en una.

## Qué NO hacer

- No reimplementar ni «mejorar» lo de la 121. Si al mirar las capturas algo no te
  gusta, va a `runs/123.md`, no a un commit.
- No tocar los hallazgos de la 122 que están fuera de estas tres pantallas: `?q[]=x` en
  los otros controladores, CSS muerto de otras hojas, `campos-form.js`, `confirm-button`,
  `confirm()` nativo, `carlos.ferrufino`. Siguen en «Deuda técnica detectada».
- No borrar ni modificar la rama vieja `feature/panel-usuarios-dispositivos`: limpiar
  ramas lo decide el usuario. Queda como está.
- No borrar los datos demo del compose (la versión de APK `0.17.2` autorizada, los
  usuarios de prueba y los dos dispositivos). Este barrido no crea nada.
- No hacer `git push` ni abrir el PR: lo hace el ciclo. Si el push se corta otra vez,
  el ciclo lo anota; no es asunto de la sesión.
- No escribirte tareas nuevas ni encolar nada.

## Criterio de aceptación

- `./bin/verify` devuelve 0.
- `git merge-base --is-ancestor feature/panel-usuarios-dispositivos HEAD` y
  `git merge-base --is-ancestor develop HEAD` devuelven 0 (la rama contiene a la 121 y
  a `develop`; así el ciclo no rebasa antes del push).
- `grep -vE '^\s*(#|$)' docs/diseno/panel_homogeneo_pendientes.txt | wc -l` imprime `0`.
- `grep -c '^| 123 |' docs/gestion/cola_tareas.md` imprime `1`.
- `NODE_PATH=$PWD/node_modules node runs/122-barrido.cjs` sale con 0.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lánzalo una vez con
`nohup ./bin/verify > runs/123-verify.log 2>&1 & echo $! > runs/123-verify.pid` y
espéralo con llamadas Bash cortas:
`p=$(cat runs/123-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/123-verify.log`.

## Cierre de cada etapa

`runs/123.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/123.md` con qué
se hizo, qué falta y los hallazgos para el dueño. Al llegar a `OK`: `runs/123.pr.md`
(título en la primera línea, cuerpo debajo). Parte de `runs/121.pr.md`, con el título
`Homogeneiza el panel de Usuarios, Dispositivos y Versiones de APK (tareas 121 y 123)`,
y agrega un párrafo «Integración» que cuente por qué llega ahora (el push de la 121 se
cortó), cómo se resolvió el merge y qué documentos se corrigieron. En «Para el dueño»,
deja el punto del `?q[]=x` con lo que midió la 122 (Clientes, Personas, Bases,
Contratos, Cultivos, Repuestos, Campañas y Lotes).

Commits agrupados por función, en español, imperativo, explicando el porqué. Sin
`Co-Authored-By`.

## Nota para la planificación siguiente (no es para esta sesión)

Detrás de esta tarea no queda ninguna HU ni TE de `plan_sprints.md` que califique: ver
`runs/122-plan.md` y la sección «Fuera del ciclo automático» de `cola_tareas.md`. Cuando
esta cierre, corresponde `runs/DETENER` con la pregunta que ese archivo deja escrita.
