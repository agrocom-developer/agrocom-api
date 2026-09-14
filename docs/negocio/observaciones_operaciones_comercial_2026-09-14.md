# Resolución de ambigüedades — Operaciones y Comercial, segunda vuelta (14/9/2026)

**Origen.** `observaciones_operaciones_comercial_2026-09-13.md` §4 dejó tres
puntos sin HU porque el Word original solo se había leído indirectamente y no
alcanzaba para decidir sin preguntar. El 14/9/2026 el dueño pegó el texto
completo del documento original y resolvió los dos primeros en el momento;
del texto completo también salió un pedido que la lectura parcial del 13/9 no
había capturado (Orden de Aplicación con varios lotes) y una corrección sobre
Adelanto que tampoco estaba registrada. Este documento consolida esa ronda,
mismo criterio que el del 13/9: no hace falta volver a abrir el Word para
saber qué se pidió.

---

## 1. Adelanto — corrección, no ambigüedad

El documento original pide **un solo campo**, punto 9 de Contratos:
"Adelanto Solicitado". El sistema hoy tiene dos (`adelanto_monto` y
`adelanto_pct`) — `adelanto_pct` nunca estuvo pedido, es un campo que se
agregó de más. Se elimina, y `adelanto_monto` se relabelea a "Adelanto
Solicitado" (mismo criterio de adopción de label sin migración que ya se usó
con "Monto Estimado" para `monto_total`, §3 del documento del 13/9). → **HU-91**.

## 2. "Parámetros de vuelo" del contrato — ambigüedad 1 resuelta

El punto sin resolver del 13/9 era: *"ELIMINAR PARAMETRO DE VUELO (NO APLICA)"
+ "VENTANA DE APLICACION (ORDEN DE APLICACION)"*, ambos vecinos en el Word
dentro de Contratos. La especificación agrupa parámetros de vuelo junto con
los límites climáticos de la guarda de autorización en un solo bloque de
columnas de `com_contratos` — no estaba claro si se sacaba todo el grupo o
solo lo redundante con la Orden, ni si la ventana horaria cambiaba de
cardinalidad.

**Confirmado con el dueño (14/9/2026): las dos líneas son la misma
instrucción**, leída en el contexto de la pantalla real
(`/panel/contratos/crear`), donde la sección "Parámetros de vuelo" está al
lado de "Ventanas de aplicación" en el formulario — la mención a "ORDEN DE
APLICACION" no reubica la ventana, repite que los parámetros de vuelo no van
en el contrato. Textual: *"es lo que mencioné que elimine todo el sector de
Parámetros de vuelo dentro del: http://localhost:8000/panel/contratos/crear y
en mi lista de contratos, esos no van ahí"*.

Se elimina **la sección completa**, los 7 campos — no solo `altura_vuelo_m`:
`viento_max_kmh`, `temperatura_max_c`, `humedad_min_pct`, `humedad_max_pct`,
`velocidad_max_kmh`, `umbral_reporte_avance_ha`, `altura_vuelo_m`. El contrato
deja de tener límites propios: todo hereda de la Orden o del valor por
defecto del sistema (RF-60). **`com_contrato_ventanas` no se toca** — sigue
siendo N por contrato, ADR 0015 punto 5 vigente sin cambios. → **HU-91**.

## 3. "Cambiar número de aplicación a Número de aplicaciones" — ambigüedad 3 resuelta

Confirmado: es el plural del label de `ordenes_aplicacion.nro_aplicacion`, no
`contratos.aplicaciones_previstas` (que son dos campos distintos y ya
existían ambos, como decía el documento del 13/9). CAMBIO_TERMINOLOGIA, se
resuelve junto con HU-92 por ser la misma pantalla.

## 4. Orden de Aplicación pasa a cubrir varios lotes — no estaba en la lectura del 13/9

Esto no era una de las ambigüedades registradas: la lectura parcial del Word
no había capturado que la Orden de Aplicación debía cubrir más de un lote.
Con el texto completo a la vista aparece explícito: *"Lotes: La Orden de
Aplicación se puede ser asignada a Varios Lotes de la Propiedad, para que
posteriormente en Trabajos se cree automáticamente un Trabajo... se asigne
varios trabajos a una sola orden en el caso de hacer con dos o más equipos
para acabarla"*, y por cada equipo: *"Lotes (selección múltiple) y
Hectáreas"*.

