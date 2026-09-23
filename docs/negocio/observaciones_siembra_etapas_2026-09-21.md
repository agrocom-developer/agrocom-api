# Hallazgo de campo — Siembra, etapas del cultivo y armado del contrato (21/9/2026)

**Origen.** El 21/9/2026 el dueño contó, a partir de una conversación con un
cliente, cómo llega un lote a necesitar el servicio y por qué los lotes de un
contrato van juntos. Salió revisando la tabla de lotes del formulario de Órdenes
de aplicación, que no mostraba qué había sembrado en cada uno. Este documento
consolida esa conversación: no hace falta volver a preguntar qué se pidió.

**Estado.** Implementado en la rama `feature/siembra-lotes` lo de las secciones
2 y 3. De la sección 4, el dueño decidió el 22/9/2026 (rama
`feature/siembra-contrato-kpi`): **el contrato valida que sus lotes sembrados no
mezclen cultivo ni etapa** (`VerificadorCultivoDelContrato`; los lotes sin
siembra no bloquean) y el **listado de Cultivos lleva una franja de KPI**
(cultivos, sembrados hoy, lotes y hectáreas en las campañas abiertas). Siguen
abiertos la foto de la etapa en cada orden y el cruce con `tipo_aplicacion`.

---

## 1. Lo que contó el cliente

**1.1 El orden real de los hechos.** El cliente limpia el terreno, después
siembra, y **con los primeros brotes pide el servicio**. Recién ahí se arma el
contrato. La siembra es, entonces, un dato que existe ANTES del contrato.

**1.2 No siempre siembra el cliente.** Depende del servicio:

| Servicio | Qué hay en el lote | Qué registra Agrocom |
|---|---|---|
| Aplicación de **líquidos** sobre un cultivo en pie | El cultivo ya está, lo sembró el cliente | Qué cultivo es y **en qué etapa de su ciclo está** |
| Aplicación de **sólidos** (siembra de semilla, p. ej. pasto) | Solo el terreno limpio | A qué cultivo se destina el lote; la etapa es «Preparación del terreno» |

Agrocom no siembra por su cuenta un cultivo en pie: deja constancia de lo que
hay. Por eso «sin cultivo registrado» en un lote **no es un error**.

**1.3 El contrato junta lotes del mismo cultivo y la misma etapa.** La
aplicación que se registra en un contrato vale para un mismo cultivo dentro de
la campaña, y cada etapa pide un trabajo distinto.

**1.4 El caso que lo explica: la caña.** Una propiedad tiene todos sus lotes con
caña, pero escalonados para que haya zafra todo el año: un sector ya está en
cosecha, otro germinando, otro con la caña tierna, y en otro se está limpiando
el terreno para la nueva siembra. Mismo cultivo, todas las etapas a la vez. De
ahí dos consecuencias:

- la etapa es un dato **del lote en la campaña**, no del cultivo ni de la
  propiedad;
- saber la etapa de cada lote es lo que permite ver que los lotes elegidos en un
  contrato son los de ESE trabajo.

**1.5 Las etapas**, en el vocabulario del dueño:

| Valor | Etiqueta | Qué pasa |
|---|---|---|
| `preparacion` | Preparación del terreno | Se limpia el terreno para la nueva siembra |
| `germinacion` | Germinación | La semilla se activa y emite la primera raíz |
| `crecimiento` | Crecimiento vegetativo | La plántula emerge y desarrolla tallos y hojas |
| `floracion` | Floración | La planta forma capullos y flores |
| `fructificacion` | Fructificación | Las flores polinizadas desarrollan frutos y semillas |
| `cosecha` | Cosecha o madurez | El fruto o grano está listo para recolectarse (la zafra) |

`preparacion` no venía en la lista del dueño; sale de su propio ejemplo de la
caña («en otros el terreno se está limpiando») y es la que cubre el caso de
sólidos.

---

## 2. Lo que se implementó — modelo y pantalla de siembra

- **`com_lote_campania.etapa_cultivo`** (nullable, `CHECK` con los seis valores;
  enum `Comercial\Dominio\EtapaCultivo`). Es un dato que se registra y se
  corrige a mano, **no una máquina de estados**: no hay transiciones que
  guardar, así que no le aplica el invariante 7.
- **La pantalla de siembra de una propiedad** (`/panel/propiedades/{propiedad}/siembra`)
  pasó al arquetipo Formulario de la guía, y la siembra se carga por
  **sectores** (segunda vuelta del mismo día, pedido directo: una fila con
  cinco campos por lote no sirve para una propiedad de mil lotes):
  - un **sector** es un cultivo, su etapa y sus fechas, más los lotes que lo
    comparten; «Agregar sector» suma otro. Es la palabra que usó el dueño al
    explicar la caña. **No es una entidad**: al mostrar, las siembras guardadas
    se agrupan por lo que tienen en común; al guardar, cada sector se expande a
    una fila de `com_lote_campania` por lote. El caso de uso
    (`GuardarSiembraCampania`) no cambió;
  - los lotes de un sector se eligen en un modal con casillas (no arrastrar y
    soltar: no escala a cientos de lotes ni funciona en el celular): solo ofrece
    los lotes libres más los del propio sector, de a 40 por página en cuatro
    columnas que se leen de arriba abajo (la primera trae los primeros lotes),
    con «Marcar todos» —todas las páginas— y Mayús + clic para un tramo. Sin
    buscador (tercera vuelta, pedido directo). Un lote va en un solo sector, y
    cuando ya no quedan lotes libres «Agregar sector» deja de ofrecerse;
  - un lote que no queda en ningún sector se guarda sin cultivo (su siembra,
    si la tenía, se da de baja). Quitar todos los sectores y guardar deja la
    propiedad sin siembra en esa campaña;
  - **un sector siembra el lote entero**: las hectáreas sembradas son las del
    lote. Una siembra ya guardada con menos hectáreas conserva su valor. La
    carga de hectáreas parciales por lote salió de la pantalla (ver 4.5);
  - los lotes viajan en UN campo por sector, con los ids separados por coma:
    mil lotes como mil campos chocan con `max_input_vars` de PHP;
  - **alta vs. edición:** sin siembra guardada en la campaña la pantalla es un
    alta y va sin columna lateral; con siembra muestra el resumen de la campaña
    (lotes con cultivo, hectáreas, desglose por cultivo).
