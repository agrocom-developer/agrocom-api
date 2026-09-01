# Planificar la tarea siguiente — después de {{ID}}

Sesión nueva y aislada. No implementás nada: tu entregable es **el prompt de la
próxima tarea**, listo para que otra sesión lo ejecute sola.

## La unidad de trabajo es una HU o una TE completa

Una tarea del ciclo entrega **una historia de usuario o una tarea técnica
entera de `docs/gestion/plan_sprints.md`**, con todos sus criterios de
aceptación cubiertos, en un solo PR. No media HU, no "la primera parte de
TE-05", no un retoque suelto.

Esto es un cambio respecto de cómo se planificaba antes, y es deliberado: la
regla vieja pedía que la tarea "cupiera en una sesión", así que cada HU se
partía hasta caber y el historial terminó lleno de PRs de un commit. El tamaño
de una sesión ya no manda. **El ciclo encadena hasta `etapas=` sesiones sobre
la misma rama**: cada una retoma donde quedó la anterior y suma sus commits, y
el PR se abre recién cuando la HU está entera. Tu trabajo es dimensionar la
tarea por la historia, no por la sesión.

Sí sigue valiendo partir cuando **la historia misma** tiene un corte real —una
dependencia que todavía no existe, un módulo que no está construido. Ese
recorte se escribe explícito en el prompt, con el porqué (como hizo la tarea 08
con recetas y productos, que esperan al módulo `Mezclas`).

## Leé, en este orden

1. `runs/{{ID}}.md`, `runs/{{ID}}-veredicto.md` y `runs/{{ID}}.estado` — qué
   acaba de cerrarse y qué quedó afuera. Lo que quedó afuera suele ser la
   próxima tarea. **Ojo con el estado**: si dice `BLOQUEADA`, `RECHAZADA`,
   `AGOTADA` o `INCOMPLETA`, esa tarea se trabó y el ciclo te llamó igual para
   que la cola no se quede vacía. No la reescribas ni la repitas: quedó marcada
   para el usuario. Elegí la siguiente, y si lo que la trabó también afecta a la
   que sigue, decilo en tu reporte.
2. `docs/gestion/cola_tareas.md` — el backlog automatizable, con su orden.
3. `docs/gestion/plan_sprints.md` — las HU y TE con sus criterios de
   aceptación. **Es la fuente del alcance de la tarea que escribas.**
4. `docs/gestion/automatizacion_desarrollo.md` — qué falta para el turno
   desatendido y en qué orden de dependencia.
5. `docs/gestion/estado_proyecto.md` — dónde quedó el proyecto y qué está
   marcado como pendiente.
6. `git log --oneline -15` y `gh pr list --state merged --limit 5` — el estado
   real. Los documentos se desfasan; git no.

## Elegí UNA tarea

La primera de `docs/gestion/cola_tareas.md` que no esté hecha, salvo que lo que
acaba de cerrarse haya dejado algo que la bloquea o la vuelve innecesaria — en
ese caso explicá el cambio de orden en `docs/gestion/cola_tareas.md` y seguí.

Si la cola se quedó sin filas, la próxima tarea sale de la primera HU o TE
pendiente de `plan_sprints.md`, en el orden del sprint. Agregá su fila a
`cola_tareas.md` con su criterio ejecutable.

**Terminar un sprint no termina el trabajo.** Si la última HU pendiente del
sprint en curso ya está hecha, seguí con la primera del sprint siguiente. El
plan tiene seis; el ciclo los recorre en orden sin detenerse entre uno y otro.

Una tarea entra en el ciclo automático solo si cumple estas tres condiciones:

- **Su criterio de aceptación es un comando con exit code.** Si el criterio es
  "se ve bien" o "el usuario decide", no califica.
- **Es de este repo** (`agrocom-api`). Lo de `agrocom-field` es otro repo y
  este ciclo no lo toca.
- **No depende de una decisión que no está tomada.** Si depende, la tarea es
  escribir la pregunta, no adivinar la respuesta.

### Una tarea que no califica se saltea, no detiene el ciclo

