# ADR 0046 — Cuentas separadas por app, persona única, verificación de identidad y roles del panel

**Estado:** aceptada, revisable · **Fecha:** 2026-11-26 · **Decide:** el dueño · **Sustituye:** ADR 0004 en lo que dice del multi-rol y el rol activo por sesión, del portal del cliente con guard propio y de «ver como» sobre cuentas de app; ADR 0036 punto 4 (roles `cliente` y `proveedor` con rol en el token); ADR 0044 punto 2 (el cliente nace activo) y punto 3 («quien registra no aprueba»). Complementa los ADR 0028, 0032 y 0042.

## Contexto

El sistema mezcla dos mundos en `sec_user`: el personal de Agrocom que usa el panel y las personas que usan las apps. Las cuentas de app son `sec_user` de tipo `interno` con un rol (`cliente`, `proveedor`, `piloto`, `auxiliar`) y entran al panel con el menú vacío; el rol viaja en el token del dispositivo y las apps lo leen, con un selector de rol y un `409`. Hay nueve roles de panel heredados de la operación interna, de los cuales hoy se usan dos.

Lo que el negocio necesita:

- El panel lo usa solo el personal de Agrocom, y hoy es una sola persona que verifica, atiende y consulta.
- Son **dos apps distintas**, con dos fichas en las tiendas: la del cliente (dueño de terreno) y la del piloto o proveedor. Cada app admite un solo tipo de cuenta. Quien quiere ser proveedor siendo cliente se registra de nuevo en la app del piloto; un proveedor no se vuelve cliente (el terreno lo tiene el cliente, y es más fácil que un cliente compre un dron que lo contrario).
- Cualquiera se registra y mira la app, pero operar en el mercado con dinero, terrenos y drones ajenos exige saber que la persona existe: hoy solo se verifica al proveedor y el cliente nace activo.
- Una misma persona real no puede duplicarse: a lo sumo una cuenta de cliente y una de proveedor, para evitar fraudes de autenticidad y cuentas repetidas.
- Aún no hay apps instaladas fuera del equipo y beta tiene solo datos de prueba: el contrato de login puede cambiar de golpe.

## Decisión

### 1. Tres tipos de cuenta, una por app

1. `sec_user.tipo_cuenta` vale `panel` (personal de Agrocom), `cliente` o `proveedor`. Reemplaza a `type` (`interno` | `cliente`) y a `contrato_id`. Una cuenta tiene un solo tipo para siempre: **no hay conversión de una en otra, ni selector de rol, ni un login que sirva para las dos apps.** Cada cuenta tiene su propio usuario y contraseña, su propia verificación y su propia aprobación.
2. El guard del panel autentica solo cuentas `panel`. El token de dispositivo se emite solo a cuentas `cliente` o `proveedor`, y el login declara desde qué app llega (`app`: `cliente` o `proveedor`). Si el tipo de la cuenta no es el de la app, el servidor responde `403` con `codigo: tipo_de_cuenta_incorrecto` y el tipo de la cuenta, y **no emite token** («Esta cuenta es de cliente: ingresa en la app Agrocom»). Una cuenta `panel` recibe en las apps el mismo rechazo que una credencial inválida, y una cuenta de app en el panel también (no se revela qué cuentas existen). Las cuentas de app no tienen filas en `sec_user_role`: sus permisos salen de su tipo (punto 17).
3. `proveedor` incluye al proveedor independiente, al dueño de drones, al proveedor propio y al piloto con cuenta propia de un tercero. Su perfil es la fila de `prv_proveedores` de la que es titular o, si es piloto de un tercero, su fila de `prv_pilotos`. `cliente` tiene su fila de `sol_clientes` (persona o empresa, ADR 0043).

### 2. Panel: Administrador y Operador

