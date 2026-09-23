<!-- ciclo: critica=si turno-noche=1 rama=feature/motor-notificaciones etapas=5 -->

# Tarea 141 — motor de notificaciones interno, con la primera cadena real

No depende de las tareas 135 a 140 (puede ir en cualquier momento). Leé
`docs/gestion/plan_dashboard_notificaciones_por_rol.md` §3, §4 y §7
completo antes de escribir una línea. Cargá los skills `dominio-backend`,
`arquitectura`, `panel-design-ui` y `verificacion`.

**Marcada crítica**: es un patrón arquitectónico nuevo (motor de eventos de
dominio transversal a todos los módulos), no un CRUD. Se integra igual
(críticos se mergean con CI verde, revisión posterior), pero agregá tu
entrada a `runs/revision-pendiente.txt` al cerrar.

## El estado actual (ya diagnosticado, no lo reinvestigues)

`resources/views/components/molecules/notifications-menu.blade.php` pinta
notificaciones sin ninguna acción — cada `<li>` es texto plano. Su único
emisor hoy es `CascaraPanel::notificaciones()`
(`.../Presentacion/CascaraPanel.php:169-181`), que lee
`LecturaPanelOperaciones::alertasRecientes()` sobre la tabla `ope_alertas`
(4 tipos técnicos de equipo — batería caliente, dron sospechoso,
condiciones forzadas, suma excedida). No hay ninguna tabla ni módulo de
notificación de flujo de negocio. El único evento de dominio real hoy,
`SesionValidada`, se escucha con una closure inline en
`FinanzasServiceProvider::boot()` (línea 54) — no hay clase `Listener`
separada ni infraestructura de entrega genérica.

## Qué hacer

1. **Escribí primero el ADR** (`docs/decisiones/00XX-...md`, seguí el
   formato de los existentes): decide el módulo nuevo (`Notificaciones` es
   el candidato natural — no escribe tablas de ningún otro módulo,
   `CLAUDE.md`), el modelo de datos (tabla propia, soft delete + auditoría
   como cualquier modelo de dominio — invariantes 8 y 9, sin excepción
   salvo que la justifiques en el propio ADR), y **cómo se decide el
   destinatario de cada notificación**: ¿por rol (cualquier cuenta que
   tenga ese rol asignado, aunque no sea el activo ahora mismo — atención al
   invariante 10, que dice que los permisos efectivos son del rol activo,
   no de la unión; una notificación NO es un permiso, así que puede llegar
   igual aunque el rol no esté activo, pero la tarea tiene que decidirlo
   explícito, no por default) o por persona puntual. Documentá la decisión
   con su porqué, no solo el mecanismo.
2. **Generalizá el patrón de evento de dominio**: seguí la forma de
   `SesionValidada` (evento en `Contratos/Eventos/` del módulo que lo
   emite), pero el listener que entrega la notificación vive en el módulo
   `Notificaciones`, no en un closure inline de cada `ServiceProvider`
   ajeno — ahí sí conviene una clase `Listener` real, porque un solo listener
   genérico ("crear notificación para el destinatario que resuelva este
   evento") sirve para todos los eventos, a diferencia del caso de
   `SesionValidada` que es un cálculo de negocio propio de Finanzas.
3. **Conectá `notifications-menu.blade.php` a datos reales con acción**:
   cada notificación necesita destino al click (a la URL del recurso que la
   originó — la orden, el trabajo, el contrato). No rompas los dos
   llamadores existentes (topbar de escritorio y `mobile-topbar`, ver el
   comentario del propio archivo). Decidí (y documentá en `runs/141.md`) si
   `ope_alertas` pasa a ser un tipo más del motor nuevo o sigue emitiendo
   por su cuenta y el popover mezcla las dos fuentes — cualquiera de las dos
   es válida, pero no la dejes ambigua.
4. **Primera cadena real, de punta a punta** (no simulada): Contrato
   creado → notifica a los usuarios con rol `encargado_operaciones`; Orden
   de Aplicación/Orden de Trabajo creada → notifica al equipo asignado;
   Trabajo marcado como realizado → notifica a Jefe de Campo, Encargado de
   Operaciones y, opcionalmente (a decidir por vos, documentado), al Dueño.
   Si el evento de dominio para alguno de estos tres momentos no existe
   todavía (`SesionValidada` es el único confirmado), creá el que falte en
   el módulo dueño de esa transición, sin tocar su máquina de estados —
   emitilo, no la cambies.

## Qué NO hacer

No conviertas esto en un canal de notificaciones externas (push, email,
SMS) — es interno al panel, adentro de la campana. No dupliques ni
reemplaces `ope_alertas` sin documentar la decisión (punto 3). No le des a
`Notificaciones` acceso de escritura a tablas de otros módulos — lee lo que
necesita mostrar (título, link) del propio payload del evento, nunca con una
consulta cruzada a la tabla ajena.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test de idempotencia: el mismo evento de dominio no genera notificaciones
  duplicadas si el listener corre dos veces (mismo criterio que la
  idempotencia por `uuid_cliente`, invariante 1, aplicado acá al evento).
- Test de que un usuario ve solo las notificaciones que le corresponden a
  él (por rol o por persona, según lo que decidiste en el ADR) — nunca las
  de otra cuenta.
- Playwright (`runs/141-navegador.cjs`): crear un contrato dispara la
  notificación al encargado de operaciones demo, y el click en la
  notificación lleva al contrato.

## Cierre obligatorio de cada etapa

`runs/141.estado`, `runs/141.md`, `runs/revision-pendiente.txt` actualizado,
y al `OK` `runs/141.pr.md`. Commits por función, en español, imperativo, sin
`Co-Authored-By`.
