# Automatización del desarrollo

**Última actualización: 31/8/2026.** Este documento describe qué parte del ciclo de
desarrollo ya no depende de que una persona esté mirando la pantalla, y qué falta
para que deje de depender del todo. No es una decisión de arquitectura (esas van a
`docs/decisiones/`): es el estado operativo de la automatización.

## La regla que ordena el resto

**Una tarea puede avanzar sin supervisión solo si su criterio de aceptación es un
comando que devuelve 0 o 1.** Si no se puede expresar así, la revisa una persona.

Esto ya está escrito, sin nombrarlo, en `CLAUDE.md`: la sección "qué no delegar sin
revisión línea por línea" (motor de sync, servicio de estados, listeners que generan
dinero, scoping del portal del cliente) es exactamente la lista de lo que **no**
califica, por mucho que la suite esté en verde.

## Lo que ya está automatizado

### 1. La compuerta: `bin/verify`

Un solo comando con exit code honesto:

```
./bin/verify                # cascada completa
./bin/verify --sin-assets   # solo PHP, para iterar
```

Encadena, deteniéndose en la primera falla:

| Etapa | Dónde corre |
|---|---|
| Pint (`--test`) | contenedor `app` (PHP 8.3) |
| Larastan nivel 6 | contenedor `app` |
| Pest (SQLite en memoria) | contenedor `app` |
| Vite (`npm run build`) | host (la imagen no trae Node) |

Las tres etapas de PHP están además en `composer verify`, que es lo que el script
ejecuta dentro del contenedor. Es la misma cascada que corre `laravel-tests` en
`.github/workflows/ci.yml`, así que un verde local predice el verde de CI.

### 2. El merge: `auto-merge.yml`

Ya no hay botón humano. El PR se mergea con squash en cuanto `laravel-tests` queda
verde. Ver el skill `flujo-git-pr` y `docs/decisiones/0006-gitflow-simplificado.md`.

### 3. Los guardarraíles: hooks `PreToolUse`

`.claude/settings.json` registra dos hooks que corren **antes** de cada herramienta
y cuya denegación gana incluso sobre los modos permisivos:

- `.claude/hooks/guardarrail-bash.sh` — lista negra de comandos: push a
  `master`/`develop`, `push --force`, `reset --hard`, `clean -fdx`, `branch -D`,
  `filter-branch`, `migrate:fresh`/`refresh`/`reset`, `db:wipe`,
  `docker compose down -v`, `docker volume rm`, DDL destructivo suelto, `rm -rf`
  fuera del scratchpad, y toda lectura o escritura del `.env` real. Commit sobre
  `master` se deniega; sobre `develop` se pide confirmación.
- `.claude/hooks/guardarrail-archivos.sh` — el `.env` real no se edita nunca. Con
  `AGROCOM_TURNO_NOCHE=1` se congelan además `tests/`, `docs/decisiones/`,
  `CLAUDE.md`, `.claude/` y `.github/`.

Ese último punto es el que evita la trampa clásica: un agente que puede editar el
test que lo evalúa no está siendo evaluado. Los tests se escriben en una tarea
distinta de la que implementa el código que deben validar.

### 4. El contexto: skills por área

`.claude/skills/` guarda el contexto que antes había que reconstruir leyendo
`docs/` entero en cada sesión: `verificacion`, `dominio-backend`, `modelo-datos`,
`flujo-git-pr`, `seguridad-roles`, `panel-design-ui`.

### 5. El bucle de cola: `bin/ciclo`

`bin/iteracion` corre una cola **fija** de prompts escritos a mano. `bin/ciclo`
corre una cola que **se extiende sola**: cuando una tarea queda integrada, una
sesión de planificación lee el backlog y escribe el prompt de la siguiente.

```
bin/ciclo --fondo        # arranca desprendido de la terminal
bin/ciclo --estado       # en qué fase de qué tarea está
bin/ciclo --detener      # parada ordenada: termina la fase en curso y no toma otra tarea
bin/ciclo --reanudar     # levanta la bandera de parada
```

Cinco fases por tarea, cada una en su **propia sesión** con contexto limpio:

| Fase | Qué hace | Dónde queda el resultado |
|---|---|---|
| implementar | Ejecuta `prompts/NN-*.md`, commitea agrupado por función y corre la cascada | `runs/NN.estado`, `runs/NN.md` |
| verificar | Sesión independiente, con los tests congelados, que decide si se puede integrar | `runs/NN.veredicto` |
| PR | Abre el PR con el título y cuerpo que dejó la tarea | `runs/NN.pr` |
| esperar CI | Sondea hasta que `auto-merge` integra, o corrige si queda en rojo | bitácora |
| planificar | Elige la próxima tarea del backlog y escribe su prompt | `prompts/NN+1-*.md`, `runs/cola.txt` |

Tres cosas que hacen que el bucle no sea un lazo suelto:

