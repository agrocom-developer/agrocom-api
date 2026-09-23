<!-- ciclo: critica=si turno-noche=1 rama=feature/ver-como-usuario etapas=4 -->

# Tarea 140 — el administrador puede ver el panel/portal como otro usuario

Requiere la 135 integrada (el agrupamiento de tabs por rol tiene que existir
para que "ver como" tenga algo consistente que mostrar). Leé
`docs/gestion/plan_dashboard_notificaciones_por_rol.md` §2 y §6 completo, y
el ADR 0004 (modelo de seguridad `sec_*` multirol) y la sección del portal
en `docs/especificacion/especificacion_funcional_tecnica.md` antes de
escribir una línea. Cargá los skills `seguridad-roles`, `panel-design-ui` y
`verificacion`.

**Marcada crítica**: toca el scoping del portal del cliente, invariante 5 de
`CLAUDE.md` — "la única cosa que nunca se delega sin revisión línea por
línea". Se integra igual (política vigente: los críticos se mergean con CI
verde, la revisión es posterior — ver `CLAUDE.md` "Qué no delegar..."), pero
agregá tu entrada a `runs/revision-pendiente.txt` al cerrar, sin esperar a
que alguien la encuentre.

## Alcance (decisión del dueño, 23/9/2026): completo desde el principio

Cubre los dos casos, no solo uno:

1. **Ver como otro usuario interno** (cualquiera de los 6 roles de
   `sec_roles`, incluido otro `admin_plataforma`): mismo guard `interno`,
   cambia a qué usuario/rol activo se evalúa el dashboard y las pantallas.
2. **Ver como un usuario del portal cliente**: guard `cliente` separado
   (`config/auth.php:44-46`, provider `usuarios_cliente`, sin fila en
   `sec_roles`) — esto es una impersonación real, no un cambio de rol activo
   dentro de la misma sesión.

## Qué hacer

1. Diseñá el mecanismo de "vista como" de forma que **nunca sea indistinguible
   de una sesión real de esa persona**: un flag de sesión explícito
   (`viendo_como_usuario_id` / `viendo_como_guard`, a tu nomenclatura) que la
   capa de presentación usa para renderizar con el scoping de ESE usuario,
   conservando la identidad real del administrador para poder volver. No
   reemplaces la sesión de autenticación real del admin por un login real
   como el otro usuario — si lo hacés así igual, la salida ("Volver a mi
   vista") tiene que restaurar exactamente la sesión de origen, sin dejar
   ningún estado a mitad de camino.
2. **Modo estrictamente de lectura mientras se ve como otro usuario**:
   ninguna acción que mute algo (crear, editar, transición de estado,
   eliminar) queda disponible — ni siquiera las que el usuario real tendría
   permiso de hacer. Un indicador visible y persistente en toda la interfaz
   ("Viendo como: Fulano — Rol/Cliente") mientras dure.
3. **Bitácora de auditoría en la entrada Y en la salida** (invariante 9): quién iba
   de verdad, a quién/qué vio, cuándo, y cuándo volvió — no solo el evento de
   entrar.
4. **El portal del cliente sigue consultando desde el `contrato` del usuario
   observado** (invariante 5) — la impersonación tiene que pasar por el
   mismo scoping real del guard `cliente`, nunca por un `where` agregado
   aparte para el caso "admin mirando".
5. Quién puede usar "ver como": nuevo permiso propio (p. ej.
   `seguridad.usuario.ver_como`), no implícito en ningún permiso existente.

## Qué NO hacer

No uses esto como mecanismo real de soporte técnico que ejecuta acciones en
nombre del usuario (eso sería suplantación con escritura, un problema
distinto y no pedido). No lo actives por defecto para ningún rol que no sea
`admin_plataforma`. No agregues un tercer guard nuevo — reusá `interno` y
`cliente` tal como existen.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test de que ninguna ruta de escritura responde 200/redirect mientras hay
  una "vista como" activa (debe rechazar, no solo ocultar el botón en la
  UI).
- Test de que la bitácora registra entrada y salida de cada "vista como".
- Test de que el portal, visto como cliente X, jamás devuelve datos de un
  contrato que no sea de X (mismo criterio del invariante 5, aplicado al
  caso nuevo).
- Playwright (`runs/140-navegador.cjs`): admin entra como jefe de campo, ve
  su dashboard de la tarea 138, vuelve a su propia vista; admin entra como
  un cliente del portal, ve solo sus contratos, vuelve.

## Cierre obligatorio de cada etapa

`runs/140.estado`, `runs/140.md`, `runs/revision-pendiente.txt` actualizado,
y al `OK` `runs/140.pr.md`. Commits por función, en español, imperativo, sin
`Co-Authored-By`.
