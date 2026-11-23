---
name: ciclo-segundo-plano
description: Cómo ejecutar desarrollo desatendido con bin/ciclo y bin/iteracion — escribir prompts con metadatos, la cola, runs/, estados, el LaunchAgent que lo mantiene vivo y los hooks de turno noche que congelan zonas. Usar para lanzar, vigilar, detener o preparar tareas en segundo plano.
---

# Ciclo en segundo plano — `bin/ciclo`

Detalle del porqué en `docs/gestion/automatizacion_desarrollo.md`. Acá, la operación.

## Fases por tarea

implementar, verificar (solo si `critica=si`), abrir PR, esperar CI, planificar la siguiente. Cada fase corre en su propia sesión de Claude Code con contexto limpio. Una tarea es una HU o TE completa y termina en un PR contra `develop`. El estado vive en git y en `runs/`, no en ninguna conversación: el ciclo se puede matar y retomar.

`bin/iteracion` corre una cola fija de prompts escritos a mano; `bin/ciclo` corre una cola que se extiende sola (la planificación lee el backlog y escribe el prompt siguiente).

## Comandos

```
bin/ciclo --fondo           # se desprende de la terminal
bin/ciclo                   # en primer plano
bin/ciclo --estado          # fase, cola, rama, parada, tareas trabadas, últimas líneas del log
bin/ciclo --detener         # parada ordenada: termina la fase en curso y no toma otra
bin/ciclo --reanudar        # levanta la bandera de parada
bin/ciclo --vueltas 2       # tope de tareas de esta corrida
bin/ciclo --planificar 08   # reescribe el prompt siguiente a la 08 si esa vuelta se cortó
bin/ciclo --reconciliar     # corrige runs/*.estado que git desmiente (--simular para ver)
```

## Escribir un prompt

Archivo `prompts/NN-nombre-corto.md`. La primera línea declara los metadatos:

```
<!-- ciclo: critica=no turno-noche=1 rama=feature/nombre-corto etapas=3 descongela=tests -->
```

| Clave | Significado |
|---|---|
| `rama` | Rama que crea el ciclo desde `develop`; la sesión no la elige |
| `critica` | `si` activa la sesión verificadora independiente (motor de sync, dinero, estados) |
| `turno-noche` | `1` activa los congelamientos del hook |
| `etapas` | Tope de sesiones encadenadas sobre la misma rama |
| `descongela` | Zonas editables: `tests`, `decisiones`, `claude`, `github` (separadas por coma) |
| `modelo` | Modelo de la fase de implementación |

El cuerpo trae: objetivo, alcance, agentes a delegar (según `orquestador`) y **un criterio de aceptación que sea un comando con código de salida 0 o 1** (`bin/verify` más las pruebas específicas). Sin criterio ejecutable, la tarea la revisa una persona. Las plantillas están en `prompts/plantillas/`.

## La cola y `runs/`

- `runs/cola.txt`: ids de tarea pendientes, uno por línea, en orden.
- `runs/NN.estado`: `OK`, `PARCIAL`, `CERRADA`, `OMITIDA`, `BLOQUEADA`, `RECHAZADA`, `AGOTADA`, `INCOMPLETA`. Los cuatro últimos necesitan una persona.
- `runs/NN.log`, `NN-verify.log`, `NN.pr.md`, `NN.veredicto`: evidencia de cada fase.
- `runs/ciclo.log`, `runs/ciclo.estado`, `runs/ciclo.pid` y `runs/DETENER` (bandera de parada).
`runs/` no se versiona.

Una tarea trabada no frena el bucle: queda marcada y el ciclo sigue. Se detiene por parada pedida, cola vacía o varias tareas seguidas sin integrarse.

## Que sobreviva a un reinicio: LaunchAgent

```
bin/ciclo-servicio --instalar     # instala y carga com.agrocom.ciclo
bin/ciclo-servicio --estado
bin/ciclo-servicio --desinstalar
```

Es un proceso separado del de `agrocom-field` (`com.agrocom.ciclo-field`): cada uno con su árbol, su PID y su `runs/`. Respeta `runs/DETENER`.

## Hooks de turno noche

`.claude/hooks/guardarrail-bash.sh` bloquea siempre: push a `master` o `develop`, `--force`, `reset --hard`, `clean -fdx`, `branch -D` y lectura de credenciales. `guardarrail-archivos.sh`, con `AGROCOM_TURNO_NOCHE=1`, congela `tests/`, `docs/decisiones/`, `.claude/` y `.github/` salvo que el prompt declare `descongela=` para esa zona; `CLAUDE.md` nunca se abre. Se prueban con `.claude/hooks/prueba-guardarrail.sh`. Si un comando legítimo cae en falso positivo, se reformula; no se esquiva el hook.

## Delegación dentro del ciclo

Las sesiones headless usan los agentes de `.claude/agents/` con las cadenas que describe `orquestador`. Una tarea de panel incluye a `qa-visual` en su criterio; una con borrado de datos, a `migracion-esquema`; una que toca autorización o archivos, a `confianza-seguridad`.

## Antes de dejar la máquina sola

1. `bin/verify` en verde sobre `develop`.
2. Cola escrita y prompts con criterio ejecutable.
3. Sin trabajo sin commitear en el árbol del ciclo.
4. `bin/ciclo --fondo` (o servicio instalado) y `bin/ciclo --estado` para confirmar.
