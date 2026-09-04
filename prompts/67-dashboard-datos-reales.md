<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/dashboard-datos-reales etapas=5 -->

# Tarea 67 — el dashboard deja de ser maqueta: datos reales de la base y datos demo sembrados

## Por qué esta tarea

`GET /panel/dashboard` es la pantalla de aterrizaje del panel y hoy es
íntegramente mock: `DashboardController` arma la vista con tres clases
demo de `app/Dominios/Seguridad/Infraestructura/Http/Demo/`
(`DatosDemoPanel`, `DatosDemoMapaOperativo`, `DatosDemoCapturasRc`) que
devuelven arreglos inventados. La tarea 60 ya reemplazó los badges del menú
por contadores reales y dejó escrito que el resto del dashboard "es trabajo
de HUs que no existen todavía". El usuario la pidió el 4/9/2026: quitar los
mockups y que el dashboard lea de la base, con datos demo cargados en
Postgres para que se vea poblado en desarrollo.

No es crítica: es lectura y seeders.

## Lo que ya existe

- `DashboardController::index()` pasa a la vista: `fechaBajada`,
  `ventana` (ventana volable), `distribucion` (sesiones por estado),
  `hectareasPorDia`, `avanceMeta`, `detalleClientes`, `mapaLotes`,
  `mapaSesiones`, `resumenMapa`, `resumenPorLote`, `capturasRc`,
  `sesiones`, `pausas`, `stock`, `alertaRc`. Los parciales están en
  `app/Dominios/Seguridad/Infraestructura/Http/Views/pages/dashboard/`
  (`_tabla-sesiones`, `_fichas-sesiones`, `_barras-pausas`,
  `_detalle-clientes`, `_resumen-por-lote`, `_multimedia-*`) y el organism
  `x-organisms.mapa-operativo`.
- Fuentes reales por sección (verificalas antes de escribir; es un punto de
  partida, no una verdad cerrada):

  | Sección | Fuente real | Nota |
  |---|---|---|
  | Sesiones recientes / por estado | `Operaciones` (`ope_sesiones`, `ope_trabajos`) — ya hay `ListarSesiones`/cola de validación (HU-14) | real |
  | Hectáreas por día | suma de `hectareas_*` de sesiones validadas por fecha, `DECIMAL` (invariante 6) | real |
  | Avance de meta por contrato | `Comercial` — `LecturaContrato`/`DatosResumenContrato` (tarea 55) y el reporte de avance comercial (HU-27) | real |
  | Detalle de clientes | `Comercial` — clientes con contratos vigentes y su avance | real |
  | Pausas por causa | tarea 58, `AgregarPausasPorCausa` | real |
  | Stock bajo mínimo | `Inventario` — la tarea 60 ya expuso `LecturaContadoresPanel` en Inventario | real |
  | Mapa operativo (lotes + sesiones geo) | `com_lotes` / `ope_sesiones` — revisá si hay geometría (`geojson`, lat/lng) en el esquema; si no la hay, el mapa **no se inventa**: se retira y se anota | probablemente se retira |
  | Capturas RC / multimedia | `Operaciones` — `ope_evidencias` (galería de la tarea 56) | real, las últimas N |
  | Alerta RC / ventana volable | no hay fuente (sin telemetría ni meteorología en el dominio) | **se retira** |
  | Fecha de bajada | última sincronización recibida (motor de sync, `SyncController`) — si hay timestamp de la última recepción, es esa; si no, se retira | verificar |

