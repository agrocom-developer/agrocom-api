<!-- ciclo: critica=no turno-noche=1 rama=feature/base-mapa-marcador etapas=2 -->

# Tarea 132 — la ficha de Base pide latitud/longitud a mano, en vez de un mapa

Pedido del dueño (22/9/2026), mirando `/panel/bases/1/editar`: tipear
latitud/longitud como número no sirve de nada para ubicar una base en el
terreno; el pedido es reemplazar esos dos inputs por un mapa donde se
coloca un marcador (arrastrable) y los dos campos numéricos se llenan solos.

El dueño también cuestionó si conviene un desglose administrativo
(departamento/provincia/municipio/localidad) en vez del campo `ubicacion`
libre que hay hoy — **esa parte NO es alcance de esta tarea**: tocar eso es
agregar columnas y probablemente un catálogo nuevo, una decisión de modelo de
datos que el dueño tiene que confirmar aparte. Dejalo anotado en
`runs/132.md` como pregunta abierta y no toques `ubicacion`.

Cargá los skills `panel-design-ui` y `verificacion`.

## Ya hay un editor de mapa en el proyecto — no arranques de cero

`resources/js/organisms/propiedad-mapa-editor.js` (usado en
`/panel/propiedades/{propiedad}/mapa`) ya resuelve la pieza de MARCADOR
arrastrable + inputs ocultos + buscador de coordenadas, con Leaflet +
`leaflet/dist/leaflet.css` (el proveedor lo decide `ResolverProveedorMapa`
del lado del servidor, llega por `data-*`). Leelo entero (es largo, ~1360
líneas, pero la lógica del marcador está aislada de la de polígonos — la
tarea de Base solo necesita esa parte, sin Geoman ni multi-polígono). Base es
un objeto de cuatro campos planos, sin terreno que dibujar: **no repliques el
editor de página completa de Propiedad** (esa es una pantalla dedicada de
pantalla completa para dibujar polígonos); acá alcanza con un mapa chico,
embebido en `_formulario.blade.php`, con un solo marcador.

## Qué hacer

### Etapa 1 — el módulo del mapa

- `resources/js/organisms/base-mapa-marcador.js` (nombre nuevo, no extiendas
  el de Propiedad — mismo criterio de aislamiento que ya explica su docblock:
  compartir el chunk de Leaflet entre editores rompió la inicialización en
  producción el 16/9/2026): un mapa embebido (no pantalla completa) con un
  único marcador arrastrable, centrado en las coordenadas actuales de la base
  (o un centro por defecto de Bolivia/Santa Cruz si es alta y no hay
  coordenadas todavía) + click para reposicionar + los inputs
  `latitud`/`longitud` que ya existen en `_formulario.blade.php` pasan a
  `type="hidden"` y los llena el marcador (sin buscador de coordenadas — eso
  sí es del editor de Propiedad, acá no hace falta).
- Cargado por `import()` dinámico desde `app.js` cuando la página tiene un
  `[data-ag-base-mapa]`, mismo criterio que los otros editores.
- Sin permiso nuevo: el formulario de Base ya está gateado por
  `personal.base.crear`/`.editar` (comprobalo en `BasesController`).

### Etapa 2 — integración en el formulario

- `_formulario.blade.php`: agregá el contenedor del mapa en la sección de
  datos (junto a `nombre`/`ubicacion`), con `data-ag-base-mapa`,
  `data-latitud-inicial`/`data-longitud-inicial` (vacíos en alta) y los
  atributos que el proveedor de mapa necesite (mismo patrón que
  `propiedades/mapa.blade.php`). Los inputs `latitud`/`longitud` ocultos
  siguen viajando con el mismo `name` — el `Request` de alta/edición de Base
  no cambia.
- `resources/css/pages/bases.css`: alto fijo razonable del mapa embebido
  (no pantalla completa), token de borde/radio del catálogo.
- Sin marcador colocado (alta, sin click todavía): el submit debe seguir
  funcionando igual que hoy si `latitud`/`longitud` quedan vacíos (mismo
  comportamiento actual — son opcionales, revisá `CrearBaseRequest` antes de
  asumir).

## Qué NO hacer

No toques `PerBase`, migraciones, ni el `Request` de alta/edición (los
campos siguen siendo los mismos dos decimales). No agregues
departamento/provincia/municipio/localidad. No reuses el JS de Propiedad
importándolo (aislar el chunk, ver arriba). No le pongas al mapa de Base
dibujo de polígonos ni Geoman.

## Criterio de aceptación

- `./bin/verify` = 0.
- `grep -c 'type="number"' app/Dominios/Personal/Infraestructura/Http/Views/pages/bases/_formulario.blade.php` = 0 (los dos campos pasan a `hidden`).
- Prueba en navegador: crear una base arrastrando el marcador guarda
  latitud/longitud coherentes con el punto elegido; editar una base existente
  centra el mapa en sus coordenadas actuales y mover el marcador actualiza el
  valor al guardar. Capturas en `runs/132-capturas/` (ruta absoluta), claro y
  oscuro, alta y edición.

## Cierre obligatorio de cada etapa

`runs/132.estado`, `runs/132.md` (con la pregunta abierta del desglose
administrativo anotada), y al `OK` `runs/132.pr.md`. Commits por función, en
español, imperativo, sin `Co-Authored-By`.