4. **Roles del panel: `administrador` y `operador`.** Una cuenta `panel` tiene un solo rol. El administrador ve y hace todo. El operador ve partes específicas de algunos módulos: sus permisos son los que el administrador marca en la matriz de roles, salvo los reservados al administrador (usuarios, roles, configuración, «ver como», carga de verificación por administrador). Se retiran del panel `dueno`, `encargado_operaciones`, `jefe_campo`, `soporte`, `auxiliar`, `admin_plataforma`, `operario`, `cliente`, `proveedor` y `piloto`; los usuarios de panel existentes pasan a `administrador`. No se separan funciones por rol: quien administra también verifica, atiende y consulta.
5. **«Ver como» sobre cuentas de app se retira**, junto con el portal heredado del cliente (guard `cliente`, `portal/*`, `contrato_id`). Se conserva solo para el administrador y solo sobre cuentas `panel`, en solo lectura, para comprobar qué ve un operador. El portal web del cliente, si se crea, será una superficie de cuentas `cliente` con su propio ADR.
6. El invariante 10 pasa a decir: un usuario, un login; en el panel un solo rol; en las apps, un tipo de cuenta. El «rol activo por sesión» (ADR 0004, extensión del 27/8/2026) deja de tener sentido y su maquinaria (`ElegirRolActivo`, `RecordarRolPreferido`, `rol.activo`, la pantalla de selección) se retira en una fase propia.

### 3. Persona única

7. **`sec_personas`** (módulo Seguridad): `id`, `documento_tipo` (`ci`, `pasaporte` o `licencia_conducir`), `documento_numero` (normalizado: mayúsculas, sin espacios ni puntos), `nombres`, `apellidos`, `celular`, `fecha_nacimiento` (nula; la pide el registro del proveedor, porque quien vuela debe ser mayor de edad). **Índice único parcial `(documento_tipo, documento_numero)`**: una persona real es una fila. El correo vive solo en `sec_user.email` y la foto de perfil en `sec_user.foto_perfil_ref` (es de la cuenta, no de la persona). Reemplaza a `per_personas`, que se retira con todo `per_*`.
8. **`sec_user.persona_id`** apunta a `sec_personas` y es obligatorio en las cuentas de app y nulo en las de panel (el personal usa `sec_user.name`). **Índice único parcial `(persona_id, tipo_cuenta)`** entre cuentas no eliminadas: una persona tiene a lo sumo una cuenta de cliente y una de proveedor, y una cuenta dada de baja o rechazada sigue ocupando su lugar (la reactiva o la libera un administrador). Los mismos datos de persona sirven a las dos cuentas sin duplicarse.
9. **Registrarse otra vez en el mismo tipo.** El registro (app o panel) normaliza el documento y busca la persona:
   - **No existe:** se crea la persona y la cuenta.
   - **Existe sin cuenta de ese tipo** (por ejemplo, un cliente que se registra como proveedor): la cuenta nueva se enlaza a la misma persona. El servidor **no sobrescribe** los datos de la persona; si los que se enviaron difieren de los registrados, lo marca para quien revisa.
   - **Existe con cuenta de ese tipo:** `409` con `codigo: persona_ya_registrada` y el `tipo_cuenta`. La app muestra «Ya tienes una cuenta de cliente. Inicia sesión o recupera tu acceso.», con esos dos botones y «Si no fuiste tú, escríbenos». El mensaje no devuelve ningún dato de la persona, y el registro público sigue bajo el limitador `registro-publico` (5 por minuto y 20 por hora por IP).
   Límites asumidos: el documento declarado no está verificado hasta la revisión, así que alguien podría registrar el documento de otra persona antes que ella; el recurso es que esa persona escriba a la oficina y el administrador compare la selfie con el documento y elimine la cuenta falsa con motivo, lo que libera el lugar. Quien use otro tipo de documento para sí mismo (CI y pasaporte) crearía dos personas: lo detecta la revisión humana, no el índice. Es revisable.
10. **Sin autocontratación.** Una cuenta no ofrece sobre una solicitud publicada por ella misma (regla vigente) ni por otra cuenta de la misma `persona_id`, y no se califica a sí misma. Importa porque ahora una misma persona puede tener la cuenta de cliente y la de proveedor.

### 4. Estado y verificación por cuenta