- Seeders demo: `database/seeders/Demo/{NucleoComercialSeeder,PanelDemoSeeder,PortalDemoSeeder}` (esta última de la tarea 65), idempotentes. Los fixtures de `tests/Visual/fixtures/*-demo.php` arman sesiones, pausas, gastos, evidencias, etc. **por el flujo real** (`ValidarSesion`, `FirmarActa`): son la referencia de cómo sembrar sin violar las invariantes 2 y 3.
- `docs/diseno/guia_pantalla_panel.md` y `sistema_diseno_panel.md`: la
  disposición actual del dashboard es la que hay que conservar; cambia el
  origen de los datos, no el diseño.

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`.

1. **Un contrato de lectura por sección, en el módulo dueño del dato.**
   `Seguridad` (donde vive el controlador) no puede consultar tablas de
   otros módulos (ADR 0003): cada sección es un contrato en
   `<Modulo>/Contratos/` con su implementación Eloquent y su DTO de
   primitivos, como hizo la tarea 60 con `LecturaContadoresPanel`. Si un
   caso de uso de listado ya existe (sesiones, pausas por causa, avance de
   contrato, stock), reusalo; no dupliques consultas.
2. **Caso de uso `ArmarDashboard`** (en `Seguridad/Aplicacion/` o en un
   módulo `Panel` si `dominio-backend` lo prefiere) que compone los
   contratos y devuelve un solo DTO. Toda cifra de dinero y hectáreas viaja
   como string decimal, nunca float. Fechas ya convertidas a la zona del
   usuario (tarea 63).
3. **Secciones sin fuente real se retiran** (ventana volable, alerta RC, y
   el mapa si no hay geometría), junto con su parcial y su CSS; no quedan
   con "dato de ejemplo". Dejá en `runs/67.md` la lista de lo retirado y
   por qué, para que el usuario decida si alguna vuelve como HU nueva.
4. **Las tres clases `DatosDemo*` desaparecen** al final de la tarea. Si
   algún test las referencia, se reescribe contra datos reales.
5. **Seeder `Demo\DashboardDemoSeeder`**, encadenado en `DemoSeeder` después
   de los demás: deja en la base del compose lo necesario para que cada
   sección que sobrevive muestre contenido a primera vista — sesiones de
   los últimos 7 días en varios estados (cerradas, validadas, alguna
   abierta) con hectáreas distintas por día, pausas con causa, stock por
   debajo del mínimo en al menos dos repuestos, evidencias con archivo real
   (copiá el patrón de `evidencias-trabajo-demo.php`), avance de meta de
   los dos contratos demo (San Jorge y La Esperanza). Todo por el flujo
   real (`ValidarSesion` para que existan devengos, `FirmarActa` si hace
   falta acta), idempotente (dos corridas, mismos conteos), y **sin borrar
   nada** de lo que ya haya.
6. **Filtro por rol activo.** El dashboard respeta lo que el rol puede ver:
   `detalleClientes` solo con `comercial.cliente.ver`, stock solo con
   `inventario.stock.ver`, etc. — cada sección detrás de `@puede` del
   permiso que ya protege su pantalla propia. El permiso de entrada al
   dashboard es el de la tarea 62 (`seguridad.dashboard.ver`).

## Qué NO hacer

- No inventes un dato para no perder una sección: si no hay fuente, se
  retira (mismo criterio que la tarea 60 con los badges).
- No agregues columnas ni tablas nuevas al dominio solo para alimentar el
  dashboard (por ejemplo, geometría en `com_lotes`): eso es una HU aparte.
- No cambies el diseño/disposición del dashboard: es el mismo con datos
  reales. Las capturas de `dashboard.spec.ts` cambian solo por el contenido
  y por las secciones retiradas.
- No consultes modelos de otros módulos desde `Seguridad` ni desde la
  vista.
- No uses `float` para hectáreas ni dinero en ningún DTO.

## Cómo repartir las etapas

- **Etapa 1**: inventario real de fuentes (verificar la tabla de arriba),
  contratos de lectura para sesiones, hectáreas por día y pausas, con tests
  unitarios de cada consulta.
- **Etapa 2**: contratos para avance de meta, detalle de clientes, stock,
  evidencias recientes, última sincronización; tests.
- **Etapa 3**: `ArmarDashboard` + `DashboardController` sobre datos reales,
  secciones retiradas, `DatosDemo*` eliminadas, `@puede` por sección,
  tests Feature (el dashboard cambia si cambia el dato de origen; un rol
  sin `comercial.cliente.ver` no ve detalle de clientes).
- **Etapa 4**: `DashboardDemoSeeder` por el flujo real, idempotente, test
  de doble corrida.
- **Etapa 5**: `dashboard.spec.ts` regenerado con el seeder cargado (fecha
  fija: el seeder siembra fechas relativas a "hoy", así que el fixture
  visual tiene que fijar el rango o el spec enmascarar solo las fechas —
  elegí lo que no tape datos), `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- `grep -rn "DatosDemo" app/ resources/ tests/` no devuelve nada.
- Test: validar una sesión más (por `ValidarSesion`) cambia "hectáreas por
  día" y el conteo de sesiones del dashboard en el request siguiente.
- Test: registrar una pausa con causa cambia la barra de esa causa.
- Test: un `jefe_campo` (sin `comercial.cliente.ver`) recibe el dashboard
  sin la sección de clientes y sin 403.
- `docker compose exec -T app php artisan migrate --seed --force` dos veces
  deja los mismos conteos en `ope_sesiones`, `ope_pausas`, `inv_stock` y
  `ope_evidencias`.

## Puede tocar

`app/Dominios/Seguridad/**` (dashboard), `app/Dominios/{Operaciones,Comercial,Inventario,Finanzas}/Contratos/**`
y sus implementaciones en `Infraestructura/`, `database/seeders/Demo/**`,
`resources/**` (parciales y CSS del dashboard), `lang/es/**`, `tests/**`.

Fuera de alcance: migraciones de esquema, `ope_drones`, el motor de sync
(solo lectura de su último timestamp si existe).

## Cierre obligatorio de cada etapa

`runs/67.estado`, `runs/67.md`, y al `OK` `runs/67.pr.md`. Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.