- **Tres entradas, según de dónde se viene** (cuarta vuelta, pedido directo):
  - **desde la propiedad:** cliente y propiedad llegan ya elegidos;
  - **desde la ficha de un cultivo** («Sembrar este cultivo», antes mandaba al
    listado de propiedades): `GET /panel/siembra`, la misma pantalla sin
    propiedad elegida — se cargan todos los clientes, las propiedades según el
    cliente, y el sector en blanco ya trae ese cultivo;
  - **desde la ficha de un lote:** `GET/POST /panel/lotes/{lote}/siembra`, el
    formulario de un solo sector sin elegir lotes, con cliente, propiedad,
    campaña y lote de solo lectura. Guarda una sola fila, así que no toca la
    siembra de los demás lotes. Es también donde se cargan **hectáreas
    sembradas menores que las del lote** (resuelve 4.5).
  Cliente, propiedad y campaña son selects que recargan la pantalla; ninguno
  viaja con el guardado (se guarda siempre en la propiedad de la ruta).
- **La siembra no tiene ítem de menú propio.** Es el cruce lote × campaña, no un
  objeto con identidad: se edita desde la propiedad (y se llega desde el lote).
- **Dónde entra en el flujo.** El flujo real (abrir campaña → contrato → cliente
  → propiedad → lotes → aprobar → órdenes) nunca pasaba por la siembra. El punto
  natural es al elegir los lotes del contrato: ya existen, y es donde el dato
  sirve. El modal de lotes del contrato lleva **«Registrar siembra»** junto a
  «Crear lote»: sale a la siembra de esa propiedad con la campaña del formulario
  y vuelve al contrato sin perder lo cargado (borrador + memento). Es opcional
  (en sólidos puede no haber nada que registrar), exige
  `comercial.propiedad.editar`, y la tarjeta «Siembra» de la ficha de la
  propiedad queda como segunda entrada.

## 3. Lo que se implementó — el dato a la vista donde se decide

Criterio del dueño: a medida que se entra en más detalle en cada formulario, esa
información se muestra.

- **Formulario de contrato, modal de selección de lotes:** columna «Cultivo y
  etapa», para la campaña elegida en el formulario. Es donde se decide qué lotes
  van juntos.
- **Formulario de orden de aplicación, tabla de lotes:** columnas «Cultivo y
  etapa» y «Terreno» (desnivel y limpieza, que ya viajaban y no se mostraban).
  Operaciones lo lee por el contrato de lectura de Comercial
  (`LecturaCultivoLote::deLotes()`), no por sus tablas.

En los dos, un lote sin siembra registrada se muestra apagado, nunca como aviso.

---

## 4. Decisiones abiertas — no resueltas

**4.1 ¿El contrato debe EXIGIR que sus lotes compartan cultivo y etapa?** Hoy
solo se muestra. No se validó a propósito: en sólidos los lotes pueden no tener
cultivo, y conviene ver primero cómo se carga el dato en campo (mismo criterio
que el 19/9 con las fechas del contrato en la orden). Alternativa intermedia:
un aviso que no bloquea cuando la selección mezcla cultivos o etapas.

**4.2 La etapa cambia con el tiempo y hoy se guarda una sola.** Un contrato dura
varias aplicaciones y el cultivo avanza entre una y otra. Hoy la etapa es «la
actual», corregida a mano. Si hiciera falta saber en qué etapa estaba el lote
en cada aplicación, el dato tendría que quedar también en la orden (una foto al
emitirla) — no está hecho.

**4.3 Relación con `tipo_aplicacion` de la orden** (`siembra` / `desarrollo` /
`cosecha`). Dicen casi lo mismo desde dos lados: la etapa, cómo está el lote; el
tipo, qué se le va a hacer. Hoy no se cruzan ni se sugieren entre sí.

**4.5 Hectáreas sembradas parciales — RESUELTO el mismo día.** Un sector
siembra el lote entero; el lote a medio sembrar se carga desde la siembra del
lote (`/panel/lotes/{lote}/siembra`), y un guardado posterior por sectores
conserva ese valor. Pendiente menor: el resumen de cada sector suma las
hectáreas de sus lotes, no las sembradas.

**4.4 Entrada por cultivo.** Propuesto y no hecho: en el listado de Cultivos,
una franja fija de KPI de la campaña vigente (cultivos con siembra, lotes,
hectáreas, propiedades); y en la ficha de cada cultivo, el «dónde está
sembrado» por propiedad y etapa. El desglose va en la ficha y no en el listado
porque crece con los datos (guía de pantallas §6.2, franja de KPI).
