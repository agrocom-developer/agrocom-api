# CLAUDE.md — invariantes de `agrocom-api`

Este repo es el backend (Laravel + PostgreSQL) y el panel web (AdminLTE + Blade/Livewire) de Agrocom SRL. El desarrollo lo hace una sola persona con agentes de IA como implementadores principales; estas invariantes existen para que la coherencia no dependa de la memoria de nadie. La fuente de verdad funcional/técnica es `docs/especificacion/especificacion_funcional_tecnica.md`; el porqué de cada decisión de arquitectura está en `docs/decisiones/` (ADRs). Léelos antes de implementar algo que los toque.

## Invariantes no negociables

1. **Idempotencia por UUID.** Todo registro que nace en la app de campo trae su `uuid_cliente` generado en el dispositivo. El servidor lo hace único (`UNIQUE (uuid_cliente)`) y responde `duplicado` ante un reintento — nunca duplica hectáreas.
2. **El servidor nunca sobrescribe un registro validado.** Las correcciones son registros nuevos con `anula_a_id`, motivo y autor — nunca un `UPDATE` sobre lo validado.
3. **Una sesión se valida una sola vez, nunca al cerrarla.** Validar es una transición de estado que anuncia el evento de dominio `SesionValidada`; validar de nuevo una sesión ya validada no hace nada y no repite el aviso.
4. **Validador ≠ piloto de esa sesión, a nivel de persona — no de rol.** Un jefe de campo que también es piloto no valida sus propias sesiones (ver `docs/decisiones/0004-modelo-seguridad-sec-multirol.md`).
5. **El cliente solo alcanza lo suyo.** Toda consulta de la API del cliente y del portal parte del `cliente_id` de la cuenta autenticada (solicitudes, terrenos, actas, reportes), nunca de la tabla global con un `where` agregado después, y un id ajeno responde 404. La garantía es de código (scoping en el caso de uso), no de un test de endpoint — ver §Testing.
6. **Dinero y hectáreas en `DECIMAL`, jamás `float`.** Todo monto derivado debe poder recalcularse desde los registros de origen y cuadrar exacto.
7. **Toda transición de estado pasa por el servicio de dominio de la máquina de estados correspondiente** (tabla de transiciones permitidas + guardas) — nunca un `estado = ...` suelto en un controlador.
8. **Soft delete por defecto en todo modelo de dominio.** Ningún `DELETE` físico salvo excepción justificada explícitamente en el PR (`docs/decisiones/0007-soft-delete-y-bitacora-auditoria.md`).
9. **Bitácora de auditoría en toda mutación relevante**: quién, cuándo, qué entidad, qué acción, valores antes/después donde aplique. Es un trait/observer de plataforma — no una llamada manual que cada caso de uso deba recordar.
10. **Un usuario, un login, múltiples roles, un rol activo por sesión.** Nunca se crean cuentas duplicadas por rol. Un usuario puede tener varios roles asignados vía `sec_user_role`, pero en cada sesión opera bajo un único **rol activo** (elegido al iniciar sesión entre los roles asignados); los permisos efectivos son los de ese rol activo, nunca la unión de todos los roles del usuario. Cambiar de rol activo no requiere volver a loguearse.
11. **Ningún color hardcodeado en el panel.** Todo color se referencia por token CSS — es lo que permite el theming por usuario (`docs/decisiones/0002-panel-web-adminlte-livewire-atomic-design.md`).

## Convenciones

