# Observaciones del dueño — Operaciones y Comercial (13/9/2026)

**Origen.** El dueño entregó un Word (`MODULO OPERACIONES - COMERCIAL.docx`,
reemplazado 23 minutos después por una segunda versión con una sección nueva:
"Reporte de Equipos") con contenido marcado en rojo (`EE0000`) para lo que
consideraba de eliminar o de máxima importancia — sin un código único: hay que
leer cada caso por contexto, no asumir que rojo siempre significa lo mismo.
Además mandó dos audios, transcritos acá textual. Este documento es la fuente
de verdad de esa ronda: no hace falta volver a abrir el Word para saber qué se
pidió, ni para saber qué de eso ya estaba resuelto en el sistema.

**No es un fix, es una ronda de features.** Como en el negocio de Sprint 13
(7/9/2026), estas son historias de usuario nuevas o gaps de diseño, no bugs.
Se convierten en el **Sprint 16** de `docs/gestion/plan_sprints.md` y de ahí
en las filas 85–95 de `docs/gestion/cola_tareas.md`.

**Categorías usadas:** `YA_CUBIERTO` (la espec/código ya lo resuelve),
`GAP_REAL` (falta, se convierte en HU/TE), `CAMBIO_TERMINOLOGIA` (etiqueta de
panel, no modelo), `CONTRADICE_ARQUITECTURA` (choca con un ADR o invariante —
se resolvió con el dueño antes de escribir código) y `AMBIGUO` (no se entiende
qué pide; queda pendiente de una aclaración puntual, sin tarea).

---

## 1. Decisiones que el dueño resolvió hoy (13/9/2026)

### 1.1 CR-01 (mezcla del caldo) — **revertida**

