# Pendiente: "Propiedad" con más de un campo físico, y delimitar el campo antes de marcar sus lotes

**2026-09-10.** Surgió revisando el bug de guardado del editor de mapa de
lotes (rama `fix/edicion-lotes`) — no es parte de ese fix, queda anotado acá
para abrirlo en una rama propia una vez que ese PR esté mergeado a `develop`.
Ya se investigó contra el modelo de datos real y contra la especificación —
lo que sigue es lo confirmado, no una hipótesis a verificar después.

## El problema, como lo planteó el dueño

Hoy el editor de mapa de un lote (`campos/_lote-fila.blade.php`,
`resources/js/organisms/lote-mapa-editor.js`) dibuja el polígono sobre un
mapa satelital en blanco, sin ninguna referencia al perímetro del campo al
que pertenece ese lote. El dueño hizo notar que el orden lógico es al revés:
primero se delimita el **campo** (el área física), y recién a partir de ese
perímetro ya delimitado se marca qué lotes hay adentro — no tiene sentido
ofrecer un espacio libre para "asignar" lotes que en la realidad ya están
contenidos en un límite conocido.

Ejemplo real que dio, textual: la propiedad "Gamelera" del cliente Bruno
Macedo está dividida en **dos campos físicos** de 1500 ha cada uno, separados
por una carretera que pasa por el medio — un campo se compró primero y el
segundo se unificó después bajo el mismo dueño. Cada uno de esos campos de
1500 ha está a su vez dividido en lotes de 200 ha. En **campañas divididas**
van a fumigar los dos campos de esa misma propiedad, pero como campañas
separadas (una por campo).

## Lo que confirmó la investigación contra el código y la especificación

**"Propiedad" y "Campo" son la misma cosa en todo el sistema hoy — 1:1, sin
excepción.** No solo el esquema (`com_campos`: `id`, `cliente_id`, `nombre`,
`ubicacion`, auditoría — nada más) trata "campo" como una fila única; el
vocabulario de negocio también lo hizo siempre así:

- La especificación funcional lo define explícito: *"Un cliente tiene varios
  campos (haciendas); cada campo tiene varios lotes"*
  (`especificacion_funcional_tecnica.md:120`) — dos niveles, no tres.
- `CrearLoteRequest`, el select del formulario de lote y hasta los mensajes
  de validación (`"Seleccioná una propiedad"`, `"La propiedad seleccionada
  no es válida"`) usan "propiedad" como sinónimo literal de `campo_id`.
- Hace tres días (2026-09-08, tarea 77, HU-54) el propio dueño pidió
  renombrar el ítem de menú de "Campos y lotes" a **"Propiedades"** — sin
  tocar el modelo de datos. La palabra ya venía empujando en esta dirección
  antes de este pedido.

**Y sin embargo, el propio dueño ya había usado "propiedad" como un tercer
nivel, distinto de "campo", en al menos dos citas textuales previas** (ADR
0015 y la especificación de combustible): *"cuánto y a cómo se usó gasolina
[...] por lote, campo y propiedad"*. Eso ya era una señal de que el negocio
piensa en tres niveles aunque el sistema solo modele dos — el caso Gamelera
es la primera vez que esa distinción se vuelve concreta y bloqueante, no un
capricho nuevo.

**Conclusión: esto es una laguna real de la especificación, no un caso ya
resuelto.** Ningún ADR ni la especificación contemplan una propiedad
partida en más de un campo físico, y "campañas divididas" no aparece en
ningún documento del repo.

**Si se intentara simular Gamelera hoy sin tocar el modelo (dos filas de
`Campo` bajo el mismo `Cliente`, ej. "Gamelera Norte"/"Gamelera Sur"),
funcionaría a medias:**
- Cliente → N campos ya existe, así que las dos filas se pueden cargar.
- Pero no hay ningún campo que exprese que ambas son la misma propiedad
  física — ni `propiedad_id`, ni agrupador, nada. La única columna de texto
  libre (`ubicacion`) no se usa como agrupador en ningún lado del código.
- El índice único `com_campos_nombre_unico` (`cliente_id`, `nombre`) impide
  además la única convención naive que alguien podría probar (nombrar los
  dos campos igual) — hay que inventarles nombres distintos ("Norte"/"Sur")
  sin que el sistema sepa que están emparentados.

**`Campo` no tiene geometría propia y no puede validarse contra la del lote
sin un cambio de infraestructura.** Sin PostGIS (ADR 0001: la geometría es
JSONB, "se guarda y se dibuja, no se consulta espacialmente"), no hay forma
de que la base valide que el polígono de un lote caiga dentro del polígono
de su campo — cualquier delimitación del campo, en v1, sería solo una capa
visual de referencia en el mapa del lote, no una restricción exigida.

**`Campania` ya tolera campañas paralelas del mismo cliente — sin ningún
atado estructural a "campo".** `cpn_campanias` solo tiene `cliente_id`, sin
`campo_id`; el ADR 0015 dice textual que el dueño pidió *"que sea flexible"*
y no hay guarda de solapamiento, ni entre clientes ni dentro de uno. Es
decir: "campañas divididas" (una campaña por cada campo de Gamelera) **ya
funciona mecánicamente** hoy, con dos códigos de campaña distintos y
asignando a mano los lotes de cada campo a su campaña vía
`com_lote_campania` — pero nada impide cargar mal esa asignación, porque el
sistema no sabe que esos lotes "pertenecen" al mismo campo físico más que
por la columna `campo_id` de cada lote (que sí existe y sí es correcta,
solo que ninguna validación cruza eso contra la campaña).

## Ideas para la solución, de más barata a más cara

1. **Decidir si hace falta una entidad `Propiedad` por encima de `Campo`**,
   o si alcanza con seguir usando `Campo` = "campo físico delimitado" y
   resolver la agrupación Gamelera con algo más liviano (un `propiedad_id`
   autorreferenciado, o un `grupo` opcional en `Campo`). Esto es una
   decisión de modelado real, no mecánica — conviene pasarla por el agente
   de arquitectura antes de migrar nada.
2. **Delimitar el campo primero.** Agregar geometría/perímetro propio a
   `Campo` (mismo editor Leaflet que ya existe para `Lote`, reusado), y que
   el mapa de cada lote se abra mostrando ese perímetro como capa de
   referencia visual (no como restricción validada — ver limitación de
   PostGIS arriba).
3. **Punto de partida editable, sin visión por computadora.** Una vez
   delimitado el campo, un botón "dividir en lotes automáticamente" que
   corte ese polígono en franjas de N hectáreas (dato que ya se carga) —
   no reconoce lotes reales, pero da una base editable en vez de un mapa en
   blanco. Barato, sin dependencias nuevas.
4. **Detección automática de lotes por imagen satelital** (idea del dueño,
   fase posterior). Es un problema de segmentación/visión por computadora,
   no una función chica: con imágenes gratuitas (Esri, lo que ya usa el
   editor) la precisión en campo real —caminos internos, cortinas
   forestales— suele ser floja sin entrenar un modelo o pagar un servicio
   agtech especializado. Evaluar costo/precisión real antes de
   comprometerse, y solo si el punto 3 no alcanza.

## Próximo paso

No se toca nada de esto en `fix/edicion-lotes`. Cuando ese PR esté mergeado
a `develop`, abrir una rama nueva y corta para esto. Primer paso de esa
rama: la decisión de modelado del punto 1 (¿entidad `Propiedad` nueva o
agrupador liviano sobre `Campo`?) — recién con eso resuelto tiene sentido
tocar migraciones o el editor de mapa.
