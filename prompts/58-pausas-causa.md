<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/pausas-causa etapas=4 -->

# Tarea 58 — HU-44: pausas con causa atribuible

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§253): "Como jefe de campo, quiero registrar las
pausas con su causa atribuible (DS-01), para saber qué tiempo se pierde y por
qué." Criterio: pausa ligada a sesión con causa de catálogo; agregado por
causa en el tablero.

`DS-01` (`docs/gestion/respuestas_campo/analisis_clasificacion.md:52`) es un
hallazgo de campo, no una ocurrencia: "las pausas y demoras nunca se
registran... es la información que falta siempre... donde se pierden horas".
Decisión ya tomada ahí: "entra en v1: entidad `pausas` con causa atribuible
(incluye `imprevisto_del_cliente`)". No es una idea nueva de esta tarea, es
la que quedó pendiente de implementar.

No es crítica: no mueve dinero ni cambia estado de trabajo/sesión, es un
registro y su lectura agregada.

## Lo que ya existe

- El ítem de menú **ya está sembrado como placeholder**: `SecMenuSeeder.php`,
  módulo `operacion`, `$this->item($operacion, 'operacion', 'pausas',
  'pause_circle', 5);` (sin `ruta` ni `codigoPermiso` todavía). Mismo patrón
  que activaron las tareas 26/40/44/45/46/53/54: **no crees un ítem nuevo**,
  activá este.
- La maqueta de referencia (`DatosDemoPanel::pausas()`,
  `app/Dominios/Seguridad/Infraestructura/Http/Demo/DatosDemoPanel.php:227`)
  muestra la forma que el negocio espera: un total del período y una lista de
  causas con horas acumuladas — usala como guía de qué agregar, no como
  fuente de datos (es mock, se reemplaza recién en la tarea 60/TE-14, que
  depende de que esta exista).
- `ope_sesiones` (`database/migrations/2026_09_01_100002_create_ope_sesiones_table.php`)
  es la entidad a la que la pausa se liga — mismo patrón de FK plana +
  `restrictOnDelete()` que usan `ope_recargas`/`ope_incidencias`.
- No existe ningún permiso `operaciones.pausa.*` todavía en
  `database/seeders/Catalogo/SeguridadSeeder.php` — lo creás vos.

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

Migración nueva `ope_pausas` en `Operaciones` (prefijo `ope_`, ADR 0011):
`sesion_id` (FK a `ope_sesiones`, `restrictOnDelete`), `causa` — decidí vos
si es un `string` acotado por `CHECK`/enum de aplicación o una tabla catálogo
separada (`ope_causas_pausa`); el catálogo mínimo de causas según DS-01 y la
maqueta: clima fuera de rango, insumos del cliente que no llegan
(`imprevisto_del_cliente`, mención explícita de DS-01), cambio de lote
ordenado por el cliente, falla de equipo, logística/traslados. Sumá
`inicio`/`fin` (`dateTime`, con el mismo cuidado de `->utc()` que ya corrigió
la tarea 29 — no repitas el bug de timezone) y `duracion_minutos` o
calculala al leer, decisión tuya con su porqué. Auditoría + soft delete
(invariante 8/9) como todo modelo de dominio.

Si una pausa nace en la app de campo (vía sync) o solo desde el panel: la HU
dice "jefe de campo" y no menciona el motor de sync ni `uuid_cliente` —
tratala como alta desde el panel (como pausas/rendiciones/gastos), no como
un tipo nuevo de registro de `EscrituraSincronizacionEloquent`. Si encontrás
evidencia real en la especificación de que nace en campo, decilo en
`runs/58.md` y ajustá, pero no lo asumas de entrada.

### 2. Caso de uso y permisos

`Operaciones/Aplicacion/RegistrarPausa` (o nombre equivalente) + caso de uso
de listado/agregación por causa. Permisos nuevos en `SeguridadSeeder.php`
(`operaciones.pausa.ver`, `operaciones.pausa.registrar`, mismo patrón que
`operaciones.sesion.validar`) asignados a `jefe_campo` (dueño de la HU) y a
`encargado` (ya comparte `trabajo.ver`/`sesion.validar` con jefe_campo en el
seeder, línea ~290 — mismo criterio si aplica acá).

### 3. Pantalla

Controlador nuevo, ruta `GET /panel/pausas` (listado + alta) o el patrón que
ya usan las pantallas de registro simple del panel (revisá
`GastosController`/`RendicionesController` como referencia de forma).
Activá el ítem de menú `operacion.pausas` con `ruta` y `codigoPermiso:
'operaciones.pausa.ver'`. El "tablero" del CA es el agregado por causa
(horas totales por causa en el período) — mismo shape que
`_barras-pausas.blade.php`, pero podés adaptarlo a tabla si el componente de
barras no calza bien con datos reales variables.

## Qué NO hacer

- No toques `DatosDemoPanel::pausas()` ni el dashboard — eso es la tarea 60
  (TE-14), que depende de que esta pantalla exista primero.
- No crees un ítem de menú nuevo — activá el placeholder `operacion.pausas`.
- No metas la pausa en el motor de sync salvo que confirmes que nace en
  campo (ver punto 1).
- No uses `float` para horas/minutos si guardás duración como decimal — usá
  `DECIMAL` o enteros de minutos (invariante 6 es sobre dinero/hectáreas
  específicamente, pero el mismo cuidado de exactitud aplica a lo que se va
  a sumar y mostrar).

## Cómo repartir las etapas

- **Etapa 1**: migración `ope_pausas` (+ catálogo de causas si es tabla
  separada), modelo Eloquent, tests unitarios de guarda (`fin > inicio`,
  causa válida).
- **Etapa 2**: caso de uso de alta + caso de uso de agregación por causa,
  permisos en `SeguridadSeeder`.
- **Etapa 3**: controlador, ruta, vista, activación del ítem de menú.
- **Etapa 4**: tests Feature (alta válida, 403 sin permiso, agregado por
  causa exacto), spec visual, checklist §8, `bin/verify` completo.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature: alta de pausa válida liga `sesion_id` correcto; causa fuera
  del catálogo/enum rechazada; 403 sin el permiso nuevo; el agregado por
  causa devuelve la suma exacta de horas/minutos de las pausas del período
  filtrado.
- Spec visual (`tests/Visual/pausas.spec.ts` o equivalente), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Operaciones/**` (migración, modelo, caso de uso, controlador,
vista), `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php` (solo activar el placeholder
`pausas`), `routes/web.php`, `lang/es/operaciones.php`, `tests/**`.

Fuera de alcance: `DatosDemoPanel`, el motor de sync, cualquier tabla fuera
de `Operaciones`.
