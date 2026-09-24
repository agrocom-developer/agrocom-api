# ADR 0025 — Motor de notificaciones interno: un módulo, un evento por hecho, un aviso por cuenta

**Fecha:** 2026-09-23 · **Estado:** aceptado · **Decide:** el desarrollador, a partir del pedido del dueño del 22–23/9/2026 (plan `docs/gestion/plan_dashboard_notificaciones_por_rol.md`); tarea 141, marcada crítica.

## Contexto

La campana del panel (`molecules/notifications-menu`) pintaba avisos sin ninguna acción: cada `<li>` era texto plano y su única fuente eran las alertas técnicas de equipo de `ope_alertas` (batería caliente, dron sospechoso, condiciones forzadas, suma excedida), gateadas por `operaciones.alerta.ver`. No existía ninguna tabla ni módulo de aviso de **flujo de negocio**: nadie se enteraba de que se había cargado un contrato, de que se armó una orden de trabajo para su cuadrilla o de que un trabajo se cerró en campo, salvo entrando a mirar.

El único evento de dominio con oyente real era `SesionValidada`, escuchado con un closure dentro de `FinanzasServiceProvider::boot()`. Ese patrón sirve para un cálculo de negocio propio de un módulo (el devengo es de Finanzas), pero no para "avisar a alguien", que es transversal: si cada módulo escribiera su propio closure y su propia tabla, habría tantos mecanismos de aviso como módulos.

## Decisión

1. **Módulo nuevo `Notificaciones`, prefijo de tabla `ntf_`** (ADR 0011: el prefijo se registra antes de la primera migración; este ADR lo asigna). Escribe solo `ntf_notificaciones`; no escribe ninguna tabla ajena ni lee ninguna: lo que necesita mostrar viaja en el payload del evento, y a quién avisar lo pregunta por contratos (`Seguridad\Contratos\LecturaUsuariosPorRol`, `LecturaUsuarioDePersona`; `Personal\Contratos\LecturaEquipoTrabajo`). La campana lo lee por `Notificaciones\Contratos\LecturaNotificaciones`.

2. **Una fila por cuenta destinataria, creada al emitir el evento** (`ntf_notificaciones.usuario_id → sec_user.id`). El estado de lectura es de cada cuenta, así que el aviso se «reparte» al momento del hecho; no se calcula al mirar. Columnas de negocio: `tipo` (catálogo cerrado: `contrato_creado`, `orden_trabajo_creada`, `trabajo_cerrado`), `clave_evento` (identidad del hecho, p. ej. `contrato_creado:12`), `parametros` (JSON con lo que el texto necesita: cliente, hectáreas, número de aplicación; las cifras se guardan ya presentables —hectáreas en formato es-AR, armadas sobre el decimal exacto y sin pasar por `float`— y el texto que las rodea se traduce al mirar), `recurso_tipo` + `recurso_id` (a qué recurso lleva el click) y `leida_en`. `recurso_id` no es FK: apunta a tablas de varios módulos y el aviso debe sobrevivir a que el recurso se dé de baja.

3. **Quién es el destinatario: se declara por rol, por persona o por equipo, y se entrega a la cuenta.**
   - *Por rol* = **toda cuenta interna activa que tenga ese rol asignado en `sec_user_role`, aunque no sea su rol activo en este momento.** El invariante 10 dice que los permisos efectivos son los del rol activo; una notificación **no es un permiso**: enterarse de que algo ocurrió no concede poder hacerlo. Un usuario que es piloto y jefe de campo (`abraham.gutierrez`) recibe los avisos del jefe de campo aunque hoy esté operando como piloto. El rol activo interviene después, al **abrir** (punto 6): el click nunca da acceso a lo que ese rol no puede ver.
   - *Por persona* (`per_personas.id`): la cuenta que tiene esa persona vinculada (`LecturaUsuarioDePersona`); sin cuenta o con la cuenta bloqueada, no hay a quién avisar y no falla nada.
   - *Por equipo* (`per_equipos_trabajo.id`): los integrantes vigentes hoy → sus personas → sus cuentas. Es lo que cubre «notifica al equipo asignado».
   - El destinatario nunca se declara como `usuario_id` suelto: quien emite un evento de dominio no conoce cuentas. La conversión rol/persona/equipo → cuentas es una sola clase (`ResolverDestinatarios`).
   - El aviso es una foto del momento: quien recibe un rol después no recibe lo anterior, y quien lo pierde conserva los que ya recibió (no se revocan).
   - Lo que dice el aviso (cliente, hectáreas, números de orden) se muestra aunque el rol activo de la sesión no pueda abrir el recurso: es información de aviso decidida para el rol asignado, no un dato del recurso. Lo que el rol activo limita es adónde lleva el click (punto 6).
   - Quien dispara el evento no se notifica a sí mismo (si el dueño carga un contrato, no se avisa a sí mismo aunque también tenga el rol).