11. **El estado de aprobación es de cada cuenta**, con el ciclo único del ADR 0036 (`pendiente → aprobado ⇄ suspendido`, `pendiente → rechazado → pendiente`, cualquiera → `baja`) en la fila de su perfil. `sol_clientes.estado` deja de ser `activo | suspendido` y pasa a ese ciclo: un cliente nace `pendiente`. El cliente tiene su historial de estados con autor y motivo, como el proveedor.
12. **Qué puede hacer una cuenta según su estado:**

| Estado | Entra y ve las pantallas de su app | Cliente: publicar solicitud, aceptar o contraofertar | Proveedor: ofertar, contraofertar, aceptar |
|---|---|---|---|
| `pendiente`, `rechazado` | Sí: su perfil, su verificación, y sus terrenos y borradores (cliente) o sus drones y documentos (proveedor) | No | No |
| `aprobado` | Sí | Sí | Sí |
| `suspendido`, `baja` | Sí, solo lo propio y el historial | No | No |

   El servidor decide y responde `403` con `codigo: cuenta_no_aprobada`, el estado y lo que falta (distinto de `proveedor_no_habilitado`, que sigue significando que falta un equipo apto); la app lo muestra como la pantalla de verificación, no como un error genérico. La app no replica la regla. Lo ya adjudicado no se interrumpe por un cambio de estado: qué hacer con un servicio en curso de una cuenta suspendida lo decide el administrador caso a caso.
13. **`sec_verificaciones`**: una por cuenta (`usuario_id`), con `estado` (`en_revision | aprobada | rechazada | reemplazada`), la **selfie** (`selfie_ref`, `selfie_capturada_en`, `selfie_origen`: `camara` | `administrador`), el **documento** (`documento_ref`, `documento_origen`: `cuenta` | `administrador`; el tipo y el número son los de la persona, y quien revisa verifica que coincidan con la imagen) y la revisión (`revisado_por`, `revisado_en`, `motivo_rechazo`). Lo ya revisado no se reescribe: reenviar crea una fila nueva y deja la anterior `reemplazada`. Hay a lo sumo una vigente por cuenta.
14. **La selfie es distinta de la foto de perfil.** La foto de perfil la ven las otras partes (ADR 0042); la selfie solo la ve quien revisa. La selfie se toma con la cámara de la app en el momento y la app no ofrece galería para ella. El servidor no puede probar el origen de una imagen: registra `selfie_capturada_en` y exige que no sea anterior a 10 minutos (parámetro); el control real es que quien revisa compara la selfie con el documento. Una prueba de vida con reto aleatorio queda como mejora revisable.
15. **Cada cuenta sube su propia selfie y su propio documento, también cuando la persona ya está verificada con la otra cuenta.** Reutilizar lo de la otra cuenta sería convertir un registro cualquiera con un documento ajeno en una cuenta verificada; una selfie nueva prueba que quien maneja esta cuenta es la persona. Para que no sea una carga doble para quien revisa, la ficha muestra, si la persona tiene otra cuenta aprobada, su selfie y su documento anteriores al lado de los nuevos: aprobar es una comparación de un clic, y lo específico del proveedor (licencias, seguros, dron con fotos) se revisa aparte. Es revisable (pregunta abierta del plan).
16. **Para aprobar una cuenta** hace falta su verificación `aprobada`. Al proveedor se le exige además lo que ya piden las políticas (licencia, matrículas, seguros, fotos del dron): el documento `identidad` deja de ser obligatorio en `prv_documentos` porque lo cubre la verificación. Quien revisa aprueba la verificación y, en el mismo paso, puede aprobar la cuenta. Selfie y documento van al disco privado con ruta por objeto (ADR 0026), se sirven con URL firmada de corta vida solo con el permiso `seguridad.verificacion.ver`, no entran a la bitácora ni a los logs, y la app borra su copia local al subirlos. Las otras partes ven solo la insignia «Verificado».

### 5. Contrato de login y permisos de app

