<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/informe-contratos etapas=4 -->

# Tarea 75 — HU-52: informe de avance de contratos, por cultivo y por cliente

## Por qué esta tarea

El dueño pasó como referencia el manual de contratos de producción de
`synagroweb.com/manual/contrato-de-produccion/` y la decisión de alcance ya está
tomada (ADR 0015, alternativas descartadas): **se toma la forma del informe, no
el modelo**. Agrocom vende servicio de aplicación, no compra grano — donde ese
sistema pone kilos pactados y entregados, este pone **hectáreas pactadas y
aplicadas**.

Lo que existe hoy (`ObtenerAvanceComercial`, HU-32) responde la misma pregunta
pero plano: una fila por contrato, sin agrupar, sin filtros y sin cultivo — su
propio enunciado prometía "por cliente, contrato **y campaña**" y la campaña no
existía. Ahora existe (tarea 69) y el cultivo también (tarea 71).

La especificación de la pantalla está en **§9.1** de
`docs/especificacion/especificacion_funcional_tecnica.md`. Es la fuente del
alcance; leela entera antes de maquetar.

Depende de las tareas 69 (campaña) y 71 (cultivo).

## Lo que ya existe

- `Comercial/Aplicacion/ObtenerAvanceComercial.php` — **la cuenta ya está
  resuelta y es la buena**: hectáreas contratadas de `com_contratos`, aplicadas
  como suma de `hectareasConformadas` de las actas firmadas
  (`LecturaActaConformada::listarFirmadas()`, ADR 0003 regla 2), facturadas de
  `com_facturas`. Todo con `Brick\Math\BigDecimal`, **nunca con `SUM()` de
  SQL**, y el docblock explica por qué. Extendé esa clase o construí sobre
  ella; **no escribas una segunda fórmula de avance** — el dashboard, el portal
  y este informe no pueden decir números distintos.
- `Comercial/Contratos/AvanceClientePanel.php` — el DTO que ya usa el
  dashboard, con `porcentaje` entero redondeado "para pintar una barra, no para
  cuadrar".
- `Comercial/Contratos/LecturaCultivoLote` (tarea 71).
- `Views/pages/reportes-comerciales/index.blade.php` — la pantalla actual.
- Los skills `panel-design-ui` (tokens, catálogo de componentes, reglas de
  pulido ya confirmadas) y `verificacion`. Cargalos antes de tocar Blade o CSS.

## Qué hacer

1. **Pantalla de inicio** con dos selectores múltiples, **cliente** y
   **cultivo**, sin valor por defecto. Hasta que haya al menos uno de cada uno:
   los botones "Filtros" y "Generar" quedan deshabilitados y el selector vacío
   muestra su mensaje de error debajo. Estado vacío ilustrado hasta la primera
   consulta, y también cuando la consulta no devuelve nada.
2. **Pantalla de filtros** aparte (no editables desde los chips):
   - Campaña (múltiple, **por defecto la campaña activa de la sesión**,
     obligatoria).
   - Rango de fechas (desde / hasta), estado del contrato, saldo
     (`a aplicar` / `cumplido` / `pendiente`), e "incluir contratos
     deshabilitados" apagado por defecto.
   - Botones: aplicar (guarda, cierra y genera), limpiar (vuelve a los valores
     por defecto) y cancelar (descarta y no genera).
3. **Chips de filtros aplicados** en la pantalla principal, en carrusel
   horizontal. Los múltiples muestran nombre y cantidad (`Campaña (3)`). Quitar
   un chip regenera el informe sin ese filtro. Desde el chip no se edita nada,
   solo se quita.
4. **Dos pestañas**: *Por cultivo* (por defecto) y *Por cliente*.
   - **Por cultivo**: un grupo por cultivo, con barra de avance del cultivo, y
     debajo sus contratos con las columnas contrato (nombre a 12 caracteres con
     puntos suspensivos si excede), hectáreas pactadas, aplicadas y **a
     aplicar**. Totalizador de "a aplicar" al pie de cada cultivo. Orden por
     vencimiento más cercano.
   - **Por cliente**: mismo agrupamiento por cultivo, y dentro, cliente en
     tarjeta colapsable — todas cerradas por defecto, **una sola abierta a la
     vez**. Al abrirla, barra del cliente y su lista de contratos con las mismas
     columnas y su totalizador.
5. **Barra de avance por tramos**, con **un token CSS por tramo** — 0-33 %,
   34-66 %, 67-99 %, 100 % y más de 100 %. **Ningún color hardcodeado**
   (invariante 11): definí los cinco tokens en la capa de tokens del panel, con
   su variante para tema claro y oscuro, y verificá contraste en ambos. El
   quinto tramo (más de 100 %) existe porque se puede aplicar de más — no lo
   elimines por "no debería pasar".
6. **Números**: hectáreas con separador de miles y los decimales que ya usa el
   panel; magnitudes como string decimal de punta a punta (invariante 6). El
   porcentaje es entero redondeado y sirve **solo** para pintar la barra.
7. **Traducciones** en `lang/es/comercial.php`. Nada de texto en la vista.

## Qué NO hacer

- **No modeles kilos, corredores, vencimientos de entrega ni saldos de grano.**
  Del manual de synagro se toma la interacción, no el dominio.
- No escribas una segunda fórmula de avance ni dupliques la suma de actas.
- No uses `SUM()` de SQL para hectáreas.
- No hardcodees ningún color de la barra, ni siquiera "temporalmente".
- No toques el portal del cliente: este informe es interno (§13 es otra cosa) y
  el portal tiene su propia regla de scoping por contrato (invariante 5).

## Cómo repartir las etapas

- **Etapa 1**: caso de uso de consulta sobre `ObtenerAvanceComercial`, con
  agrupación por cultivo y por cliente y los filtros; tests unitarios de la
  cuenta y del orden.
- **Etapa 2**: tokens de los cinco tramos (claro y oscuro) y el componente de
  barra, en el catálogo de Atomic Design que corresponda.
- **Etapa 3**: pantalla de inicio, filtros, chips, pestañas y tarjetas
  colapsables; tests Feature.
- **Etapa 4**: estado vacío, traducciones, snapshots visuales, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: sin cliente **o** sin cultivo seleccionado, "Generar" no produce
  informe y aparece el mensaje bajo el selector que falta.
- Test: el total de "a aplicar" de un grupo cuadra exacto contra la suma de sus
  contratos (comparación de strings decimales).
- Test: el avance que devuelve este informe para un contrato es **idéntico** al
  que devuelve `ObtenerAvanceComercial` para ese mismo contrato.
- Test: quitar el chip de un filtro regenera el informe sin ese filtro.
- Test: los contratos de un grupo salen ordenados por vencimiento más cercano.
- Test: un contrato con 100 % y otro con 120 % caen en tramos de color
  distintos.
- `grep -rnE "#[0-9a-fA-F]{3,6}" ` sobre las vistas y el CSS nuevos no devuelve
  nada.

## Puede tocar

`app/Dominios/Comercial/**`, `resources/views/components/**` (átomos/moléculas
de barra y chips), `resources/css/**` (tokens), `lang/es/comercial.php`,
`routes/web.php`, `tests/**`, `tests/Visual/**`.

Fuera de alcance: `app/Dominios/Portal/**`, `fin_*`, `per_*`, `ope_*` (solo se
leen por contrato).

## Cierre obligatorio de cada etapa

`runs/75.estado`, `runs/75.md`, y al `OK` `runs/75.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
