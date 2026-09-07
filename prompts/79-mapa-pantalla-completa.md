<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/mapa-pantalla-completa etapas=3 -->

# Tarea 79 — HU-56: el mapa del lote a pantalla completa, con acciones legibles

## Por qué esta tarea

Pedido del dueño el 7/9/2026: *"nuestro componente de mapas en los campos no
tiene la opción de ocupar toda la pantalla para tener más libertad en
seleccionar los puntos, con mejores iconos para indicar las acciones en cuanto
al diseño de los mapas. Si se puede usar la librería de Google Maps sería
ideal"*.

El editor de perímetro (`resources/js/organisms/lote-mapa-editor.js`, Leaflet +
Esri World Imagery + Leaflet-Geoman, PR #122) vive dentro de una fila del
formulario de campo. Dibujar un polígono de cientos de hectáreas con precisión
en un recuadro de formulario es exactamente el problema que describe. Y las
acciones se dibujan con el chrome nativo de Leaflet-Geoman, que
`resources/css/pages/campos.css` §173 ya está corrigiendo a mano — íconos
ajenos al panel, en inglés, sin relación con Material Symbols.

Depende de la tarea 78 (sin dónde guardar la llave, no hay Google Maps) y se
apoya en la 77 (el lote ya tiene pantalla propia).

## Lo que ya existe

- `resources/js/organisms/lote-mapa-editor.js` — Leaflet + Esri World Imagery +
  Leaflet-Geoman, cargado **diferido** desde `resources/js/app.js` solo cuando
  hay un `[data-ag-lote-mapa]` en la página ("Leaflet pesa, y no entra en el
  resto del panel"). Respetá esa decisión.
- `resources/js/organisms/dashboard-map.js` — el mismo stack para el mapa
  operativo del tablero.
- `resources/js/shared/color-tokens.js` — el puente que ya existe para pasarle
  a Leaflet colores desde tokens CSS, porque la librería pide un color literal.
  **Usalo**; no escribas un hex.
- `resources/css/pages/campos.css` §173 — las correcciones al chrome de Geoman.
- `Compartido/Contratos/LecturaConfiguracion` (tarea 78).

## Qué hacer

1. **Pantalla completa de verdad.** Un botón de expandir que lleve el editor a
   toda la ventana (Fullscreen API, con respaldo a un contenedor fijo al 100 %
   si el navegador la niega), con salida por botón y por `Escape`. Al volver,
   el polígono dibujado y el zoom se conservan — no se pierde el trabajo.
2. **Barra de acciones propia**, con Material Symbols y texto en español, en
   lugar del chrome nativo de Geoman: dibujar perímetro, editar vértices, mover,
   borrar, deshacer, centrar en el lote, y alternar capa (satélite / calles).
   Cada acción con `title` y `aria-label`; navegable con teclado. Es la parte
   que el dueño pidió como "mejores iconos para indicar las acciones".
3. **Ayudas para dibujar con precisión**: superficie calculada en hectáreas
   mientras se dibuja (comparable contra las hectáreas declaradas del lote), y
   la posibilidad de ajustar un vértice ya puesto sin rehacer el polígono.
4. **Proveedor de mapa configurable**, resuelto por `LecturaConfiguracion`:
   - Con llave de Google Maps cargada en `/panel/configuracion` → Google Maps.
   - Sin llave → **Leaflet + Esri World Imagery, exactamente como hoy**. Es el
     camino por defecto y tiene que seguir funcionando completo: Google Maps
     Platform se factura por uso y la llave puede no estar puesta, o agotarse.
   - La elección de proveedor **no cambia el dato**: el perímetro se guarda como
     GeoJSON `Polygon` en `com_lotes.geometria`, igual que hoy, con proveedor o
     sin él. Si el proveedor cambia mañana, la geometría no se toca.
   - La carga del SDK de Google va **diferida y solo si hay llave**, con el
     mismo criterio que ya usa Leaflet — nada de cargarlo en todo el panel.
   - Si el SDK falla al cargar, cae a Leaflet en vez de dejar el formulario sin
     mapa.
5. **Aplicá lo mismo al mapa operativo del tablero** donde tenga sentido
   (pantalla completa e íconos), sin duplicar el código del editor: lo común
   sale a un módulo compartido.

## Qué NO hacer

- **No hagas Google Maps obligatorio.** Sin llave, el panel funciona igual. Es
  un proveedor opcional, no un reemplazo.
- No pongas la llave en el código, en el HTML servido a cualquiera, ni en el
  repositorio: sale de la configuración de la tarea 78.
- No cambies el formato de `com_lotes.geometria` ni migres geometrías.
- No cargues el SDK de mapas en páginas que no tienen mapa.
- Ningún color hardcodeado, tampoco en los estilos del mapa (invariante 11):
  van por `color-tokens.js`.
- No conviertas esto en un rediseño del formulario de campo.

## Cómo repartir las etapas

- **Etapa 1**: pantalla completa + barra de acciones propia + superficie en
  vivo, sobre el Leaflet actual; tests.
- **Etapa 2**: abstracción del proveedor y camino de Google Maps con llave,
  con respaldo a Leaflet y prueba de la caída.
- **Etapa 3**: mapa operativo del tablero, snapshots, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`; las capturas
  visuales fallan en este Mac por ser win32 — no las persigas acá).
- Test: sin llave configurada, el editor carga con Leaflet + Esri y permite
  dibujar y guardar un polígono (el camino de hoy, intacto).
- Test: el GeoJSON guardado es idéntico dibujando el mismo polígono con un
  proveedor o con el otro.
- Test: entrar y salir de pantalla completa conserva el polígono en curso.
- Test: la llave de Google **no aparece** en el HTML cuando el proveedor
  configurado es Leaflet.
- `grep -rnE "#[0-9a-fA-F]{3,6}" resources/js/organisms/` no devuelve colores
  nuevos.

## Puede tocar

`resources/js/**`, `resources/css/**`, `resources/views/components/**`,
`app/Dominios/Comercial/**` (solo la vista del editor y su controlador),
`app/Dominios/Compartido/Contratos/**` (consumo de configuración),
`package.json`, `vite.config.js`, `lang/es/**`, `tests/**`, `tests/Visual/**`.

Fuera de alcance: `com_lotes` y su esquema, la tabla de configuración en sí
(tarea 78), el resto del panel.

## Cierre obligatorio de cada etapa

`runs/79.estado`, `runs/79.md`, y al `OK` `runs/79.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