Varias HU y TE del plan no son automatizables desde acá, y son parte del camino
normal: TE-02 es un spike de hardware con el RC en mano; TE-04 vive en
`agrocom-field`; HU-05 necesita la app para demostrarse; la reunión de cierre de
la especificación es de negocio. Toparse con una de ellas **no es motivo para
parar**.

Cuando la primera pendiente no califica: anotala en la sección "Fuera del ciclo
automático" de `cola_tareas.md` —con cuál de las tres condiciones incumple, en
una línea— y seguí bajando hasta la primera que sí califique. Esa es la que
escribís.

Detener el ciclo (ver más abajo) es el último recurso: solo si **ninguna** de las
pendientes de los seis sprints califica.

Las tareas de la lista "qué no delegar sin revisión línea por línea" de
`CLAUDE.md` — motor de sync, servicio de estados, listeners que generan dinero,
scoping del portal del cliente — **sí se toman**, pero se marcan
`critica=si`: su PR se abre en borrador y lo revisa una persona. No las
saltees por críticas ni las degrades para que dejen de serlo.

## Si la próxima tarea ya tiene prompt

Puede pasar: alguien la escribió a mano por adelantado. Si
`prompts/NN-*.md` ya existe para la tarea que elegiste, **no la reescribas ni
la dupliques**. Confirmá que su id esté en `runs/cola.txt` (agregalo si no
está), anotá en tu reporte que ya estaba escrito, y terminá. Un prompt
reescrito por encima de otro pierde el criterio con el que se redactó.

## Escribí `prompts/NN-slug.md`

`NN` es el número siguiente de dos dígitos; `slug` de 2–3 palabras. La primera
línea, exactamente este formato:

```
<!-- ciclo: critica=no turno-noche=1 rama=feature/sync-idempotente etapas=4 -->
```

- **`rama=`** — la rama que va a crear el ciclo. **Siempre `feature/` más 2–3
  palabras de la función del proyecto** que la tarea construye:
  `feature/sync-idempotente`, `feature/ordenes-offline`,
  `feature/cola-validacion`. Nunca el número de la tarea, nunca el nombre de la
  actividad (`feature/tarea-09`, `feature/implementar-tests`). La rama la crea
  el ciclo con este valor, no la sesión — si falta, se deriva del nombre del
  archivo, que por eso conviene que ya sea la función.
- **`etapas=`** — cuántas sesiones puede encadenar el ciclo sobre esa rama para
  terminar la HU. Dimensionalo por el tamaño de la historia, no por miedo: 2
  para algo acotado, 4–5 para una HU de 2 días o más del plan de sprints. Si no
  lo ponés, son 5.
- `critica=si` para lo de la lista de arriba (PR en borrador).
- `turno-noche=1` **siempre**. Lo que cambia por tarea es `descongela=`, con
  las zonas que esa tarea necesita escribir, separadas por coma:

  | Zona | Abre | Cuándo corresponde |
  |---|---|---|
  | `tests` | `tests/**` | La tarea escribe tests — casi todas las que implementan algo con su cobertura |
  | `decisiones` | `docs/decisiones/**` | Solo para **ampliar** lo que un ADR dejó explícitamente abierto, nunca para revisar lo que decidió |
  | `claude` | `.claude/**` | La tarea cambia un skill, un agente o un hook |
  | `github` | `.github/**` | La tarea toca el CI |

  Sin `descongela`, esas zonas están congeladas: es lo que impide que una sesión
  edite el criterio que la evalúa. Pedí solo lo que la tarea necesita — una que
  escribe un test no tiene por qué poder reescribir un ADR. `CLAUDE.md` no se
  abre con ninguna zona: las invariantes son del usuario.

  El valor lo heredan también las sesiones de corrección, así que una tarea cuyo
  entregable es un test necesita `descongela=tests` para poder corregirlo.

  (`turno-noche=0` sigue existiendo y apaga el congelamiento entero. No lo uses:
  está para depurar el ciclo a mano.)