- **El verificador no es quien implementó.** Quien escribió el código ya se
  convenció de que está bien. La verificación corre en sesión aparte y con
  `AGROCOM_TURNO_NOCHE=1`, así que no puede "arreglar" el test que la evalúa.
  Si rechaza, hay hasta dos vueltas de corrección antes de rendirse.
- **Las tareas críticas se implementan pero no se integran solas.** Lo que está
  en la lista de "qué no delegar sin revisión línea por línea" de `CLAUDE.md`
  se marca `critica=si` en el prompt: su PR se abre **en borrador**, que es
  precisamente el caso que `auto-merge.yml` deja pasar de largo. El trabajo
  mecánico queda hecho y la revisión humana empieza sobre algo que ya pasa la
  cascada.
- **El estado no vive en la conversación.** Vive en git y en `runs/`. Por eso
  el ciclo se puede matar en cualquier punto y retomar leyendo un archivo — que
  es exactamente lo que faltó la primera vez que se cerró la ventana en medio
  de una tarea. Con `--fondo` corre bajo `nohup`: cerrar la terminal ya no lo
  mata.

**Un modelo por fase.** El trabajo que decide algo —implementar, verificar,
corregir hallazgos, elegir la próxima tarea— corre en el modelo mediano; el
repetitivo —leer un log de CI en rojo, reproducirlo con `bin/verify`, arreglar
lo que rompió— en el más chico. Es el mismo criterio que
`.claude/agents/README.md` aplica a los subagentes. Sin esto, todas las
sesiones salían con el modelo por defecto de la cuenta y una noche entera de
vueltas se pagaba completa a ese precio. Se ajusta por tarea con `modelo=` en
el metadato del prompt, o por entorno con `AGROCOM_MODELO_PESADO` y
`AGROCOM_MODELO_LIVIANO`. Fable no se usa en ninguna fase.

**Qué se congela y qué no.** La sesión de verificación corre siempre con
`AGROCOM_TURNO_NOCHE=1`: no edita código, solo escribe su veredicto en `runs/`,
que no está congelado. La de corrección hereda el `turno-noche` del prompt de
la tarea — congelarla siempre parecía la opción segura y no lo era: una tarea
cuyo entregable **es** un test (una aduana, un gate) quedaba sin poder corregir
su propio archivo. Pasó en la tarea 04. La garantía que importa no se pierde,
porque quien juzga sigue siendo el verificador, que no puede tocar nada.

El backlog que consume está en [cola_tareas.md](cola_tareas.md): solo entra ahí
lo que tiene criterio de aceptación ejecutable, que es la regla que ordena todo
este documento.

## Lo que falta para un turno desatendido

En orden de dependencia:

1. **Gate de las invariantes que hoy solo se cumplen por disciplina.** Las
   invariantes 7 y 9 de `CLAUDE.md` (transiciones por el servicio de estados,
   bitácora antes/después) no tienen test que las verifique: un agente puede
   escribir un `estado = ...` suelto, o un modelo que muta sin dejar rastro, y
   pasar la cascada entera en verde. **Sigue siendo lo bloqueante**: sin ese
   gate, el verde de `bin/verify` no significa lo que parece — y el ciclo
   integra con ese verde. Encolado como tareas 04 y 05 en
   [cola_tareas.md](cola_tareas.md).

   Las invariantes 2 y 3 (no sobrescribir un registro validado, devengo solo al
   validar) vigilan tablas que todavía no existen; su gate se escribe en la
   misma tarea que cree ese dominio, no antes: una aduana sobre un dominio
   inexistente pasa siempre y simula una cobertura que no hay.

2. **Regresión visual.** Playwright está en `devDependencies` pero no hay
   `playwright.config`, ni suite E2E, ni capturas de referencia. Mientras no
   exista, ningún cambio del panel puede cerrarse sin que una persona mire la
   pantalla (ver `panel-design-ui`, "verificación visual"). Encolado como tarea
   06.

3. **Paralelismo con worktrees.** Hoy el ciclo corre una tarea por vez. Cada
   worktree necesitaría su propio proyecto compose, con puerto y base distintos
   (`docker compose -p`). Recién vale la pena cuando haya varias tareas
   independientes en cola al mismo tiempo — hoy la cola es una fila, no un
   abanico.

## Nota sobre el orden

Los puntos 1 y 2 pagan solos aunque nunca se llegue al turno noche: son deuda
que hay que saldar antes de tocar devengos y planilla. El 3 solo vale la pena
cuando el backlog tenga volumen mecánico y varias tareas independientes a la
vez — hoy la cola es una fila de tareas cargadas de decisión, y una fila la
corre bien un solo ciclo.

Lo que cambió respecto de la versión anterior de este documento: el backlog
legible por máquina, el runner de una sola tarea y el bucle de cola —que eran
los puntos 3, 4 y 5 de esta lista— ya existen: `cola_tareas.md`,
`bin/iteracion` y `bin/ciclo`.
