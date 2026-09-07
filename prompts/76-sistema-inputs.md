<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/sistema-inputs etapas=5 -->

# Tarea 76 — HU-53: el sistema de inputs del panel (fecha, select, casillas)

## Por qué esta tarea

Pedido explícito del dueño el 7/9/2026: *"mi input date para las fechas quedó
muy antigua y obsoleta"*, y *"no te olvides usar componentes Material Design y
sus iconos, y si tiene que hacer componentes propios o de terceros para mejorar
la vista, hazlo"*.

El diagnóstico es exacto y el código lo confirma:

- **El átomo `input` no cubre `date`.** `type="date"` cae al selector nativo del
  navegador, que se ve distinto en cada navegador y sistema operativo, no
  respeta ningún token del panel, e ignora el idioma `es` en varios de ellos.
  Hay **11** en el panel.
- **No existe átomo `select`.** El catálogo de `resources/views/components/atoms/`
  tiene `badge`, `button`, `icon`, `input`, `logo` y `switch` — y nada más. Los
  **70** selects del panel son `<select class="ag-input__field">` crudos,
  copiados y pegados, con el `<option disabled>` de placeholder repetido a mano
  en cada pantalla. Ninguno busca, ninguno tiene ícono, y con listas largas
  (repuestos, personas, lotes) son inusables.
- **No existe átomo de casilla.** Los **7** `type="checkbox"` son marcado suelto.

Esta tarea es infraestructura de interfaz: **va temprano en el Sprint 13, justo
después de la 69**, porque todas las pantallas que vienen detrás (campaña,
cultivo, equipos, gastos, informe) construyen formularios, y no tiene sentido
que nazcan con los inputs viejos para migrarlos después.

## Lo que ya existe

- `resources/views/components/atoms/input.blade.php` — leelo entero. Es el
  molde: props ya traducidos por el llamador (ADR 0013), `icon` de Material
  Symbols, dos variantes (`boxed` Material outlined / `line` editorial), y la
  resolución de `$attributes` partida en dos bags que documenta su propio
  docblock. **Los átomos nuevos siguen ese contrato, no inventan otro.**
- `resources/css/components/input.css` y la capa de tokens del panel.
- `docs/diseno/sistema_diseno_panel.md` y `docs/diseno/guia_pantalla_panel.md`
  §3 (la regla de `$attributes` y LSP).
- ADR 0002 — AdminLTE/Bootstrap para estructura, **Material Design para
  inputs, cards e iconografía**. Es exactamente el terreno de esta tarea.
- Los skills `panel-design-ui` (tokens, catálogo, reglas de pulido ya
  confirmadas, verificación visual) y `verificacion`. **Cargalos antes de tocar
  Blade o CSS.**

## Qué hacer

1. **Explorá antes de escribir.** Usá los skills de diseño (`design` /
   `impeccable`) para probar dos o tres direcciones del selector de fecha y del
   select antes de fijar una. No arranques escribiendo Blade.
2. **Átomo `select`**: label, placeholder, ícono, error, help, `required`,
   `disabled`, opciones como `id => etiqueta`, valor seleccionado, y
   **búsqueda dentro del desplegable cuando la lista supera ~8 opciones**.
   Navegable con teclado (flechas, Enter, Escape, `type-ahead`), con los `aria-*`
   que corresponden a un combobox. Debe degradar a un `<select>` nativo
   utilizable si el JS no cargó.
3. **Átomo `date`**: selector propio con calendario en español (semana
   empezando el lunes), formato de visualización del panel, navegación por
   teclado, y rango mínimo/máximo. Nada de depender del picker nativo.
   Si además hace falta rango (desde/hasta), resolvelo como una variante o un
   segundo átomo — el informe de la tarea 75 y los filtros lo van a pedir.
4. **Átomos `checkbox`, `checkbox-group` y `radio-group`**, con el mismo
   contrato de props. El `checkbox-group` tiene que servir para elegir varios
   ítems de una lista larga con búsqueda: es lo que consume la tarea 80
   (repuestos por casillas), así que dimensionalo para eso.