- `modelo=` solo si la tarea justifica salirse del modelo por fase que ya usa
  el ciclo. No lo pongas por costumbre.

El cuerpo, con estas secciones y sin relleno. Tono: frases cortas, el porqué
antes del qué, cero relleno. Si querés una referencia de densidad, los prompts
de las tareas 01 a 08 están en el historial de git
(`git show "$(git rev-list -1 HEAD -- prompts/03-implementar-hu03.md)":prompts/03-implementar-hu03.md`)
— se borraron del árbol al reiniciar la cola, no porque estuvieran mal escritos.

Secciones:

- **Qué hacer**: el objetivo en una frase, y después los pasos concretos. Decí
  qué skills cargar (`verificacion` siempre; los demás según el área). Nombrá
  los archivos que hay que tocar cuando ya se sepan, y qué NO hay que tocar.
  Cubrí la HU entera: **todos** sus criterios de aceptación del plan de
  sprints, cada uno con su test.
- **Cómo repartir las etapas**: si la HU claramente no entra en una sesión,
  sugerí el corte (p. ej. "etapa 1: migración y modelo; etapa 2: caso de uso y
  endpoint; etapa 3: tests de replay"). Es una sugerencia, no un contrato — la
  sesión puede llegar más lejos o menos.
- **Qué NO hacer**: los desvíos previsibles. Si en `runs/{{ID}}.md` quedó
  anotada una tentación (reabrir una decisión, ampliar el alcance), nombrala.
- **Criterio de aceptación**: el comando, textual, y qué tiene que devolver.
  Casi siempre `./bin/verify` devuelve 0, más lo específico de la tarea.
- **Cierre obligatorio de cada etapa**: `runs/NN.estado` con una sola palabra —
  `PARCIAL` si avanzó y commiteó pero la HU sigue abierta, `OK` recién cuando
  está entera, `BLOQUEADA` si falta una decisión que no le corresponde.
  `runs/NN.md` con qué se hizo y **qué falta**, concreto, para que la etapa
  siguiente no adivine. Y, al cerrar con `OK`, `runs/NN.pr.md` con el título del
  PR en la primera línea y el cuerpo debajo (el ciclo lo usa tal cual).
- **Commits**: recordá que van **agrupados por función** — un commit por pieza
  coherente (esquema, modelo, casos de uso, endpoints, pantalla, permisos,
  tests), en español, imperativo, explicando el porqué y no el qué. Ni un
  commit único con todo, ni un commit por archivo. **Sin trailer
  `Co-Authored-By`.** Varios commits por PR es lo normal y lo buscado.

## Actualizá la cola

- Agregá `NN` como última línea de `runs/cola.txt`.
- Marcá en `docs/gestion/cola_tareas.md` lo que se cerró y agregá la fila nueva
  si no estaba, con la HU/TE que cubre.

## Si NINGUNA tarea de los seis sprints califica

Recién ahí escribí `runs/DETENER` con el motivo en una línea y, debajo, la
pregunta concreta que el usuario tiene que responder para que el ciclo pueda
seguir. Eso detiene el bucle de forma ordenada — es una respuesta válida, no una
falla.

No lo uses porque la primera pendiente no calificaba, ni porque la tarea anterior
se trabó: para lo primero, salteá y anotá; lo segundo no dice nada sobre la que
sigue, y el ciclo va a tomar la próxima igual.

## No commitees

Dejá los archivos escritos y nada más. No los commitees, no crees ninguna rama,
no abras ningún PR. El ciclo los commitea **como primer commit de la rama de la
tarea que encolaste**, en la vuelta siguiente: así el prompt viaja adentro del
PR de su propia HU en vez de gastar un PR de una línea para él solo. Si
commiteás vos, esa vuelta arranca con el árbol sucio y se aborta antes de
empezar.

## Cierre obligatorio

`runs/{{ID}}-plan.md`: qué tarea elegiste, por qué esa y no otra, qué HU o TE
del plan de sprints cubre entera, cuántas etapas le diste y con qué criterio, y
qué queda detrás de ella en la cola.