17. El token de dispositivo (`sec_token_dispositivo`) identifica cuenta y dispositivo, **sin rol** (`role_id` se retira). `POST /api/auth/token` recibe `username`, `password`, `uuid_dispositivo` y `app`; ya no responde `409` ni lista roles. Esa respuesta y `GET /api/auth/sesion` devuelven `cuenta` (id, usuario, nombre, correo, `tipo` = `cliente` o `proveedor`, `estado`, el motivo si es `rechazado` o `suspendido`, lo que falta para aprobarla y los datos propios del perfil: persona o empresa; en el proveedor, `tipo` propio, independiente o tercero y `rol_en_proveedor`: `titular` o `piloto`), `persona` y la `verificacion` de la cuenta. No hay lista de capacidades: una cuenta tiene un solo tipo.
18. Los permisos de las cuentas de app viven en `config/permisos_app.php` (tipo de cuenta → códigos de `sec_permission` con `ambito = app`), no en `sec_role`, y no se editan desde el panel. `IdentidadOperarioTokenSanctum::tienePermiso` resuelve por el tipo de la cuenta y exige su estado según el punto 12. El lado de una acción (cliente o proveedor) lo decide el tipo de la cuenta.

### 6. Alta desde el panel

19. El administrador crea cuentas de app (cliente o proveedor) desde el panel, con los mismos casos de uso, reglas y unicidad que el registro público (ADR 0044), y **las aprueba él mismo**. Para no romper la regla de que la verificación exige documento, adjunta el documento de la persona si lo tiene o deja un **archivo marcador generado por el sistema** (una página con la leyenda «Documento no cargado por la persona; verificada por la oficina»). Para que no se confunda con una verificación documental:
    - la fila de `sec_verificaciones` lleva `documento_origen = administrador` y `es_marcador = true` si no hay documento real, y `selfie_origen = administrador`;
    - el administrador debe escribir un motivo («verificado en persona en la oficina», «cliente conocido»), que queda en la verificación y en la bitácora con su autor;
    - la ficha, la cola de revisión y el historial muestran la insignia «Cargado por administrador, sin documento de la persona»;
    - la app muestra la verificación como «Verificada por la oficina» y le pide a la persona completar su selfie y su documento cuando entre; al completarlos, la verificación nueva reemplaza a la del administrador y la anterior queda en el historial;
    - el tablero cuenta las cuentas aprobadas con marcador, para que el administrador vea cuántas hay.
    El riesgo es de auditoría: sin la marca, un archivo en blanco aparece como evidencia que nunca existió y un tercero (aseguradora, autoridad, un cliente en un reclamo) podría creer que la identidad se comprobó con un documento. Con la marca, la aprobación por administrador es una decisión de una persona con nombre, fecha y motivo, no una evidencia falsa. Es revisable.