4. **Patrón de evento generalizado.** El hecho lo emite el módulo dueño de la transición como DTO `readonly` de primitivos en su `Contratos/Eventos/` (mismo molde que `SesionValidada` y `AplicacionCerrada`), **después** de persistir y sin cambiar su máquina de estados, e implementando `ShouldDispatchAfterCommit`: si el emisor está dentro de una transacción, el aviso sale recién al confirmarla — un rollback nunca anuncia lo que no ocurrió. Nuevos eventos: `Comercial\ContratoCreado`, `Operaciones\OrdenTrabajoCreada`, `Operaciones\TrabajoCerrado` (el «trabajo realizado» del pedido es la transición `abierto → cerrado` de `MaquinaEstadosTrabajo`).
   Un **único listener genérico** (`EntregarNotificacionDelEvento`) atiende a todos los eventos. Para cada uno hay una **regla** (`Notificaciones/Aplicacion/Reglas/`, una clase por evento) que dice qué tipo de aviso es, con qué parámetros, a qué recurso lleva y a quiénes se declara. Agregar un aviso nuevo = un evento en el módulo emisor + una regla + una clave en `lang/es/notificaciones.php`; ningún `ServiceProvider` ajeno cambia.
   Las reglas viven en `Notificaciones`, no en el emisor: «a quién le importa esto» es política de aviso, y los módulos de negocio no tienen que saber que existe una campana.

5. **Idempotencia por identidad del hecho.** `UNIQUE (usuario_id, clave_evento)` — a propósito **no parcial** (`WHERE deleted_at IS NULL`, como los demás índices únicos del repo): si una cuenta descartó un aviso, un reintento del mismo evento no lo resucita. El listener hace «existe → no hace nada» y atrapa la violación de unicidad por si dos procesos se cruzan. Mismo criterio que `uuid_cliente` (invariante 1) aplicado al evento: correr el listener dos veces sobre el mismo evento deja las mismas filas.

6. **Cada aviso lleva a algún lado, según quién lo abre.** El click no va al recurso directo: va a `GET /panel/notificaciones/{notificacion}/abrir`, que (a) busca el aviso **solo entre los de la cuenta autenticada** (404 si es de otra), (b) lo marca leído y (c) redirige al recurso si el **rol activo** tiene el permiso para verlo (`comercial.contrato.editar`, `operaciones.trabajo.ver`), y si no, a la primera pantalla visible de ese rol (`primerDestinoVisible`; en la práctica su tablero). El id del aviso admite hasta 18 dígitos en la ruta: uno mayor no existe y responde 404, no un error del servidor. Un piloto que recibe «tu cuadrilla tiene una orden de trabajo nueva» no puede abrir `/panel/trabajos/{id}` (no tiene `operaciones.trabajo.ver`) y aterriza en su tablero, en vez de en un 403. `abrir` es un `GET` a propósito: es un enlace de la campana (se puede abrir en otra pestaña) y marcar leído es idempotente. `marcar-todas` (`POST`) marca solo las de la cuenta, fila por fila para que cada cambio pase por la autoría y la bitácora.

7. **`ope_alertas` sigue emitiendo por su cuenta; la campana mezcla las dos fuentes.** No se absorbe como un tipo más del motor. Las alertas técnicas tienen ciclo de vida propio (`pendiente → atendida`, con pantalla y permisos `operaciones.alerta.ver/atender`), unicidad propia por tipo y recurso, y una audiencia definida por **permiso** (quien puede ver `/panel/alertas`), no por rol asignado. Absorberlas obligaría a duplicar su estado en las filas del motor o a sincronizarlo. La cáscara del panel (`CascaraPanel::notificaciones()`) junta ambas listas y `CampanaDeAvisos` decide cuáles entran: todo lo no leído primero (hasta el tope de 10), después los leídos más recientes, del más nuevo al más viejo — así el badge, que cuenta lo no leído de la lista que recibe, sigue siendo exacto hasta «9+». Cada alerta gana el enlace a `/panel/alertas` que antes no tenía.

8. **Soft delete y auditoría como cualquier modelo de dominio** (invariantes 8 y 9), sin excepción: `Notificacion` extiende `ModeloDominio` (soft delete, `forceDelete()` bloqueado, autoría) y usa `RegistraBitacora`. Costo asumido: cada aviso repartido y cada lectura dejan una fila en `plt_bitacoras`. Si el volumen llegara a molestar, la salida es excluir esta entidad del observer con una decisión explícita, no omitirlo hoy por anticipación.

