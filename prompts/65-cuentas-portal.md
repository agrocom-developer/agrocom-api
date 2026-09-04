<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/cuentas-portal etapas=3 -->

# Tarea 65 — cuentas del portal desde el panel y dos clientes demo para probar el aislamiento

## Por qué esta tarea

HU-41 (portal del cliente, tarea 55) ya está integrada (PR #106, 4/9/2026):
el scoping por contrato y los 404 cruzados están cubiertos por tests. Pero
para que el usuario lo pruebe a mano faltan dos cosas: **crear cuentas de
portal sin tocar la base** (hoy `UsuariosController` fija `type = interno`
siempre y `ListarUsuarios` filtra `interno`, así que las cuentas `cliente`
son invisibles desde el panel) y **datos demo con dos clientes distintos**,
cada uno con su usuario, para ver con los ojos que B no ve lo de A. Hoy el
único usuario de portal (`cliente.visual.portal`) lo crea
`tests/Visual/fixtures/portal-demo.php`, que solo corre desde Playwright.

Es crítica: toca cuentas del portal y el scoping es de la lista de
`CLAUDE.md`. Se implementa igual y el PR queda anotado para revisión.

## Lo que ya existe

- `sec_user.type` (`interno` | `cliente`), `contrato_id` (FK
  `com_contratos`), check constraint en Postgres: un `cliente` tiene
  `contrato_id` y no `persona_id`; un `interno` al revés. `SecUsuarioCliente
  extends SecUser` filtra por tipo. Guard `cliente` en `config/auth.php`.
- Una cuenta de portal **no tiene roles**: no pasa por `sec_user_role` ni
  por `rol.activo`. Su única "pertenencia" es el contrato.
- `AutorizacionPortalClienteSesion::contratoId()` es la única fuente de
  scoping del portal (invariante 5). No la toques.
- `database/seeders/Demo/`: `NucleoComercialSeeder` (un solo cliente,
  `Agropecuaria San Jorge S.R.L.`, con contrato, campos, lotes, órdenes y 3
  contactos con email), `PanelDemoSeeder` (`camila.rojas` multirol),
  `DemoSeeder` que los encadena. `migrate --seed` es idempotente por
  `firstOrCreate`.
- `tests/Visual/fixtures/portal-demo.php` ya arma un cliente con acta
  firmada y reporte técnico **por el flujo real** (`GenerarActaTrabajo` →
  `FirmarActa`, que dispara el reporte). Es el modelo a seguir para los
  datos demo; nunca `Acta::create()` a mano (invariante 3, hallazgo #2 de
  `runs/55-veredicto.md`).
- `tests/Feature/Portal/PortalClienteTest.php` tiene el helper
  `contratoParaPortal()` para crear clientes A y B en tests.

## Qué hacer

Cargá las skills `seguridad-roles`, `dominio-backend`, `modelo-datos` y
`verificacion`.

1. **Cuentas de portal en el ABM de usuarios.** `ListarUsuarios` deja de
   filtrar por tipo y el listado muestra la columna "Tipo" con filtro
   (`?tipo=interno|cliente`). El formulario de alta pide el tipo; si es
   `cliente`, en vez de persona y roles pide **cliente → contrato** (select
   dependiente: contratos vigentes del cliente elegido) y ningún rol. La
   edición no permite cambiar el tipo (una cuenta no muta de interna a
   cliente ni al revés: es otra cuenta). Permisos: los mismos
   `seguridad.usuario.*`, más uno nuevo `seguridad.usuario.portal` que se
   exige además para crear/editar cuentas `cliente` (solo `dueno` y
   `encargado_operaciones`). Bloqueo/desbloqueo y reset de contraseña
   funcionan igual para ambos tipos.
2. **Caso de uso `CrearCuentaPortal`** (o extensión de `AsignarRolesUsuario`
   si queda más claro, pero sin mezclar las guardas de roles con las de
   contrato): valida que el contrato exista y esté vigente, que `persona_id`
   quede null, y que no haya ya una cuenta viva para ese contrato **salvo**
   que el negocio lo permita — decidilo leyendo
   `docs/especificacion/especificacion_funcional_tecnica.md` (portal del
   cliente); si no dice nada, permití varias cuentas por contrato y dejalo
   anotado en `runs/65.md`.
3. **Seeder `Demo\PortalDemoSeeder`**, encadenado desde `DemoSeeder`:
   - Cliente A: reusa `Agropecuaria San Jorge S.R.L.` y su contrato del
     `NucleoComercialSeeder`; agrega, por el flujo real, al menos una sesión
     validada, un acta firmada y su reporte técnico, para que avance, actas
     y reportes del portal muestren algo.
   - Cliente B: cliente nuevo (`Estancia La Esperanza S.A.` o similar), con
     contrato de hectáreas y precio claramente distintos, campo, lote,
     orden, trabajo, sesión validada, acta firmada y reporte, también por el
     flujo real.
   - Dos cuentas: `cliente.sanjorge` y `cliente.esperanza`, contraseña
     `password`, `type = cliente`, cada una con su `contrato_id`. Nombres y
     contraseñas quedan documentados en `docs/gestion/estado_proyecto.md`
     (sección de datos demo) junto a `camila.rojas`.
   - Idempotente por `firstOrCreate`/guardas tempranas, como los otros dos.
   - `tests/Visual/fixtures/portal-demo.php` puede seguir existiendo para
     Playwright, pero si el seeder ya deja lo que las specs del portal
     necesitan, simplificalo para que delegue en el seeder y no duplique.
4. **Guion de prueba manual** en `docs/gestion/estado_proyecto.md` (o un
   `docs/gestion/prueba_portal.md` enlazado desde ahí): entrar como A,
   anotar hectáreas/actas/reportes y el id de un acta; salir; entrar como B;
   verificar que avance y listados son otros y que `GET
   /portal/actas/{id_de_A}/pdf` responde 404. Diez líneas, no más.

## Qué NO hacer

- No toques `AutorizacionPortalClienteSesion`, los controladores del portal
  ni sus tests de scoping: ya están verificados. Si algo del portal te
  parece mal, es un hallazgo para `runs/65.md`, no un cambio.
- No des roles a las cuentas de portal ni las hagas pasar por
  `seleccionar-rol`.
- No crees las actas/reportes demo con `create()` directo: siempre por
  `GenerarActaTrabajo` y `FirmarActa`.
- No borres ni renombres datos demo existentes (`camila.rojas`, `demo`, San
  Jorge): se agregan, no se reemplazan (skill `verificacion`, "los datos
  demo no se borran").

## Cómo repartir las etapas

- **Etapa 1**: ABM de cuentas `cliente` en usuarios (listado con tipo,
  formulario dependiente cliente → contrato, caso de uso, permiso nuevo),
  tests Feature (crear cuenta cliente con contrato vigente; rechazar sin
  contrato o con persona; 403 sin `seguridad.usuario.portal`; una cuenta
  cliente creada así puede loguearse en `/portal/login` y ve solo lo de su
  contrato).
- **Etapa 2**: `PortalDemoSeeder` con A y B por el flujo real, encadenado en
  `DemoSeeder`, test de idempotencia (dos corridas, mismos conteos) y test
  de que las dos cuentas demo ven datos distintos y 404 cruzado.
- **Etapa 3**: guion manual, actualización de `usuarios.spec.ts` (el listado
  cambia) y de las specs del portal si el fixture se simplificó,
  `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- `docker compose exec -T app php artisan migrate --seed --force` dos veces
  seguidas deja exactamente dos cuentas `type = cliente` demo y no duplica
  actas ni reportes.
- Test: `cliente.sanjorge` logueado en el portal pide el PDF de un acta de
  `cliente.esperanza` → 404; y al revés.
- Test: `POST /panel/usuarios` con `tipo = cliente` y `contrato_id` de un
  contrato vigente crea la cuenta sin roles y sin persona; con un
  `persona_id` además → 422.
- Test: un `encargado_operaciones` sin `seguridad.usuario.portal` (quitado en
  el test) recibe 403 al crear una cuenta cliente, pero sigue pudiendo crear
  internas.

## Puede tocar

`app/Dominios/Seguridad/**` (usuarios), `database/seeders/Demo/**`,
`database/seeders/Catalogo/SeguridadSeeder.php`, `lang/es/**`,
`resources/**` (formulario de usuarios), `docs/gestion/estado_proyecto.md`,
`tests/**` (incluido `tests/Visual/fixtures/portal-demo.php`).

Fuera de alcance: `app/Dominios/Portal/**` salvo lectura.

## Cierre obligatorio de cada etapa

`runs/65.estado`, `runs/65.md`, y al `OK` `runs/65.pr.md`. Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.
