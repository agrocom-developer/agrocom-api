<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/validacion-sesiones etapas=4 -->

# Tarea 14 — HU-14: cola de validación de sesiones

Con la tarea 13 (HU-05) integrada, `ope_sesiones` ya llega a `cerrado`. Nadie
las aprueba todavía: no existe transición a `validado`, no existe el evento
`SesionValidada` (invariante 3 de `CLAUDE.md` — el devengo se genera **solo**
al validar, nunca al cerrar), y no existe la policy de la invariante 4
(validador ≠ piloto de esa sesión, a nivel persona, no de rol). Esta tarea
construye la cola del jefe de campo: aprobar o rechazar cada sesión cerrada.

**Es terreno nuevo.** Ningún archivo del repo implementa hoy el mecanismo de
"corrección, nunca edición" de la invariante 2 — confirmé con
`grep -r anula_a_id app/` que no existe una sola referencia. El rechazo de
esta HU es la primera vez que ese mecanismo se necesita de verdad. No hay un
patrón que copiar: vas a diseñarlo vos, con lo que la invariante 2 dice
literalmente, y documentar la decisión.

**Es crítica**: toca el servicio de estados y define quién puede validar qué
— exactamente lo que `CLAUDE.md` no delega sin revisión línea por línea. El
PR se abre en borrador.

## Qué hacer

Cargá el skill `verificacion`, y `seguridad-roles` para la policy. Leé
`app/Dominios/Operaciones/` (con lo que dejó la tarea 13) y
`app/Dominios/Seguridad/Contratos/IdentidadOperarioToken.php` (el contrato que
la tarea 12 creó para resolver la persona del token) antes de tocar nada —
para el panel necesitás el equivalente con el usuario del panel
(`AutorizacionPanelWeb` ya expone el usuario autenticado; resolvé su
`persona_id` desde ahí, sin que `Operaciones` importe `SecUser` directo).

1. **Transición validar**: agregá el estado `validado` a `EstadoSesion`, la
   transición `cerrado → validado` a `TransicionesSesion`, y `validar()` a
   `MaquinaEstadosSesion`. Al validar, disparás el evento de dominio
   `SesionValidada` — **idempotente** (invariante 3: `UNIQUE (sesion_id,
   persona_id)` es la tabla de devengos, que todavía no existe). El listener
   que genera el devengo real es HU-16 (tarea aparte, todavía no planificada
   con detalle) — dejá el evento disparándose sin oyente real (o con un
   oyente vacío si Laravel lo exige) y documentalo en `runs/14.md`: no
   inventes la lógica de devengo acá.
2. **Policy validador ≠ piloto**: quien valida no puede ser la persona
   (`per_personas.id`, no el rol) que pilotó esa sesión. Resolvé la persona
   del jefe autenticado igual que la tarea 12 resolvió la del operario del
   token, pero del lado panel. El jefe que también vuela no valida sus
   propias sesiones — sí puede validar las de otro piloto.
3. **Mecanismo de rechazo (invariante 2)**: el criterio de la HU dice
   literalmente *"rechazo con motivo genera corrección, nunca edición"* —
   nunca un `UPDATE` sobre la fila `cerrado`. Diseñá el mínimo que cumple
   esto: una fila nueva que referencia a la original por `anula_a_id`, con
   motivo y autor (columnas nuevas, migración nueva), dejando la original con
   sus columnas de negocio intactas — solo marcada de alguna forma como
   anulada (no un `estado` que sobreescriba lo que ya se registró). Documentá
   la decisión exacta en `runs/14.md` con el mismo nivel de detalle que la
   tarea 12: qué tabla, qué columnas, por qué. Esta es la pieza más abierta
   de la tarea — si al final de la etapa 2 no llegás a un diseño que te
   convenza, dejalo escrito como bloqueo en `runs/14.md` en vez de forzar
   algo que no cumple la invariante.
4. **Panel**: cola de validación — sesiones `cerrado` pendientes, con acción
   validar y acción rechazar (con motivo obligatorio). Gateada por permiso
   nuevo y por la policy: si el jefe autenticado es el piloto de esa sesión,
   la acción de validar esa fila puntual no está disponible (o responde 403
   si se fuerza).

## Cómo repartir las etapas

1. Estado `validado`, transición, evento `SesionValidada` (sin oyente real
   todavía), tests unitarios de la máquina de estados.
2. Mecanismo de corrección del rechazo: migración + caso de uso + tests que
   prueben que la fila original nunca recibe un `UPDATE` de sus columnas de
   negocio.
3. Policy validador≠piloto integrada al caso de uso de validar/rechazar +
   tests de la policy (jefe-piloto rechazado, jefe-no-piloto aceptado).
4. Pantalla de panel (cola, permiso, menú) + tests de panel, cascada verde.

## Qué NO hacer

- No implementes el listener real que genera devengos ni toques dinero — eso
  es HU-16, con su propia revisión crítica. El evento se dispara, nadie cobra
  todavía.
- No toques el tablero de HU-15 (aunque vaya a leer los mismos datos) — es
  una pantalla distinta, en otra tarea.
- No reabras la lógica de cierre de la tarea 13.
- Nada de `agrocom-field`/Flutter.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. Un jefe que no es el piloto de la sesión la valida: pasa `cerrado →
   validado`, dispara `SesionValidada` una sola vez aunque se reintente.
2. El piloto de esa misma sesión intenta validarla: rechazado (403 o
   equivalente), la sesión sigue `cerrado`.
3. Un jefe rechaza una sesión con motivo: la fila original no cambia sus
   columnas de negocio (assert explícito), se crea la fila de corrección con
   `anula_a_id`, motivo y autor.
4. Test de panel: la cola muestra solo sesiones `cerrado`; la acción de
   validar no está disponible (o da 403) cuando el jefe es el piloto de esa
   fila puntual.

## Cierre obligatorio de cada etapa

`runs/14.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/14.md` con lo hecho, el
diseño del mecanismo de corrección con su porqué, y qué falta para HU-16 (qué
espera encontrar del evento `SesionValidada`). Al llegar a `OK`,
`runs/14.pr.md`.

## Commits

Agrupados: transición y evento, mecanismo de corrección, policy, pantalla de
panel. Español, imperativo, el porqué antes que el qué. Sin trailer
`Co-Authored-By`.
