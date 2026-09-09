# Automatización del desarrollo

**Última actualización: 1/9/2026.** Este documento describe qué parte del ciclo de
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

**El squash tiene una consecuencia que hay que tener presente.** Los commits de
la rama desaparecen dentro de uno solo, así que una rama que siga viva después
de que su PR se mergeó ya no tiene correspondencia con `develop`: cualquier PR
nuevo desde esa misma rama nace en conflicto. Y GitHub no dispara ningún
workflow sobre un PR que no puede fusionar, con lo cual **CI no queda en rojo:
no existe**. Es un fallo mudo, y el ciclo lo trataba como "CI todavía
pendiente".

Pasó el 8/9/2026 con la tarea 69: la sesión abrió el PR #128 por su cuenta,
`auto-merge` lo integró con squash, y el PR #129 que abrió después el ciclo
—con las correcciones del verificador— quedó trabado sin un solo check. De
paso, esa rama había salido de `develop` antes del PR #127 y mergearla habría
borrado `prompts/81-ficha-desempeno.md` entero.

Ahora hay dos compuertas:

- **Antes de abrir el PR** (`poner_al_dia_con_develop`), la rama se rebasa
  sobre `develop` si quedó atrás. El rebase limpio se hace solo; el que choca
  deja una alerta y una rama `respaldo/*`.
- **Durante la espera de CI**, un PR sin un solo check se mira por su merge
  ref, no por sus checks: si está en conflicto se corta en el acto, y si a los
  tres minutos sigue sin checks se corta también. Los dos casos escriben en
  `runs/alertas.log` además de la bitácora.

El tope de espera bajó de 45 a 15 minutos. CI mide 200-277 s en las últimas
veinte corridas; 45 min no era holgura, era la ventana en la que un fallo mudo
podía esconderse.

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
  `.claude/` y `.github/`, **por zona**: cada tarea declara en su prompt cuáles
  necesita (`descongela=tests`, `decisiones`, `claude`, `github`) y las demás
  siguen cerradas. Sin esa granularidad, una tarea cuyo entregable es un test
  tenía que apagar el turno noche entero, y quedaba habilitada a reescribir
  también los ADRs y los agentes. `CLAUDE.md` no se abre con ninguna zona: las
  invariantes son del usuario, no del turno.

