<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/reporte-avance-comercial etapas=3 -->

# Tarea 46 — HU-32: reporte comercial de avance

## Por qué esta tarea

`plan_sprints.md` Sprint 9 (§206): "Como dueño, quiero un reporte comercial
de avance por cliente, contrato y campaña, para saber cuánto queda por
aplicar y por cobrar." Criterio: hectáreas contratadas vs. aplicadas vs.
facturadas por contrato; exportable. Cierra Sprint 9 (HU-31/facturas, tarea
45, ya integrada — PR #91).

**No es crítica**: es una pantalla de solo lectura que agrega datos ya
persistidos, no un listener que genera dinero.

**Módulo dueño: `Comercial`**, mismo criterio que HU-31 — el dato de avance
comercial es del lado del dinero que entra. Reusa el contrato de lectura que
ya existe (`Operaciones/Contratos/LecturaActaConformada`), no crees uno
nuevo.

## Qué hacer

Cargá las skills `modelo-datos` (si hace falta alguna vista o índice),
`dominio-backend`, `panel-design-ui` y `verificacion`.

### 1. Caso de uso de agregación

Nuevo caso de uso en `Comercial/Aplicacion/` (p. ej.
`ObtenerAvanceComercial.php`) que, por contrato:

- **Hectáreas contratadas**: `Contrato.hectareas_contratadas` (ya existe,
  columna propia de `Comercial`).
- **Hectáreas aplicadas**: suma de `hectareasConformadas` de las actas
  firmadas de ese contrato. Usá
  `Operaciones/Contratos/LecturaActaConformada::listarFirmadas()` (ya
  implementado en la tarea 45) y agrupá por `contratoId` del DTO
  `DatosActaConformada` — **no** filtres por facturadas o no: un acta
  firmada es hectárea aplicada, esté facturada o todavía no.
- **Hectáreas facturadas** y **monto facturado**: `sum()` sobre
  `Comercial\Infraestructura\Eloquent\Factura` filtrado por `contrato_id` —
  dato propio del módulo, sin cruzar nada.

Sumá con `Brick\Math\BigDecimal` (invariante 6 — nunca floats; ya es
dependencia de Laravel, ver
[[bcmath-no-instalado-usar-brick-math]] si la tenés en memoria). El
resultado es una lista por contrato con: cliente (nombre, vía
`Contrato->cliente`), hectáreas contratadas/aplicadas/facturadas, monto
facturado.

### 2. Pantalla y filtro

`panel/reportes/comercial`, `ReportesComercialesController@index`. Filtro
por cliente y/o contrato (`<select>`, mismo patrón de filtros ya usado en
`ListarTrabajos`/`DevengosController`). Tabla con las columnas del punto 1.

Activá el ítem **`comerciales`** que ya está sembrado como "botón sin link"
en el grupo `reportes` de `SecMenuSeeder.php` (línea ~133) — no crees uno
nuevo, mismo patrón que usaron las tareas 26/40/44/45 con sus propios
ítems.

Permiso nuevo `comercial.reporte.ver`. **No** lo agregues a
`PERMISOS_ENCARGADO_OPERACIONES`: la historia es literal "como dueño", mismo
criterio ya usado para `finanzas.planilla.aprobar` (exclusivo del dueño, que
recibe todo el catálogo automáticamente por diseño §2 — no hace falta
tocar la constante).

### 3. Exportación CSV

"Exportable" es el criterio esencial de esta HU. No hay ninguna librería de
Excel instalada (`composer.json` no trae `maatwebsite/excel` ni similar) —
**no la agregues**. Un endpoint `GET /panel/reportes/comercial/exportar`
(mismo controlador, mismo permiso) que arma un CSV con
`Illuminate\Http\Response`/`StreamedResponse` nativo de Laravel, mismas
columnas que la tabla, respetando el filtro activo si lo hay.

## Qué NO hacer

- No crees una entidad `Campaña` ni una tabla nueva para ella — el CA
  esencial de esta HU no la exige (solo agrega por contrato); "campaña" en
  el enunciado es la campaña agrícola en curso, no un concepto del modelo
  de datos todavía.
- No toques `LecturaActaConformada` ni su implementación — ya expone lo que
  esta tarea necesita (`listarFirmadas()`).
- No agregues una librería de generación de Excel/CSV — `StreamedResponse`
  nativo alcanza.
- No calcules con floats en ningún paso de la agregación.

## Cómo repartir las etapas

- **Etapa 1**: caso de uso de agregación + sus tests unitarios de cálculo
  (con `Brick\Math`).
- **Etapa 2**: controller, ruta, permiso, menú, vista con filtro.
- **Etapa 3**: exportación CSV, tests Feature, spec visual, checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test (Feature o Unit según corresponda) cubriendo: agregación exacta por
  contrato (hectáreas contratadas/aplicadas/facturadas, monto, sin error de
  redondeo flotante); un contrato sin actas firmadas aparece con aplicadas =
  0, no se cae; filtro por cliente/contrato funciona; 403 sin el permiso;
  la exportación CSV devuelve las filas esperadas con el filtro aplicado.
- Spec visual (`tests/Visual/reporte-avance-comercial.spec.ts` o nombre
  equivalente), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Comercial/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/comercial.php`,
`tests/**`.

Fuera de alcance: cualquier cambio a `Operaciones/Contratos/LecturaActaConformada`,
a la máquina de estados de contrato/acta, o al modelo `Factura`.