9. **Un fallo al avisar no rompe la operación de negocio.** El aviso es secundario: el contrato ya se creó, el trabajo ya se cerró. El listener atrapa cualquier `Throwable`, lo reporta (`report()`) y sigue; nunca deja que un error de entrega devuelva 500 sobre algo ya confirmado, ni aborte la transacción de sincronización de la app de campo. Cada destinatario se resuelve aislado (uno con datos rotos —un equipo con un integrante dado de baja— se reporta y no anula a los demás), y la cáscara del panel tampoco tira las páginas si falla la lectura de avisos: la campana sale sin los del motor.

10. **Primera cadena (fuera de esto, el motor no dispara nada):**

| Hecho | Regla | Destinatarios | Lleva a |
|---|---|---|---|
| Contrato creado | `ContratoCreado` | rol `encargado_operaciones` | el contrato |
| Orden de trabajo creada (tanda con equipos) | `OrdenTrabajoCreada` | cada equipo asignado → sus integrantes vigentes | la orden de trabajo |
| Trabajo cerrado | `TrabajoCerrado` | roles `jefe_campo` y `encargado_operaciones` | el trabajo |

   Las hectáreas del aviso de orden de trabajo son las de **toda la tanda** (todos sus equipos), no las del equipo de quien lo lee; el texto lo dice («en total»). Por-destinatario haría falta un aviso por equipo.

   La orden de **aplicación** no tiene equipo cuando nace (el reparto lo confirma la orden de trabajo), así que «notifica al equipo asignado» sale de la orden de trabajo y no de la alta de la orden de aplicación.

   **El dueño no recibe el «trabajo cerrado».** Es el aviso de mayor frecuencia (uno por equipo×lote, varios al día) y su tablero ya muestra el avance; recibirlo lo convertiría en ruido. Si el dueño lo quisiera, es agregar `RolDestinatario::Dueno` a `ReglaTrabajoCerrado`, una línea.

11. **Extensión (23/9/2026): abrir marca leído también en las alertas, y «Limpiar».** Al probar la campana apareció que el clic en una alerta técnica llevaba a `/panel/alertas` pero la dejaba «sin leer» para siempre: el punto 7 las dejó con su ciclo de vida propio (`pendiente → atendida`, de todos), y ese estado no dice si *esta* cuenta ya la vio. Sin tocar el punto 7:
   - **Estado de lectura por cuenta, en el módulo que ya sabe de cuentas.** Tabla nueva `ntf_alertas_vistas` (`usuario_id`, `alerta_id` sin FK, `leida_en`, `limpiada_en`, `UNIQUE (usuario_id, alerta_id)` no parcial). Es lo que cada cuenta hizo con cada alerta; **no** cambia el estado de la alerta ni la declara atendida. `Notificaciones` sigue sin leer ninguna tabla ajena: pregunta por contrato de Operaciones (`LecturaPanelOperaciones::idsAlertasExistentes()`) qué ids existen, y el estado se escribe siempre bajo el id de la sesión.
   - **La campana** calcula «sin leer» de una alerta como `pendiente` **y** no leída por esta cuenta, y no muestra las que la cuenta limpió (`alertasRecientes($limite, $excluirIds)` las descuenta antes de aplicar el tope, así una limpiada no tapa a una más vieja). Cada alerta enlaza a `GET /panel/notificaciones/alertas/{alerta}/abrir`, que exige `operaciones.alerta.ver` contra el rol activo, revalida que la alerta exista (404 si no), deja la lectura y redirige a su pantalla. Con la vista «como otro usuario» activa no escribe, igual que `abrir`.
   - **«Marcar todas como leídas»** marca los avisos del motor de la cuenta **y** las alertas que su campana mostraba. **«Limpiar»** (`POST /panel/notificaciones/limpiar`) vacía la campana de la cuenta, leídas y no leídas: da de baja (soft delete) **todos** sus avisos del motor —no solo los que alcanzan a verse— y deja limpiadas las alertas que mostraba. El índice único no parcial del punto 5 hace que un reintento del evento no los resucite. Ninguna de las dos toca a otra cuenta ni una pantalla: una alerta limpiada sigue en `/panel/alertas`.
   - **Qué alertas se marcan o limpian** las dice el formulario (`alertas[]`, las que la campana mostraba); el servidor las ignora si el rol activo no puede verlas y descarta las que no existen. Un id inventado nunca deja una fila que taparía una alerta futura.
   - Fila por fila con `save()`/`delete()`, no con `update()` masivo, para que cada cambio deje su fila de bitácora (invariantes 8 y 9).

## Alcance: lo que NO es