5. **Átomo `textarea`**, por consistencia: hoy también es marcado suelto.
6. **Migrá TODO el panel**: los 70 `<select>`, los 11 `type="date"` y los 7
   `type="checkbox"` pasan a los átomos nuevos. No dejes ninguno crudo — si
   queda uno, la próxima pantalla lo copia y volvemos a empezar.
7. **Iconografía Material Symbols** en los átomos nuevos (calendario, flecha de
   desplegable, búsqueda, check, limpiar), consistente con la que ya usa
   `input`.
8. **Terceros: permitido, pero que se gane el lugar.** Si una librería resuelve
   mejor el combobox o el calendario que un componente propio, usala — pero
   tiene que entrar por `package.json` + Vite (nada de CDN), pesar poco, cargar
   diferida como ya hace Leaflet en `resources/js/app.js`, y **no traer su
   propio tema de colores**: los colores salen de los tokens del panel, sin
   excepción (invariante 11). Si no cumple, escribilo propio y dejá anotado en
   el PR qué se evaluó y por qué se descartó.
9. **Documentá el catálogo** en `docs/diseno/sistema_diseno_panel.md`: qué
   átomo usar para cada caso, con ejemplo de uso.

## Qué NO hacer

- **Ningún color hardcodeado**, ni en el CSS propio ni sobrescribiendo el de una
  librería (invariante 11). Todo por token, con su valor para tema claro y
  oscuro, y contraste verificado en ambos.
- No rompas el contrato de props de `input` ni su manejo partido de
  `$attributes`: los átomos nuevos lo replican, no lo reinventan.
- No metas texto en el átomo: los strings llegan ya traducidos por el llamador
  (ADR 0013).
- No conviertas esto en un rediseño del panel. Es el sistema de inputs, no las
  pantallas: no muevas layouts, no cambies jerarquías, no toques el sidebar ni
  el header.
- No dejes un átomo nuevo sin usar: si lo creaste, migrá lo que le corresponde.

## Cómo repartir las etapas

- **Etapa 1**: exploración de dirección visual con los skills de diseño, y el
  átomo `select` con búsqueda + teclado + accesibilidad, con sus tests.
- **Etapa 2**: átomo `date` (y el rango si aplica), con tests.
- **Etapa 3**: `checkbox`, `checkbox-group`, `radio-group`, `textarea`.
- **Etapa 4**: migración de las 88 apariciones crudas, por módulo.
- **Etapa 5**: documentación del catálogo, regeneración de snapshots visuales
  (van a cambiar muchos: es esperado y es el punto de la tarea), `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- `grep -rn "<select" app/Dominios/*/Infraestructura/Http/Views/ resources/views/`
  no devuelve nada fuera del propio átomo `select`.
- `grep -rn 'type="date"' app/Dominios/*/Infraestructura/Http/Views/ resources/views/`
  no devuelve nada fuera del propio átomo `date`.
- `grep -rnE "#[0-9a-fA-F]{3,6}|rgb\(" resources/css/components/select.css resources/css/components/date.css`
  (y los CSS nuevos que agregues) no devuelve nada.
- Test de accesibilidad del `select`: se abre, se navega y se elige **solo con
  teclado**, y expone `role`/`aria-expanded`/`aria-activedescendant`.
- Test: el `date` acepta y devuelve el formato esperado por el request de la
  pantalla que lo usa (una fecha elegida en el calendario llega correcta al
  backend).
- Test: un formulario existente (contratos) sigue guardando igual después de la
  migración — misma request, mismos valores.
- Los snapshots visuales regenerados se ven bien en tema claro **y** oscuro.

## Puede tocar

`resources/views/components/atoms/**`, `resources/views/components/molecules/**`,
`resources/css/**`, `resources/js/**`, `package.json`, `vite.config.js`,
`app/Dominios/*/Infraestructura/Http/Views/**` (solo el reemplazo de marcado),
`docs/diseno/sistema_diseno_panel.md`, `tests/**`, `tests/Visual/**`.

Fuera de alcance: migraciones, casos de uso, controladores, permisos, el
sidebar y el header, cualquier regla de negocio.

## Cierre obligatorio de cada etapa

`runs/76.estado`, `runs/76.md`, y al `OK` `runs/76.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
