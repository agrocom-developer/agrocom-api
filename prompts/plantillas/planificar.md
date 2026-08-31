# Planificar la tarea siguiente — después de {{ID}}

Sesión nueva y aislada. No implementás nada: tu entregable es **el prompt de la
próxima tarea**, listo para que otra sesión lo ejecute sola.

## Leé, en este orden

1. `runs/{{ID}}.md` y `runs/{{ID}}-veredicto.md` — qué acaba de cerrarse y qué
   quedó afuera. Lo que quedó afuera suele ser la próxima tarea.
2. `docs/gestion/cola_tareas.md` — el backlog automatizable, con su orden.
3. `docs/gestion/automatizacion_desarrollo.md` — qué falta para el turno
   desatendido y en qué orden de dependencia.
4. `docs/gestion/plan_sprints.md` — las HU y TE con sus criterios de
   aceptación.
5. `git log --oneline -15` y `gh pr list --state merged --limit 5` — el estado
   real. Los documentos se desfasan; git no.

## Elegí UNA tarea

La primera de `docs/gestion/cola_tareas.md` que no esté hecha, salvo que lo que
acaba de cerrarse haya dejado algo que la bloquea o la vuelve innecesaria — en
ese caso explicá el cambio de orden en `docs/gestion/cola_tareas.md` y seguí.

Una tarea entra en el ciclo automático solo si cumple las cuatro condiciones:

- **Su criterio de aceptación es un comando con exit code.** Si el criterio es
  "se ve bien" o "el usuario decide", no califica.
- **Es de este repo** (`agrocom-api`). Lo de `agrocom-field` es otro repo y
  este ciclo no lo toca.
- **Cabe en una sesión.** Si necesita más de ~8 archivos nuevos o toca tres
  áreas a la vez, partila y encolá primero la parte que habilita el resto.
- **No depende de una decisión que no está tomada.** Si depende, la tarea es
  escribir la pregunta, no adivinar la respuesta.

Las tareas de la lista "qué no delegar sin revisión línea por línea" de
`CLAUDE.md` — motor de sync, servicio de estados, listeners que generan dinero,
scoping del portal del cliente — **sí se toman**, pero se marcan
`critica=si`: su PR se abre en borrador y lo revisa una persona. No las
saltees por críticas ni las degrades para que dejen de serlo.

## Escribí `prompts/NN-slug.md`

`NN` es el número siguiente de dos dígitos; `slug` de 2–3 palabras. La primera
línea, exactamente este formato:

```
<!-- ciclo: critica=no turno-noche=1 -->
```

- `critica=si` para lo de la lista de arriba (PR en borrador).
- `turno-noche=0` **solo** si la tarea consiste en escribir tests, ADRs o
  configuración de `.claude/`: con `1` esos archivos están congelados, que es
  lo que impide que un agente edite el criterio que lo evalúa. Si la tarea es
  implementar código, va en `1` siempre.

El cuerpo, con estas secciones y sin relleno — mirá `prompts/03-implementar-hu03.md`
como referencia de tono y densidad:

- **Qué hacer**: el objetivo en una frase, y después los pasos concretos. Decí
  qué skills cargar (`verificacion` siempre; los demás según el área). Nombrá
  los archivos que hay que tocar cuando ya se sepan, y qué NO hay que tocar.
- **Qué NO hacer**: los desvíos previsibles. Si en `runs/{{ID}}.md` quedó
  anotada una tentación (reabrir una decisión, ampliar el alcance), nombrala.
- **Criterio de aceptación**: el comando, textual, y qué tiene que devolver.
  Casi siempre `./bin/verify` devuelve 0, más lo específico de la tarea.
- **Máximo de intentos**: 3 salvo que haya razón para menos.
- **Cierre obligatorio**: escribir `runs/NN.estado` con una palabra (`OK`,
  `BLOQUEADA`), `runs/NN.md` con qué se hizo y qué quedó afuera, y
  `runs/NN.pr.md` con el título del PR en la primera línea y el cuerpo debajo
  (el ciclo lo usa tal cual para abrirlo).
- **Commits**: recordá que van **agrupados por función** — un commit por pieza
  coherente (esquema, modelo, casos de uso, endpoints, pantalla, permisos,
  tests), en español, imperativo, explicando el porqué y no el qué. Ni un
  commit único con todo, ni un commit por archivo.

## Actualizá la cola

- Agregá `NN` como última línea de `runs/cola.txt`.
- Marcá en `docs/gestion/cola_tareas.md` lo que se cerró y agregá la fila nueva
  si no estaba.

## Si no hay ninguna tarea que califique

Escribí `runs/DETENER` con el motivo en una línea y, debajo, la pregunta
concreta que el usuario tiene que responder para que el ciclo pueda seguir. Eso
detiene el bucle de forma ordenada — es una respuesta válida, no una falla.

## Cierre obligatorio

`runs/{{ID}}-plan.md`: qué tarea elegiste, por qué esa y no otra, y qué queda
detrás de ella en la cola.