- **Dominio en español, infraestructura en inglés**: `Trabajo`, `Sesion`, `Mezcla`, `hectareas_declaradas`, pero `SyncController`, `Repository`. El vocabulario del negocio es en español; traducirlo crea dos idiomas para la misma cosa.
- **Commits en español, imperativo**: `agrega validación de solape en sesiones`.
- **Arquitectura**: monolito modular Laravel, un módulo de dominio = una carpeta bajo `app/Dominios/`, con `Contratos/`, `Aplicacion/`, `Dominio/`, `Infraestructura/` (ver `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`). Un módulo solo escribe sus propias tablas; entre módulos se viaja por contratos o eventos de dominio, nunca por modelos ajenos.
- **Panel web**: componentes Blade en Atomic Design (atoms/molecules/organisms/templates/pages), AdminLTE (Bootstrap) para estructura + Material Design para inputs/cards/iconos (ver ADR 0002).
- **Branching**: GitFlow simplificado — ver `CONTRIBUTING.md` y `docs/decisiones/0006-gitflow-simplificado.md`.
- **Tuteo estándar, nunca voseo ni "usted".** Todo texto visible al usuario (labels, placeholders, mensajes, botones, errores, PDFs, respuestas de la API) va en segunda persona singular neutra — "Selecciona", "Confirma", "Elige" — nunca voseo rioplatense ("Seleccioná") ni la forma formal de "usted" ("Seleccione"). Vive en `lang/es/*.php`, nunca hardcodeado: ni en Blade, ni en controladores, ni en FormRequests, ni en excepciones (que lo leen con `Texto::de()`), ni en JS (que lo recibe del Blade por `data-*`). A un componente Blade el texto se le pasa enlazado (`:label="__('x')"`), nunca con `{{ }}` (doble escape). Las dos cosas tienen compuerta en `tests/Unit` (`RedaccionNeutraTest`, `EscapePropsBladeTest`). Ver skill `redaccion-neutra` para la tabla de conversión, los verbos irregulares y dónde va cada clave.

## Testing

No hay suite de tests funcionales (`tests/Feature/`): a partir del 15/9/2026 el desarrollador prueba cada cambio a mano, en vivo, contra el compose real — dejó de confiar en que una suite automática de caja negra/blanca sobre la API reflejara que el sistema hace lo que se pide. `tests/Unit/` se mantiene (arquitectura de módulos, máquinas de estado, bitácora de auditoría, reglas de dominio puras — nada que ejercite HTTP) y sigue corriendo en `bin/verify`.

**Se verifica lo afectado, no la suite entera.** `./bin/verify` corre Pint sobre lo tocado, Larastan y las pruebas que el selector de impacto (`bin/tests-afectados`) elige para el diff, más un humo mínimo que corre siempre (arquitectura de módulos y contrato de API). Ni personas ni agentes corren la suite completa por defecto: `./bin/verify --todo` es para cuando el selector ya lo exige, un release o reproducir una falla nocturna. En CI un PR a `develop` corre lo afectado y la suite completa corre en el release a `master`, de noche y con la etiqueta `full-ci`. El selector falla siempre hacia más pruebas, nunca hacia menos. Detalle en `docs/gestion/ci-por-impacto.md`.

Lo que sí hay que escribir y mantener es una suite de **no funcionales de seguridad**: inyección, y exposición de datos entre usuarios (un usuario logueado viendo o alcanzando por ID un recurso que no le corresponde — el caso del portal del invariante 5 es el ejemplo central). Esa es la categoría de prueba que se valida hoy, no el flujo funcional completo.

## Qué no delegar sin revisión línea por línea

El motor de sync, el servicio de estados y el scoping del portal del cliente. El resto (CRUDs, pantallas no críticas, recursos de listado, plantillas PDF) se revisa por diff en el PR y por prueba manual del usuario.

**Esa revisión es posterior a la integración, no previa.** Se hace sobre `develop`, con el cambio ya mergeado, y queda anotada en `runs/revision-pendiente.txt`. Retener el PR en borrador hasta que una persona lo mirara fue peor que el problema que resolvía: el PR #46 (motor de sync) quedó esperando, y como toda rama nueva sale de `develop`, bloqueó doce HU de los sprints 2 a 5 hasta que el ciclo se quedó sin trabajo y se detuvo solo. Un cambio crítico sin revisar es un riesgo acotado y visible; una rama que no entra bloquea todo lo que viene detrás.