- No es un canal externo: ni push, ni correo, ni SMS. Vive dentro de la campana del panel.
- No reemplaza a `ope_alertas` (punto 7) ni a los contadores del menú.
- No tiene bandeja propia ni preferencias por usuario todavía: la campana muestra lo reciente (las no leídas primero) y permite marcar todo como leído.
- No hay retención automática de avisos viejos (el volumen es del orden de unidades por evento): lo que sí hay, desde el punto 11, es que cada cuenta limpia su propia campana a mano.

## Consecuencias

- Tres módulos emisores dependen de un contrato que ya existía (los `Contratos/Eventos/`); `Notificaciones` depende de `Contratos/` de Comercial, Operaciones, Personal y Seguridad. `Seguridad` (la cáscara) depende de `Notificaciones\Contratos\LecturaNotificaciones`. Ninguna importa `Aplicacion`, `Dominio` ni modelos Eloquent ajenos (`ArquitecturaModulosTest`).
- La cáscara del panel tiene ahora dos fuentes; el badge sigue contando lo no leído de la lista que recibe, que prioriza las no leídas hasta el tope de la campana.
- `Seguridad` expone un contrato de lectura nuevo, `LecturaUsuariosPorRol`, que responde por rol **asignado** (no por rol activo).
- Zona de revisión línea por línea (`CLAUDE.md`, «qué no delegar»): el reparto por rol y el aislamiento entre cuentas — el aviso de una cuenta nunca lo ve otra. La garantía es de código: todo acceso de lectura pasa por `Notificacion::deUsuario()`.
- Coordinación con la tarea 140 («ver como»): mientras dura una vista como otra cuenta el panel es de solo lectura, y `abrir` escribe `leida_en`. Quien integre las dos debe hacer que `abrir` no marque leído en ese modo.

- El punto 1 («escribe solo `ntf_notificaciones`») queda ampliado: escribe también `ntf_alertas_vistas` (punto 11). `Notificaciones` ahora depende también de `Operaciones\Contratos\LecturaPanelOperaciones`, como ya dependía de otros contratos de Operaciones.
- Zona de revisión línea por línea (aislamiento entre cuentas): el estado de una cuenta sobre las alertas y su limpieza. Ambos parten de `AlertaVista::deUsuario()` / `Notificacion::deUsuario()` y del id de la sesión; `tests/Unit/NotificacionesLimpiezaTest.php` lo fija con mutaciones.

## Alternativas descartadas

- **Notificaciones de Laravel (`Notifiable` + tabla `notifications`).** Ya está en `SecUser`, pero solo para el correo de restablecer contraseña. La tabla estándar es polimórfica (`notifiable_type/id`, prohibido entre módulos por el ADR 0011), guarda un JSON sin esquema, no lleva prefijo de módulo ni soft delete ni autoría, y no tiene forma natural de imponer la unicidad que da la idempotencia.
- **Resolver el destinatario al mirar (aviso «al rol», sin una fila por cuenta).** Evita repartir, pero el estado leído/no leído tendría que vivir en otra tabla por cuenta, y quien recibiera un rol después vería avisos de hechos anteriores a su alta. Repartir al emitir es más simple de consultar (`WHERE usuario_id = ?`) y de aislar.
- **Que cada evento declare a sus destinatarios** (una interfaz que el evento implemente). Obliga a los módulos de negocio a conocer claves de rol de Seguridad y a depender de `Notificaciones`. Con las reglas dentro del módulo, la dependencia va en un solo sentido.
- **Un closure por `ServiceProvider`** (el patrón de `SesionValidada`). Correcto para un cálculo propio de un módulo; para un aviso transversal multiplica el mecanismo.
- **Meter las alertas en el motor** (punto 7).
- **Marcar la alerta como atendida al abrirla desde la campana.** Cambiaría el estado de todos por lo que hizo una cuenta, y una alerta que nadie atendió dejaría de estar pendiente por un clic de curiosidad. Leída (de esta cuenta) y atendida (de todos) son cosas distintas.
- **Una columna «leída por» en `ope_alertas`.** Operaciones tendría que conocer cuentas y estado de lectura, que es de `Notificaciones`; y una sola columna no alcanza para un estado que es por cuenta.
- **Materializar una fila del motor por cuenta al emitir cada alerta** (absorberlas, la alternativa del punto 7 otra vez): resolvería la lectura, pero duplica el ciclo de vida de la alerta o lo tiene que sincronizar. Se guarda solo lo que cada cuenta hizo, y solo cuando lo hizo.
- **Guardar la URL en el aviso.** Se rompe si cambia una ruta y no sabe de permisos; se guarda el recurso y el destino se resuelve al abrir.
- **`Compartido` (`plt_`) como dueño.** `Compartido` es plataforma técnica; una regla de «a quién le importa qué hecho» es dominio.