20. **Separación de funciones.** `proveedores.separar_funciones` (PR #352) deja de decir «quien registra no aprueba lo suyo» y pasa a decir **«nadie aprueba su propia cuenta»**: el actor no puede aprobar la cuenta de la que es titular. Aplica al registro público (el solicitante no puede aprobarse) y no limita al administrador que dio de alta desde el panel, que puede aprobar lo que registró. Cambio mínimo: los llamadores de `SeparacionDeFunciones::exigir` pasan el usuario dueño del sujeto en lugar de `created_by`. Como las cuentas `panel` y las de app son distintas, el caso queda cubierto por construcción y la regla es una segunda línea de defensa. La bitácora sigue registrando al administrador como actor.

### 7. Empresas y equipo

21. Persona natural o empresa sigue como está (`sol_clientes.tipo`; `prv_proveedores.tipo`). Se agregan, opcionales, a ambos perfiles: `nombre_comercial` y `logo_ref`; `razon_social` y `documento_fiscal` (el NIT) pasan a ser opcionales cuando el perfil es de persona. La persona del documento de identidad es siempre quien registra la cuenta, también en una empresa. El logo y el nombre comercial del proveedor los ve el cliente en las ofertas; los del cliente los ve el proveedor solo tras adjudicar (ADR 0043).
22. **Integrantes del equipo del servicio.** El proveedor puede registrar, opcionalmente, a las personas que forman un equipo: `flo_equipo_integrantes` (`equipo_id`, `nombre`, `celular` nulo, `orden`). Es solo informativo: no tienen cuenta ni verificación, no tienen página propia en el panel ni en la app del piloto (se editan dentro del formulario del equipo) y no entran en la aprobación ni en las calificaciones. Reemplaza al auxiliar, que se retira del sistema. La app del cliente muestra el equipo completo (el piloto, marcado como tal, y los integrantes) **solo después de adjudicar**, por la política de datos personales. El proveedor responde por los datos que carga; se borran con el equipo.

### 8. Despliegue

23. No hay apps instaladas fuera del equipo y beta tiene solo datos de prueba. Por eso el contrato de login cambia de golpe, sin capas de compatibilidad, y el esquema no se transforma con migraciones de retiro: se reconsolida con `bin/consolidar-migraciones` (ADR 0024) y beta se reconstruye (`migrate:fresh` y siembra). No se emiten migraciones `DROP` ni se mueven datos a `respaldo_*`; el despliegue conserva su `pg_dump` automático previo. **Esta excepción al invariante 15 vale solo mientras no haya datos reales**: desde el primer usuario externo o la primera solicitud real, todo retiro vuelve a ir por esquemas `respaldo_*`.

## Alternativas descartadas

- **Una cuenta con dos capacidades (cliente y proveedor con un solo login):** son dos apps con dos fichas en las tiendas y cada una admite su tipo; una cuenta para las dos exige activar capacidades, selectores y una sesión que sirve a dos superficies. Se descartó por indicación del dueño.
- **Convertir una cuenta de cliente en proveedor (o al revés):** mezcla verificaciones y aprobaciones distintas en una sola cuenta; el cliente que quiere ser proveedor se registra de nuevo.
- **Unicidad por correo o usuario, sin persona:** permite que la misma persona abra diez cuentas de cliente con otros correos y se califique o se autocontrate sin que el sistema lo vea.
- **Una fila de persona por cuenta:** duplica los datos y deja pasar a la misma persona dos veces como cliente; la unicidad por documento no tendría dónde vivir.
- **Reutilizar la selfie y el documento de la otra cuenta sin pedirlos de nuevo:** quien conozca el documento de una persona ya verificada obtendría una cuenta verificada nueva.
- **Cuatro roles de panel (administrador, verificador, atención, consulta):** pensados para separar funciones entre varias personas; hoy es una. El operador con permisos a medida cubre el caso sin fijar funciones que nadie ejerce.
- **Datos de la persona dentro de `sec_user` o en `prv_*`:** engorda la cuenta del personal, que no los necesita, y el cliente tampoco vive en `prv_*`.
- **Selfie desde la galería:** no prueba que la persona estaba ahí.
- **Aprobar sin documento y sin marca, o con un archivo en blanco sin marcar:** deja una evidencia que nunca existió.
- **Aprobar primero y verificar después (cliente):** un cliente sin verificar podría publicar y aceptar ofertas sobre drones y terrenos ajenos; el riesgo físico y de fraude es el mismo que justificó el ADR 0032.

## Consecuencias

- Se retiran de `sec_role` nueve roles y sus asignaciones; `RolDestinatario` y las reglas de aviso pasan a `administrador`; los accesos rápidos y el tablero dependen de permisos, no del nombre del rol.
- El cliente deja de nacer activo: hay una cola de verificación de clientes y de proveedores en el panel, a cargo de una sola persona. Es el costo operativo de la decisión.
- Cambia el contrato de `POST /api/auth/token` y `GET /api/auth/sesion`; se agregan los endpoints de verificación y el `409 persona_ya_registrada` de los registros.
- Se retiran `per_*` (incluida `per_personas`) y `prv_auxiliares`; las demás tablas del modelo anterior siguen el plan de limpieza.
- `docs/politicas/verificacion_proveedores.md`, `docs/especificacion/marketplace.md`, `docs/seguridad/autenticacion.md`, el glosario y `CLAUDE.md` describen este modelo.
- Revisable: el operador único por matriz de rol (¿permisos por persona?), el marcador del administrador, el plazo de 10 minutos de la selfie, volver a pedir la verificación a quien ya está verificado con la otra cuenta, el alcance de la unicidad por documento y que el proveedor no aprobado no vea el mapa.