El Word pide que la pantalla "Crear Aplicaciones (Piloto)" cargue **Calda:
Glifosato, 24D, Litros de agua, Urea** — nombre de producto y cantidad de la
mezcla. Esto chocaba de frente con CR-01 (`especificacion_funcional_tecnica.md`
§7, decisión del propio dueño del 1/9/2026: "Agrocom no modela fórmula, receta
ni dosis, ni siquiera como campo opcional").

**Confirmado con el dueño: cambió de opinión.** El piloto sí va a registrar
qué productos y qué cantidad se cargaron en el caldo. Esto es una **reversión
de una decisión de responsabilidad legal**, no un ajuste de pantalla — el
razonamiento de deslinde de responsabilidad de §7.1 seguía siendo válido
mientras existía, así que hay que ser explícitos en la especificación sobre
qué cambia y qué no (¿Agrocom pasa a responder por la fórmula, o solo la
transcribe como dato de lo que el cliente/agrónomo definió, sin validarla ni
calcularla?). Ver HU-78. Ya se dejó una nota fechada en `especificacion_funcional_tecnica.md`
§7 apuntando acá; la reescritura completa de esa sección es parte del alcance
de HU-78, no de este documento.

### 1.2 "Campos es lo mismo que lote" — **descartado, es vocabulario viejo**

El Word pedía eliminar la entidad "Campo" y dejar solo Propiedad → Lote.
Confirmado con el dueño: la jerarquía real es **Cliente → Propiedad → Campo →
Lote**, la misma que ya define el ADR 0018 desde el 10/9/2026 (motivado por el
caso real "Gamelera": una propiedad dividida en dos campos por una
carretera) — "una propiedad puede contener uno o más campos, un campo se
divide en lotes". **No se toca el modelo de datos.**

La queja del dueño es de **vocabulario residual**: viene de antes del ADR
0018, cuando "Campo" y "Propiedad" eran sinónimos en el panel, y probablemente
sigue viendo alguna pantalla o texto que todavía usa "Campo" donde debería
decir "Propiedad" (o viceversa). No se abre HU por esto: si en la próxima
revisión del panel aparece una etiqueta mal puesta, se corrige como hallazgo
de esa tarea, no como trabajo propio.

---

## 2. Gaps reales → Sprint 16 (`plan_sprints.md`) / cola 85–95

| HU | Qué pide el dueño | Categoría | Módulo |
|---|---|---|---|
| **HU-70** | "Dónde asignarle el trabajo al piloto" (audio 1) + "Cantidad de Equipos Necesarios" + "una Orden de Trabajo puede tener varias Trabajos" (rojo, 500 ha → Equipo 1 300 ha / Equipo 2 200 ha) + columna "equipo asignado" en Trabajos | GAP_REAL — el más importante, resuelve el reclamo activo | Operaciones (consume `equipos_trabajo` de Personal) |
| **HU-80** | Audio 2: "cantidad del ciclo de batería, ciclo actual, cuándo se emitió el reporte" + sección nueva "Reporte de Equipos" de la v2 del Word (horas de vuelo, foto de control, foto de cada ciclo de batería y balanceo, foto de dron limpio) | GAP_REAL (dato de ciclos ya existe en `Mantenimiento`, falta cruzarlo y armar el reporte) | Operaciones (lee Mantenimiento) |
| **HU-71** | Estado de contrato: En Ejecución / En Aprobación / Ejecutado / **Pausado** | GAP_REAL parcial — la máquina `borrador→vigente→finalizado→cancelado` YA EXISTE (`TransicionesContrato.php`); falta el estado `pausado` y traducir las etiquetas del panel al vocabulario del dueño | Comercial |
| **HU-72** | "Crear Lotes" con un botón: cuántos + tipo de siembra, renombrar y dibujar el polígono después | GAP_REAL (mejora de un flujo que ya existe a medias: `CrearCampo` ya acepta `lotes[]`, falta el generador por cantidad + cultivo) | Comercial |
| **HU-73** | Desniveles (NO/algunos/varios/empinado) y limpieza (SI/NO/algunos obstáculos/muchos obstáculos) del lote | GAP_REAL (no confundir con `com_lotes.restricciones`, que es texto libre sobre riesgos externos) | Comercial |
| **HU-74** | Acomodaciones: alimentación/hospedaje/combustible que brinda el cliente + observaciones | GAP_REAL | Comercial (impacta lectura de costo logístico en Finanzas a futuro) |
| **HU-75** | Cliente: ubicación de oficina central + logo. Contactos: Gerente General/Finanzas/Secretario | GAP_REAL, chico | Comercial |
| **HU-76** | Propiedad: Departamento/Municipio/Localidad/Coordenada | GAP_REAL — **amplía** ADR 0018 punto 1 (que decía "no se infiere esa estructura" a falta de un pedido explícito; ahora existe el pedido) | Comercial |
| **HU-77** | Campaña: estación Invierno/Verano, nombre autogenerado `Estación/AñoIni/AñoFin` | GAP_REAL parcial — **no** se agrega un booleano activa/inactiva: la máquina real es `planificada→abierta→cerrada`, irreversible desde `cerrada` a propósito (ADR 0015). El panel puede *mostrar* "Activa"/"Inactiva" como etiqueta, nunca permitir reabrir una campaña cerrada | Campania |
| **HU-78** | Calda: Glifosato/24D/Urea/litros de agua (registro de mezcla) | GAP_REAL grande — reversión de CR-01, ver §1.1 | Operaciones/Mezclas (módulo nuevo) |
| **HU-79** | Tipo Sólido/Líquido en la orden: kilos por vuelo (fertilizante, semilla de pasto) vs. litros por hectárea (insecticida, herbicida, fungicida, fertilizante líquido, coadyuvante, antiespumante) | GAP_REAL, el de mayor incertidumbre de diseño — depende de HU-78 y HU-70 | Operaciones |

**Detalle menor sumado a HU-78/HU-79, sin fila propia:** el Word pide
"Ráfagas (km/h)" en Clima, además del `viento_kmh` que ya existe en
`ope_condiciones`. Es una sola columna (`rafaga_kmh`, pico vs. sostenido) —
se agrega en la misma tarea que ya toca esa pantalla (HU-78 o HU-79, lo que
se implemente primero), no amerita una HU aparte.

---

## 3. Ya cubierto (no genera tarea)

- Cliente/Propiedad/Lotes con botón de alta rápida: las tres entidades existen
  (`com_clientes`, `com_propiedades`, `com_lotes`); falta solo el modal de
  alta rápida embebido — se resuelve como parte de HU-72, no aparte.
- Hectáreas contratadas, aplicaciones previstas, precio/ha, monto estimado,
  adelanto, fecha inicio/fin: 1 a 1 con `com_contratos` (espec. §4.1). El
  label "Monto Estimado" es más claro que `monto_total` — se adopta el label
  al tocar la pantalla, no hace falta migración.
  con HU-71/72.
- Parámetro de vuelo (altura, velocidad, ancho de aspersión): ya vive en
  `ordenes_aplicacion` (espec. línea 141). Esto confirma que el ítem en rojo
  "ELIMINAR PARAMETRO DE VUELO" de la sección Contratos es sacarlo de ahí
  porque ya está resuelto en Órdenes, no eliminarlo del sistema (ver §4).
- Número de contrato en la orden, fecha/hora inicio y fin, hectáreas
  aplicadas del lote, fotografía de aplicación del dron: cubiertos 1 a 1.
- Ph agua / Ph de la calda / litros por hectárea: encajan como medición de lo
  **recibido** (espec. §7.2, calidad del caldo), no como decisión de fórmula
  — no chocan con CR-01 ni antes ni después de su reversión. Se agregan junto
  con HU-78 por ser la misma pantalla.

---

## 4. Contradicciones/ambigüedades sin resolver — no generan tarea todavía

Preguntar al dueño con la pantalla o el ejemplo delante antes de escribir
código:

1. **"ELIMINAR PARAMETRO DE VUELO (NO APLICA)" + "VENTANA DE APLICACION
   (ORDEN DE APLICACION)"**, ambos en Contratos. La especificación agrupa
   parámetros de vuelo junto con los límites climáticos de la guarda de
   autorización (viento/temperatura/humedad) en un solo bloque de columnas de
   `com_contratos`; y `com_contrato_ventanas` es una decisión explícita de
   ADR 0015 punto 5 (ventana por **contrato**, no por orden). Antes de tocar
   cualquiera de las dos tablas, confirmar: (a) ¿se elimina *todo* el grupo de
   parámetros de vuelo y límites climáticos del contrato, o solo lo
   redundante con la orden (altura/velocidad/ancho)? (b) ¿la ventana horaria
   pasa de contrato a orden, cambiando su cardinalidad, o es solo una
   sugerencia de dónde mostrarla en pantalla?
2. ~~**"TRABAJO (EMILIMINAR)"**~~ — **resuelto, no era un pedido.** Al leer
   el PDF (en vez del texto plano) se ve que es un encabezado de sección en
   **amarillo**, el mismo color que usa el dueño para "CONTRATOS:", "MODULO
   OPERACIONES" y "MODULO COMERCIAL" — es decir, es una nota personal al
   redactar el documento (recordatorio de revisar/borrar ese título antes de
   mandarlo), no rojo ni un pedido funcional. No genera tarea ni aclaración.
3. **"Cambiar número de aplicación a Número de aplicaciones"** — puede ser
   solo el plural del label de `ordenes_aplicacion.nro_aplicacion`, o puede
   estar confundiéndose con `contratos.aplicaciones_previstas` (el total
   pactado). Son dos campos distintos y ya existen ambos.
4. **"Debería tener la opción de eliminar trabajo o editar trabajo"**
   (rojo parcial). Si es sobre un trabajo **no validado**: ya está cubierto
   por soft delete (invariante 8), solo falta el botón. Si es sobre uno
   **validado**: choca con la invariante 2 (nunca se sobrescribe un registro
   validado) — la vía correcta ya existe (corrección con `anula_a_id`), solo
   habría que exponerla como botón "Corregir". Mientras no se aclare el
   alcance exacto, no se escribe HU — el criterio por defecto al implementar
   HU-70 es: **eliminar/editar libre solo antes de validar**.

---

## 5. Fuera de este repo — ver documento aparte para `agrocom-field`

Estas dos observaciones son de la app del piloto (Flutter, otro repositorio) y
no generan tarea acá:

- **"SESIONES: OCULTAR" / "PAUSAS: OCULTAR"** en la pantalla de creación de
  aplicación del piloto.
- El pedido del jefe (por audio, transmitido por el dueño) de que la app
  tenga toda la información de la asignación al iniciar sesión, para no
  mandar el trabajo por WhatsApp.

Ver `docs/gestion/pendiente_agrocom_field_2026-09-13.md`.
