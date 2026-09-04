<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/reportes-tecnicos-listado etapas=3 -->

# Tarea 57 — HU-43: listado de reportes técnicos

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§252): "Como encargado, quiero listar y
descargar los reportes técnicos generados, para reenviarlos al agrónomo."
Criterio: pantalla sobre `ope_reportes_tecnicos` con filtro por cliente y
período.

Independiente de las tareas 55 (portal) y 56 (galería de evidencias): no
comparte archivos con ninguna de las dos, aunque las tres viven en
`Operaciones`/`Comercial`. Si 55 ya integró
`Operaciones/Contratos/LecturaReporteTecnico`, **reusalo en vez de crear
otro** — mirá si existe antes de escribir el tuyo. Si no está integrada
todavía, escribilo vos (ver punto 1).

No es crítica: pantalla de solo lectura.

## Lo que ya existe

- `ope_reportes_tecnicos` y su modelo `ReporteTecnico` (con relación
  `trabajo()`) ya existen desde HU-18 (tarea 25). La descarga individual ya
  funciona: `GET /panel/trabajos/{trabajo}/reporte/pdf`
  (`TrabajosController::reporteTecnicoPdf`, permiso
  `operaciones.reporte.ver`) — **no la toques**, esta tarea agrega el
  LISTADO, no cambia la descarga existente.
- El ítem de menú **ya está sembrado como placeholder**:
  `SecMenuSeeder.php`, grupo `reportes`,
  `$this->item($reportes, 'reportes', 'tecnicos', 'summarize', 1);` (sin
  `ruta` ni `codigoPermiso` todavía — se pinta como "botón sin link"). Mismo
  patrón que activaron las tareas 26/40/44/45/46: **no crees un ítem
  nuevo**, activá este con `ruta: 'panel.reportes.tecnicos.index'` y
  `codigoPermiso: 'operaciones.reporte.ver'` (reusá el permiso existente:
  misma acción de negocio — "ver/descargar reporte técnico" — que ya gatea
  la descarga individual).
- La cadena para resolver el contrato/cliente de un reporte es la misma que
  usa `LecturaActaConformadaEloquent`:
  `ReporteTecnico.trabajo_id → Trabajo.orden_id/lote_id →
  OrdenAplicacion.contrato_id → Contrato.cliente_id`. Las tres primeras
  tablas viven en `Operaciones`; `Contrato`/`Cliente` viven en `Comercial`.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

### 1. Contrato de lectura (si la tarea 55 no lo dejó ya integrado)

`Operaciones/Contratos/LecturaReporteTecnico` (+ DTO `DatosReporteTecnico`:
`reporteId`, `trabajoId`, `contratoId`, `loteId`, `horaInicio`, `horaFin`,
`pdfPath`, `generadoEn`), implementación Eloquent en
`Operaciones/Infraestructura/`, mismo molde que
`LecturaActaConformadaEloquent`: sin JOIN optimizado, tres consultas por
reporte, "el volumen no lo justifica". Bindealo en
`OperacionesServiceProvider`. Necesitás un método que liste TODOS los
reportes (no solo por contrato) con su `contratoId` resuelto, para que el
caso de uso del punto 2 filtre después.

### 2. Filtro por cliente: contrato de lectura en sentido inverso

El nombre del cliente y el filtro "por cliente" necesitan datos de
`Comercial` (`Contrato.cliente_id`, `Cliente.razon_social`), que
`Operaciones` no puede importar como modelo Eloquent ajeno (ADR 0003:
"entre módulos se viaja por contratos o eventos de dominio, nunca por
modelos ajenos"). No existe hoy ningún contrato de lectura en el sentido
`Comercial → Operaciones` (los que existen van al revés: `Operaciones`
expone `LecturaActaConformada`/`LecturaOrdenesVigentes` hacia `Comercial`).
Creá uno chico en `Comercial/Contratos/` (p. ej. `LecturaContrato`, método
`obtenerResumen(int $contratoId): ?DatosResumenContrato` con `contratoId`,
`clienteId`, `clienteNombre`), implementación Eloquent en
`Comercial/Infraestructura/`, bindealo en el `ServiceProvider` de
`Comercial`. El caso de uso nuevo (`Operaciones/Aplicacion/ListarReportesTecnicos`,
por ejemplo) compone: lista del punto 1 → por cada uno, `obtenerResumen()`
para el nombre de cliente y el filtro.

### 3. Pantalla

`ReportesTecnicosController` nuevo (o extendé uno existente si tiene
sentido) en `Operaciones/Infraestructura/Http/Controllers/Web/`, ruta
`GET /panel/reportes/tecnicos`, permiso `operaciones.reporte.ver`. Filtros
`cliente_id` y período (`generado_en` entre desde/hasta), mismo patrón de
`<select>`/inputs de fecha que `DevengosController`/`ReportesComercialesController`.
Tabla con: trabajo (lote, nro. aplicación — via `trabajo`), cliente,
fecha de generación, link de descarga (reusa la ruta existente
`panel.trabajos.reporte.pdf`, no dupliques el streaming del PDF).

## Qué NO hacer

- No toques `TrabajosController::reporteTecnicoPdf()` — la descarga
  individual ya funciona, esta tarea solo agrega el listado que enlaza a
  ella.
- No importes `Comercial\Infraestructura\Eloquent\Contrato` ni `Cliente`
  directo desde `Operaciones` — usá el contrato de lectura del punto 2.
- No crees un ítem de menú nuevo — activá el placeholder ya sembrado
  (`reportes` → `tecnicos`).
- No agregues un permiso nuevo — reusá `operaciones.reporte.ver`.
- No modifiques `LecturaActaConformada` — esta tarea no la necesita.

## Cómo repartir las etapas

- **Etapa 1**: `LecturaReporteTecnico` (si no existe ya, ver punto 1) +
  `LecturaContrato`/`obtenerResumen` (punto 2), con tests unitarios.
- **Etapa 2**: caso de uso `ListarReportesTecnicos`, controlador, ruta,
  activación del ítem de menú, vista con filtros.
- **Etapa 3**: tests Feature (filtro por cliente, filtro por período, 403
  sin permiso, ítem de menú gateado), spec visual, checklist §8,
  `bin/verify` completo.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature: listado completo sin filtro; filtro por `cliente_id` trae
  solo los reportes de contratos de ese cliente; filtro por período
  (desde/hasta sobre `generado_en`) funciona; 403 sin
  `operaciones.reporte.ver`; el link de descarga de cada fila resuelve al
  PDF real ya existente.
- Spec visual (`tests/Visual/reportes-tecnicos.spec.ts` o nombre
  equivalente), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Operaciones/**` (contrato de lectura nuevo, caso de uso,
controlador, vista), `app/Dominios/Comercial/Contratos/**` + su
implementación (el contrato de lectura inverso), `routes/web.php`,
`database/seeders/Catalogo/SecMenuSeeder.php` (solo activar el placeholder
`tecnicos`), `lang/es/operaciones.php`, `tests/**`.

Fuera de alcance: `TrabajosController::reporteTecnicoPdf()`,
`SeguridadSeeder.php` (el permiso ya existe), cualquier migración.
