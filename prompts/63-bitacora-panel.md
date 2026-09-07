<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/bitacora-panel etapas=3 -->

# Tarea 63 — pantalla de bitácora: quién hizo qué, cuándo y en qué zona horaria

## Por qué esta tarea

La invariante 9 de `CLAUDE.md` ya se cumple del lado de la escritura: el
trait/observer de `app/Dominios/Compartido/` (ADR 0007) deja en
`plt_bitacoras` usuario, tabla, registro, acción y valores antes/después de
cada mutación relevante. Pero nadie puede verlo: no hay pantalla, no hay
permiso, no hay ítem de menú. El usuario lo pidió el 4/9/2026 con dos
precisiones:

- Quiere saber **quién hizo qué y cuándo**, desde el panel.
- El "cuándo" tiene que ser el **mismo instante en cualquier lugar del
  mundo**: guardado como instante absoluto, y mostrado en la zona horaria
  de quien mira, con horario de verano/invierno resuelto por la base IANA,
  nunca "las 9:00" a secas.

No es crítica: es lectura, más una columna nueva y una preferencia.

## Lo que ya existe

- `database/migrations/2026_08_31_100002_create_plt_bitacoras_table.php`:
  `user_id` (nullable, FK `sec_user`), `tabla`, `registro_id`, `accion`,
  `antes` jsonb, `despues` jsonb, `created_at` (`timestamp`, sin zona;
  `config/app.php` fija `timezone = UTC`, así que el valor guardado ES UTC).
- `app/Dominios/Compartido/Dominio/AccionBitacora.php` y el trait bajo
  `Compartido/Infraestructura/Eloquent/` — leé cómo resuelve `user_id`
  (¿guard `interno` solo? el portal usa guard `cliente` y también muta, al
  menos preferencias).
- `sec_user_preferencia` (`tema`, `idioma`): es el lugar natural de la zona
  horaria del usuario. Ya hay un endpoint `POST /panel/preferencias/tema` y
  su equivalente del portal para copiar el patrón.