Los hooks tienen su propia prueba: `.claude/hooks/prueba-guardarrail.sh`, 22
casos que se corren a mano. Existe porque el guardarraíl falló en silencio una
vez: la excepción de "esto es solo una búsqueda" se evaluaba sobre el comando
entero, así que un `echo` en cualquier línea desactivaba **todas** las reglas
—push a `develop`, `reset --hard`, `migrate:fresh` pasaban sin que el hook
dijera nada. Un guardarraíl que se puede apagar sin querer no es un guardarraíl,
y este es el único que queda de pie cuando el ciclo corre de noche.

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
bin/ciclo --reconciliar  # corrige los runs/*.estado que git desmiente (--simular para verlo sin aplicar)
```

Cinco fases por tarea, cada una en su **propia sesión** con contexto limpio:

| Fase | Qué hace | Dónde queda el resultado |
|---|---|---|
| implementar | Encadena hasta `etapas=` sesiones sobre la misma rama hasta cerrar la HU; cada una commitea agrupado por función y corre la cascada | `runs/NN.estado`, `runs/NN.md` |
| verificar | Sesión independiente, con los tests congelados, que decide si se puede integrar | `runs/NN.veredicto` |
| PR | Abre el PR con el título y cuerpo que dejó la tarea | `runs/NN.pr` |
| esperar CI | Sondea hasta que `auto-merge` integra; corrige si queda en rojo y corta rápido si el PR está en conflicto o si CI no arrancó | bitácora, `runs/alertas.log` |
| planificar | Elige la próxima tarea del backlog y escribe su prompt | `prompts/NN+1-*.md`, `runs/cola.txt` |

**Una tarea = una HU o TE completa = un PR.** La primera versión del ciclo hacía
una sesión = una tarea = un PR, y como una HU del plan de sprints no entra en
una sesión, el planificador la partía hasta que cupiera. El resultado fue un
historial de PRs de un commit, donde ningún PR se correspondía con nada del
plan. Ahora el tamaño de la sesión no manda: la fase de implementación encadena
sesiones sobre la **misma rama**, cada una retomando lo que dejó la anterior.
La sesión declara `PARCIAL` mientras la historia siga abierta y `OK` recién
cuando está entera con todos sus criterios de aceptación cubiertos — y el PR se
abre al `OK`, con todos los commits adentro.

Tres piezas más que hacen que eso funcione sin supervisión:

- **La rama la crea el ciclo, no la sesión**, desde el metadato `rama=` del
  prompt (`feature/` + 2–3 palabras de la función del proyecto). Cuando la
  elegía la sesión, cada reintento inventaba una distinta y el trabajo de la
  vuelta anterior quedaba colgando donde nadie volvía a mirarlo.
- **El prompt de la tarea siguiente viaja en la rama de esa misma tarea.** La
  planificación lo deja escrito y sin commitear; lo commitea la vuelta siguiente
  como primer commit de su rama. Antes entraba por un PR propio de una sola
  línea (`chore/cola-NN`), uno por vuelta.
- **Una HU que se queda sin etapas no se tira ni se mergea a medias**: el ciclo
  abre su PR en borrador y marca la tarea `INCOMPLETA`. Lo mismo con una que el
  verificador rechazó tras sus vueltas de corrección. El trabajo hecho queda a la
  vista y la decisión de seguirlo o cerrarlo es de una persona.
- **Una tarea trabada no corta el bucle.** Queda marcada en `runs/NN.estado`
  —`BLOQUEADA`, `RECHAZADA`, `AGOTADA` o `INCOMPLETA`, y ya no se vuelve a
  tomar—, el ciclo planifica igual y sigue con la próxima. Lo único que lo
  detiene es una parada pedida, que se acabe la cola, o tres tareas seguidas sin
  integrar, que ya no es una tarea difícil sino algo sistemático.
  `bin/ciclo --estado` lista lo trabado.

  **Lo trabado se cierra seguido por otra vía y el archivo no se entera.** Una
  tarea queda `AGOTADA` porque su sesión se cortó sin declarar estado, y media
  hora después se integra igual: por un reintento, por una tarea "retomar" que
  la continúa (40→42, 41→43) o a mano. `runs/NN.estado` guarda lo que declaró
  la sesión, no lo que terminó pasando, así que sigue diciendo `AGOTADA` para
  siempre — el 8/9/2026 había cinco así, todas integradas desde el 3/9.
  `bin/ciclo --reconciliar` compara contra la única fuente de verdad, el PR
  mergeado (por la rama del prompt, o por el commit en `develop` que nombra la
  tarea), corrige las que puede probar y deja el rastro en
  `runs/reconciliacion-<fecha>.md`. Sin evidencia no toca nada, y `BLOQUEADA`
  nunca: eso no es un desfase, es una pregunta esperando respuesta. **No corre
  solo** — cambiar un estado sin que nadie mire es justo lo que haría que una
  tarea sin hacer pase por hecha; el ciclo se limita a avisar al cerrar.

  Es lo que permite dejarlo corriendo mientras se mira el proyecto en paralelo:
  el ciclo va a chocar seguido con tareas que no le corresponden —el spike del
  RC con el equipo en mano, lo que vive en `agrocom-field`, la reunión de cierre
  de la especificación— y frenar el turno entero por eso dejaría el backlog
  parado por una fila que nunca iba a poder tomar. La planificación las saltea
  anotándolas en la sección "Fuera del ciclo automático" de
  [cola_tareas.md](cola_tareas.md), y solo escribe `runs/DETENER` si **ninguna**
  pendiente de los seis sprints califica.
- **Terminar un sprint no termina el trabajo.** Cerrada la última HU pendiente
  de un sprint, la planificación sigue con la primera del siguiente. El plan
  tiene seis y el ciclo los recorre en orden.

Tres cosas que hacen que el bucle no sea un lazo suelto:

- **El verificador no es quien implementó.** Quien escribió el código ya se
  convenció de que está bien. La verificación corre en sesión aparte y con
  `AGROCOM_TURNO_NOCHE=1`, así que no puede "arreglar" el test que la evalúa.
  Si rechaza, hay hasta dos vueltas de corrección antes de rendirse.
- **Las tareas críticas se integran como cualquier otra, y se revisan después.**
  Lo que está en la lista de "qué no delegar sin revisión línea por línea" de
  `CLAUDE.md` se marca `critica=si` en el prompt. Eso hace dos cosas: le pone
  la sesión de verificación independiente (las no críticas ya no la pagan) y
  anota el PR en `runs/revision-pendiente.txt`, que es la lista que una persona
  revisa **sobre `develop`**, con el cambio ya adentro.

  **Ninguna sesión abre su PR en borrador por ser crítica.** Retenerlos fue la
  política hasta el 1/9/2026 y salió cara: el PR #46 (motor de sync) quedó
  esperando revisión y, como toda rama nueva sale de `develop`, bloqueó doce HU
  de los sprints 2 a 5 hasta que el ciclo se quedó sin trabajo y se detuvo solo.
  Un cambio crítico sin revisar es un riesgo acotado y visible; una rama que no
  entra bloquea todo lo que viene detrás.

  Este párrafo decía lo contrario hasta el 2/9/2026, y las sesiones de
  implementación lo leyeron y abrieron sus PR en borrador por su cuenta —
  `fase_pr` los encontraba ya creados y no los tocaba. Así quedaron retenidos
  los PR #59 (HU-08) y #62 (HU-17) en el turno de esa noche, y con ellos la
  tarea 25, que esperaba el acta. Si cambiás la política del borrador, cambiala
  también acá: `bin/ciclo` y `CLAUDE.md` no alcanzan.
- **El estado no vive en la conversación.** Vive en git y en `runs/`. Por eso
  el ciclo se puede matar en cualquier punto y retomar leyendo un archivo — que
  es exactamente lo que faltó la primera vez que se cerró la ventana en medio
  de una tarea. Con `--fondo` corre bajo `nohup`: cerrar la terminal ya no lo
  mata. `bin/ciclo --estado` muestra además en qué rama está parado y si hay
  una planificación escrita esperando a que su tarea la commitee.
- **El ciclo nunca deja la rama sucia.** Si una sesión termina sin cerrar la
  etapa (sin estado, agotada, matada), lo que dejó sin commitear se commitea en
  la rama de la tarea (`guardar_avance`) antes de seguir. Sin eso, la tarea
  siguiente no puede arrancar y la planificación no puede volver a `develop`:
  así se encadenaron tres fallos el 3/9/2026 —la 54 agotada dejó cuatro
  archivos sueltos, la 32 abortó dos veces sin correr una sola sesión— y el
  ciclo se cortó con la cola llena. Un working tree sucio que NO es del ciclo
  sigue cortando el bucle (es trabajo humano en curso), pero con ese motivo
  explícito y sin contar como tarea trabada.
- **Las sesiones esperan a `bin/verify` con llamadas Bash cortas, no con
  notificaciones.** En la máquina Windows la etapa de Playwright tarda ~20 min,
  más que el tope de 10 min de una llamada Bash, así que la sesión lo lanza en
  segundo plano. Y una sesión headless que pasa 10 minutos sin llamar a una
  herramienta se cierra sola: el 3/9/2026 dos etapas seguidas dejaron un
  `Monitor`, programaron un `ScheduleWakeup` a 20 min, escribieron "espero la
  notificación" y a los 10 minutos exactos el ciclo las vio terminar sin
  estado. Por eso `Monitor` y `ScheduleWakeup` van en `--disallowedTools`, y
  el pie que cada etapa recibe da la receta que sí funciona: `nohup
  ./bin/verify` una vez, y después `timeout 540 tail --pid=... -f /dev/null`
  en llamadas sucesivas hasta que el log termine. El tope de tareas seguidas
  sin integrar se ajusta con `AGROCOM_TAREAS_TRABADAS` (por defecto 3).

**Un modelo por fase.** El trabajo que decide algo —implementar, verificar,
corregir hallazgos, elegir la próxima tarea— corre en el modelo mediano; el
repetitivo —leer un log de CI en rojo, reproducirlo con `bin/verify`, arreglar
lo que rompió— en el más chico. Es el mismo criterio que
`.claude/agents/README.md` aplica a los subagentes. Sin esto, todas las
sesiones salían con el modelo por defecto de la cuenta y una noche entera de
vueltas se pagaba completa a ese precio. Se ajusta por tarea con `modelo=` en
el metadato del prompt, o por entorno con `AGROCOM_MODELO_PESADO` y
`AGROCOM_MODELO_LIVIANO`. Fable no se usa en ninguna fase.

**Qué se congela y qué no.** La sesión de verificación corre siempre congelada
y sin ninguna zona abierta: no edita código, solo escribe su veredicto en
`runs/`. La de implementación y la de corrección comparten exactamente las
zonas que el prompt de la tarea declaró — ni una más. La garantía que importa
no se pierde, porque quien juzga sigue siendo el verificador, que no puede
tocar nada.

El backlog que consume está en [cola_tareas.md](cola_tareas.md): solo entra ahí
lo que tiene criterio de aceptación ejecutable, que es la regla que ordena todo
este documento.

### 6. La limpieza de ramas: `bin/limpiar-ramas`

Con `--squash` en el auto-merge, los commits de una rama integrada no quedan
como ancestros de `develop` — solo su contenido. Así que `git branch -d` no
reconoce como integrada ninguna rama que sí lo está, y la lista crece hasta que
nadie sabe qué está vivo. `bin/limpiar-ramas` decide por evidencia: PR mergeado
en GitHub, o rama ancestro de otra cuyo PR sí entró, o contenido idéntico al de
`develop`. Lo que no cumple ninguna de las tres se reporta con su diff y **no se
toca**.

**Se corre a mano.** Sin `--ejecutar` solo informa, y `bin/ciclo` no lo invoca:
qué historia se descarta no es una decisión que deba tomar un bucle desatendido.
El momento natural es después de un tramo largo de avance, cuando la lista ya
creció.

### 7. La persistencia: `bin/ciclo-servicio`

`bin/ciclo --fondo` sobrevive a cerrar la terminal —lo hace `nohup`—, pero no a
que la Mac se reinicie. El 8/9/2026 se vio de las dos formas en el mismo día:
una actualización de VS Code se llevó puesta la corrida que colgaba de su
terminal, y nadie se enteró hasta que alguien fue a mirar. Lo que se pierde en un
reinicio no es una sesión, es el turno entero, y en silencio: no queda ni un
proceso al que preguntarle qué pasó.

`bin/ciclo-servicio --instalar` lo deja como LaunchAgent (`com.agrocom.ciclo`),
que arranca solo al iniciar sesión. Tres decisiones que importan:

- **La copia que ejecuta launchd vive fuera del repo** (`~/.local/bin/agrocom-ciclo`).
  Si el servicio apuntara al script dentro del árbol, dejaría de existir cada
  vez que el ciclo se parara en una rama anterior a ese commit —el estado normal
  mientras trabaja una tarea— y el arranque fallaría sin motivo visible.
- **No arranca un segundo ciclo sobre el mismo árbol de trabajo.** Si
  `runs/ciclo.pid` apunta a un proceso vivo, sale sin hacer nada. Dos ciclos
  compartiendo working tree se pisan los `git checkout` mutuamente.
- **El freno de mano sobrevive al reinicio.** Con `runs/DETENER` puesto no
  arranca, y sale con 0 — el plist solo relanza cuando la salida *no* es 0, así
  que una parada pedida no se convierte en un bucle de arranques cada cinco
  minutos.

Lo que **no** cubre, y conviene saberlo: un LaunchAgent de usuario carga al
iniciar sesión, no al encender la máquina, así que un reinicio que quede en la
pantalla de login no lo levanta; y si la Mac se duerme, nadie corre nada hasta
que despierte. Para un turno noche real, la sesión iniciada y el equipo sin
suspensión son parte del arreglo.

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