HU-70 (ya integrada, PR #189, mergeado a `develop` el 14/9/2026) construyó la
asignación de equipos asumiendo el modelo vigente de `ope_ordenes_aplicacion`:
1 orden = 1 lote, con índice único "una orden vigente por lote". Confirmado
con el dueño que el pedido es real y más amplio que eso — **es una
ampliación de HU-70, no una corrección de bug**, mismo tratamiento que la
reversión de CR-01 (HU-78): requiere cambio de esquema (N lotes por orden vía
una tabla de detalle nueva) y la pantalla `/panel/asignacion-equipos` pasa a
pedir, por cada equipo, qué lotes de la orden le tocan además de las
hectáreas. → **HU-92**.

## 5. Editar/eliminar Trabajo y columnas del listado — ambigüedad 4, sin cambio de criterio

El dueño no agregó matices nuevos sobre validado/no validado: sigue el
criterio por defecto ya escrito en
`observaciones_operaciones_comercial_2026-09-13.md` §4.4 ("eliminar/editar
libre solo antes de validar; sobre uno validado, la vía es la corrección con
`anula_a_id`, invariante 2"). Lo que sí queda confirmado con el documento
completo son las columnas por defecto del listado: **Nro. Trabajo, Orden de
Trabajo, equipo asignado, Hectáreas, Estado**. Verificado contra
`TrabajosController`/`trabajos/index.blade.php`: hoy no hay columna "Orden de
Trabajo" ni "equipo asignado", y el controlador es de solo lectura
(`index`/`show`, sin `create`/`edit`/`destroy`) — GAP_REAL confirmado, no
hacía falta preguntar nada más. → **HU-93**.

## 6. Todo lo demás del documento completo ya estaba cubierto

Verificado contra el texto completo, sin encontrar diferencias con lo ya
planificado: alta rápida de Cliente/Propiedad/Lotes (HU-72), desnivel/limpieza
de lote (HU-73), acomodaciones (HU-74), directorio de cliente y contactos
(HU-75), ubicación de propiedad (HU-76), estado de contrato con vocabulario
del negocio (HU-71), campaña con estación y nombre autogenerado (HU-77), tipo
sólido/líquido con el catálogo exacto — el catálogo del documento (sólido:
fertilizante/semilla de pasto en kilos por vuelo; líquido:
insecticida/herbicida/fungicida/fertilizante/coadyuvante/antiespumante en
litros por hectárea) coincide 1 a 1 con el ya escrito en `plan_sprints.md`
para HU-79, sin cambios — Drones/Baterías/Vehículos/Base/Generador (Sprint
17), Orden de mantenimiento y ocultar Plan de Mantenimiento/Repuestos/Stock
Base (Sprint 18), y "Campos es lo mismo que lote" (ya resuelto, vocabulario
viejo, §1.2 del documento del 13/9). Todo lo de "CREAR APLICACIONES
(PILOTO)", "SESIONES: OCULTAR" y "PAUSAS: OCULTAR" sigue fuera de este repo
(`agrocom-field`, ver `docs/gestion/pendiente_agrocom_field_2026-09-13.md`).

## 7. Hallazgo adicional: el perímetro del Campo nunca se llegó a implementar

No viene del documento — el dueño lo encontró probando `/panel/campos/crear`
en esta misma ronda: *"los campos son espacios grandes de terreno donde se
limita por coordenadas de un mapa, no podemos agregar lotes ahí mismo si no
se define primero el espacio del campo; ya en los lotes se tiene que tener
previamente un campo seleccionado para ver los límites del mismo y a partir
de ahí se divide el campo por lotes"*.

Verificado contra el código: `com_campos.geometria` existe en la base desde
el ADR 0018 (GeoJSON, "perímetro del campo, delimitado antes que sus lotes",
dice el propio comentario de la migración) y el modelo `Campo` ya la declara
`fillable`/`cast`. Pero **nunca se construyó el formulario que la carga** —
`CrearCampoRequest` lo documenta explícito desde que se escribió: *"El
perímetro propio del CAMPO (`com_campos.geometria`, ADR 0018) no tiene campo
de formulario todavía — el editor de mapa que lo delimita como capa de
referencia queda fuera de esta tarea"*. Consecuencia real: en
`/panel/campos/crear` se puede dibujar el polígono de un lote (`_lote-fila.blade.php`
ya trae el editor de mapa completo) sin que el campo que lo contiene tenga
ningún límite propio guardado ni visible — exactamente el bug que describe el
dueño. GAP_REAL confirmado, no hace falta preguntar nada más: el fix es dar
al Campo el mismo editor de mapa que ya tienen los lotes, y que el mapa del
lote pinte el perímetro del campo elegido como referencia. → **HU-94**.

---

**Nuevo en Sprint 16 de `plan_sprints.md`:** HU-91, HU-92, HU-93, HU-94 —
filas 106 a 109 de `cola_tareas.md`.