- Módulo `Seguridad`: `UsuariosController` + `ListarUsuarios` son el modelo
  de "listado con filtros" del panel; la guía visual está en
  `docs/diseno/guia_pantalla_panel.md` (arquetipo Listado).

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui`,
`seguridad-roles` y `verificacion`.

1. **Instante absoluto + zona del actor.** Migración que agrega a
   `plt_bitacoras` la columna `zona_horaria` (string 64, IANA, ej.
   `America/La_Paz`, nullable para lo ya escrito). `created_at` sigue en UTC;
   no lo conviertas ni lo reescribas. El trait llena `zona_horaria` con la
   zona del actor en el momento de la mutación (ver punto 2); si no hay
   actor con sesión (API de campo, comando), queda `null` o la zona que el
   token del dispositivo declare si ya existe ese dato — no inventes una.
2. **Zona horaria del usuario.** Columna `zona_horaria` en
   `sec_user_preferencia` (IANA, nullable). Se fija por primera vez en el
   login: el formulario manda en un campo oculto
   `Intl.DateTimeFormat().resolvedOptions().timeZone` y el servidor la guarda
   si es un identificador IANA válido (`DateTimeZone::listIdentifiers()`) y
   la preferencia estaba vacía. Además, un selector en el mismo lugar donde
   hoy vive el cambio de tema (topbar/preferencias) para cambiarla a mano.
   Vale para el panel (`interno`) y para el portal (`cliente`).
3. **Pantalla `GET /panel/bitacora`**, permiso nuevo `seguridad.bitacora.ver`
   (solo `dueno` y `encargado_operaciones`), ítem de menú bajo Seguridad.
   Listado paginado, del más reciente al más viejo, con filtros por usuario,
   por entidad (`tabla`, con nombre legible desde `lang/`), por acción, por
   rango de fechas (interpretado en la zona del que mira) y por
   `registro_id` (para que una ficha pueda enlazar "ver historial" más
   adelante vía query string). Cada fila: instante en la zona del que mira
   con el offset visible (ej. `04/09/2026 09:15 (UTC−4)`), la zona en que
   ocurrió si es distinta (ej. "registrado en America/Asuncion"), usuario
   (nombre + username; "sistema" si `user_id` es null), entidad, acción, y
   un detalle desplegable con el diff campo por campo de `antes`/`despues`
   (solo los campos que cambiaron; los que no cambiaron, colapsados).
4. **Caso de uso `ListarBitacora`** en `Compartido` o en `Seguridad`
   (decidilo con `dominio-backend`: la tabla es de plataforma, la pantalla
   es de Seguridad; el caso de uso no puede importar modelos de otros
   módulos para resolver nombres legibles — usá `lang/` por nombre de tabla
   y el `SecUser` para el actor). Toda fecha que sale al Blade sale ya
   convertida a la zona del que mira, en un DTO; la vista no calcula zonas.
5. **Cobertura de escritura.** Test que recorre todos los modelos que usan
   el trait y confirma que una mutación desde el panel deja `user_id` y
   `zona_horaria`; y que una mutación desde el portal (guard `cliente`) deja
   el `user_id` del cliente, no null.

### Actualización del 7/9/2026 — qué cambió desde que se escribió este prompt

Este prompt quedó escrito y nunca corrió (`runs/63.estado` no existe). Sigue
vigente tal cual, con dos cosas que aparecieron después y hay que respetar:

6. **Los secretos de configuración NO se muestran en el diff.** La tarea 78
   crea `/panel/configuracion` (llaves de mapas, correo, tokens) y deja esos
   campos **excluidos** del `antes`/`despues` del observer — es una excepción
   declarada a la invariante 9, registrada en el ADR 0016 y en la
   especificación §14.1. Esta pantalla es donde esa exclusión se ve o se
   filtra: la fila tiene que mostrar quién cambió qué clave y cuándo, y
   **nunca el valor**. Si la 78 todavía no entró cuando corras esta tarea, la
   pantalla igual debe estar preparada para no renderizar un campo marcado
   como excluido, en vez de asumir que todo `antes`/`despues` es mostrable.
7. **Las entidades nuevas entran solas.** Campaña, equipos de trabajo,
   integrantes, recursos, cultivo, lote-campaña y estadías (tareas 69 a 75)
   extienden `ModeloDominio`, así que ya registran bitácora sin que esta
   tarea haga nada. Lo único que necesitan es su **nombre legible** en
   `lang/` para el filtro por entidad — sumalos ahí, no los omitas.

## Qué NO hacer

- No cambies `config/app.php → timezone`: UTC en el servidor es la base de
  todo esto. La conversión es solo de presentación.
- No conviertas `created_at` a `timestamptz` en esta tarea: el valor ya es
  UTC y los 48 `timestamps()` del esquema siguen el mismo criterio; una
  migración de tipo masiva es otra decisión.
- No permitas editar ni borrar filas de bitácora desde ninguna pantalla:
  ni ruta, ni caso de uso. Es solo lectura, por definición (ADR 0007).
- No agregues una segunda forma de registrar bitácora "manual" para la
  pantalla: si algo no queda registrado, es un bug del trait, y lo arreglás
  ahí.
- **No muestres el valor de un secreto de configuración**, ni siquiera
  truncado, ni "solo para el dueño". Si está en el DOM, está filtrado.

## Cómo repartir las etapas

- **Etapa 1**: migraciones (`plt_bitacoras.zona_horaria`,
  `sec_user_preferencia.zona_horaria`), trait llenando la zona, preferencia
  fijada en login + selector, tests de escritura y de validación IANA.
- **Etapa 2**: caso de uso `ListarBitacora` con filtros y DTO con fechas
  convertidas, permiso, ruta, controlador, pantalla con diff desplegable,
  ítem de menú.
- **Etapa 3**: spec visual `tests/Visual/bitacora.spec.ts` (claro/oscuro,
  con un fixture que deje al menos tres filas de tres entidades distintas y
  fecha fija), tests de filtros, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test: una edición de usuario hecha con sesión cuya preferencia es
  `America/La_Paz` deja `created_at` en UTC y `zona_horaria =
  'America/La_Paz'`; la misma fila, listada por un usuario con preferencia
  `Europe/Madrid` en julio, se muestra con hora `+2` respecto de UTC y en
  enero con `+1` (horario de verano/invierno resuelto por IANA, no por un
  offset fijo).
- Test: un rol sin `seguridad.bitacora.ver` recibe 403 y no ve el ítem.
- Test: el filtro por `registro_id` + `tabla` devuelve solo el historial de
  ese registro, y el diff muestra únicamente los campos que cambiaron.
- Ninguna ruta acepta `PUT`/`DELETE` sobre bitácora.

## Puede tocar

`app/Dominios/Compartido/**`, `app/Dominios/Seguridad/**`, `app/Dominios/Portal/**`
(solo la preferencia de zona), migraciones nuevas, `SeguridadSeeder`,
`SecMenuSeeder`, `lang/es/**`, `resources/**` (componentes del listado y
del diff), `tests/**`.

## Cierre obligatorio de cada etapa

`runs/63.estado`, `runs/63.md`, y al `OK` `runs/63.pr.md`. Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.
