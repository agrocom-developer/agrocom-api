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
| Pest (159 tests, SQLite en memoria) | contenedor `app` |
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

## Lo que falta para un turno desatendido

En orden de dependencia — cada uno necesita el anterior:

1. **Gate de las invariantes que hoy solo se cumplen por disciplina.** Las
   invariantes 2, 3, 7 y 9 de `CLAUDE.md` (no sobrescribir validados, devengo solo
   al validar, transiciones por servicio de estados, bitácora antes/después) no
   tienen test que las verifique. Un agente puede escribir un `estado = ...` suelto
   y pasar la cascada entera en verde. **Esto es bloqueante**: sin ese gate, el
   verde de `bin/verify` no significa lo que parece.
2. **Regresión visual.** Playwright está en `devDependencies` pero no hay
   `playwright.config` ni suite E2E ni capturas de referencia. Mientras no exista,
   ningún cambio del panel puede cerrarse sin que una persona mire la pantalla
   (ver `panel-design-ui`, "verificación visual").
3. **Backlog legible por máquina.** `docs/gestion/plan_sprints.md` tiene las HU y TE
   con sus criterios de aceptación en prosa. Para una cola automática cada tarea
   necesita, además: el comando que la acepta, los archivos que puede tocar, y un
   máximo de intentos.
4. **El runner de una sola tarea**, corrido a mano hasta que salga limpio tres
   veces seguidas.
5. **Cron y bucle de cola.**
6. **Paralelismo con worktrees.** Cada worktree necesita su propio proyecto compose
   (puerto y base distintos) — `docker compose -p`.

## Nota sobre el orden

Los puntos 1 y 2 pagan solos aunque nunca se llegue al turno noche: son deuda que
hay que saldar antes de tocar devengos y planilla. Los puntos 3 a 6 solo valen la
pena cuando el backlog tenga volumen mecánico — hoy tiene pocas tareas y muy
cargadas de decisión.
